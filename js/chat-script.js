document.addEventListener("DOMContentLoaded", function() {
    let messageLimit = { used: 0, max: 0 };
    let loadedMessageCount = 0;
    let allMessagesLoaded = false;


    function updateUsageUI() {
        const counter = document.getElementById("usage-counter");
        if (!counter) return;
    
        const { used, max } = messageLimit;
        const percent = used / max;
    
        counter.textContent = `${used} / ${max} messages used today`;
    
        counter.classList.remove("low", "medium", "high");
        if (percent < 0.5) {
            counter.classList.add("low");
        } else if (percent < 1) {
            counter.classList.add("medium");
        } else {
            counter.classList.add("high");
        }
    }
    
    
    function fetchMessageUsage() {
        fetch(mindthriveChat.ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "get_message_usage",
                security: mindthriveChat.security
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                messageLimit = data.data;
                updateUsageUI();
            }
        });
    }
    


    const chatWindow = document.getElementById("chat-window");
    const userInput  = document.getElementById("user-input");
    const sendBtn    = document.getElementById("send-btn");
    const menuBtn    = document.getElementById("menu-toggle");
    const settingsToggle = document.getElementById('settings-toggle');
    const settingsMenu = document.getElementById('settings-menu');
    const typingIndicator = document.getElementById('typing-indicator');

    /**
     * Appends a text bubble to the chat window.
     */
    function appendMessage(text, sender) {
        if (!chatWindow) return;
        const messageDiv = document.createElement("div");
        messageDiv.classList.add("message", sender === "user" ? "user-message" : "ai-message");

        const textSpan = document.createElement("div");
        textSpan.classList.add("message-text");
        textSpan.innerHTML = text;

        messageDiv.appendChild(textSpan);
        chatWindow.appendChild(messageDiv);
        messageDiv.scrollIntoView({ behavior: 'smooth' });
    }

    /**
     * Fetch chat history from the server and display it.
     */
    function loadChatHistory(offset = 0, prepend = false) {
        fetch(mindthriveChat.ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "fetch_chat_history",
                security: mindthriveChat.security,
                offset: offset
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && Array.isArray(data.data.history)) {
                if (data.data.history.length < 20) {
                    allMessagesLoaded = true;
                }
    
                const fragment = document.createDocumentFragment();
    
                data.data.history.forEach(msg => {
                    const user = document.createElement("div");
                    user.classList.add("message", "user-message");
                    user.innerHTML = `<div class="message-text">${msg.message_text}</div>`;
                    fragment.appendChild(user);
    
                    const ai = document.createElement("div");
                    ai.classList.add("message", "ai-message");
                    ai.innerHTML = `<div class="message-text">${marked.parse(msg.ai_response)}</div>`;
                    fragment.appendChild(ai);
                });
    
                if (prepend) {
                    chatWindow.prepend(fragment);
                } else {
                    chatWindow.appendChild(fragment);
                }
    
                loadedMessageCount += data.data.history.length;
            }
        });
    }
    


    chatWindow.addEventListener('scroll', () => {
        if (chatWindow.scrollTop < 50 && !allMessagesLoaded) {
            loadChatHistory(loadedMessageCount, true);
        }
    });
    
        // Load history on startup
        loadChatHistory(0, false);
        fetchMessageUsage();


    function typeTextAsHTML(element, html, delay = 30) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const nodes = Array.from(tempDiv.childNodes);
    
        function renderNode(index = 0) {
            if (index >= nodes.length) return;
    
            const node = nodes[index];
            const clone = node.cloneNode(true);
    
            if (clone.nodeType === Node.TEXT_NODE) {
                let i = 0;
                const fullText = clone.textContent;
                const span = document.createElement('span');
                element.appendChild(span);
    
                function typeChar() {
                    if (i < fullText.length) {
                        span.textContent += fullText.charAt(i);
                        span.scrollIntoView({ behavior: 'auto' });
                        i++;
                        setTimeout(typeChar, delay);
                    } else {
                        renderNode(index + 1);
                    }
                }
    
                typeChar();
            } else {
                element.appendChild(clone);
                renderNode(index + 1);
            }
        }
    
        renderNode();
    }
    
    

    /**
     * Sends the user's message to the server via AJAX and handles the streaming response.
     */
    function sendMessage() {
        if (messageLimit.used >= messageLimit.max) {
            alert("You've reached your daily message limit. Upgrade your plan for more support.");
            return;
        }
        messageLimit.used += 1;
        updateUsageUI();      
        
        
        const message = userInput.value.trim();
        if (!message) return;

        appendMessage(message, "user");
        requestAnimationFrame(() => {
            chatWindow.scrollTo({ top: chatWindow.scrollHeight, behavior: 'smooth' });
          });
        
        userInput.value = "";
        sendBtn.disabled = true;

        const aiMessageDiv = document.createElement("div");
        aiMessageDiv.classList.add("message", "ai-message");
        const textSpan = document.createElement("div");
        textSpan.classList.add("message-text");
        aiMessageDiv.appendChild(textSpan);
        chatWindow.appendChild(aiMessageDiv);

        typingIndicator.style.display = 'block';

        const eventSource = new EventSource(`${mindthriveChat.ajaxurl}?action=mindthrive_chat_stream&message=${encodeURIComponent(message)}&security=${mindthriveChat.security}`);

        let markdownBuffer = '';



        eventSource.onmessage = (e) => {
        if (e.data === '[DONE]') {
            typingIndicator.style.display = 'none';
            eventSource.close();
            sendBtn.disabled = false;

            // ✅ Parse and format the full markdown response
            const formattedHTML = marked.parse(markdownBuffer);

            // Replace the streamed text with formatted HTML
            textSpan.innerHTML = formattedHTML;
            aiMessageDiv.scrollIntoView({ behavior: 'smooth' });

            markdownBuffer = '';
            return;
        }

        try {
            const json = JSON.parse(e.data);
            if (json.content) {
            markdownBuffer += json.content;

            // ✅ Stream text as plain text (typing effect)
            textSpan.textContent += json.content;
            textSpan.scrollIntoView({ behavior: 'auto' });
            }
        } catch (err) {
            console.error("JSON parse error:", err, e.data);
        }
        };



        eventSource.onerror = (err) => {
            console.error("Streaming error:", err);
            eventSource.close();
            sendBtn.disabled = false;
            typingIndicator.style.display = 'none';
            appendMessage("An error occurred during streaming.", "ai");
        };
    }

    // Click event on Send button
    sendBtn.addEventListener("click", sendMessage);

    // Press Enter to send message
    userInput.addEventListener("keypress", function(event) {
        if (event.key === "Enter") {
            event.preventDefault();
            sendMessage();
        }
    });

    // Menu button logic
    if (menuBtn) {
        menuBtn.addEventListener("click", function() {
            if (window.innerWidth < 768) {
                if (
                    typeof elementorProFrontend !== "undefined" &&
                    elementorProFrontend.modules &&
                    elementorProFrontend.modules.popup
                ) {
                    elementorProFrontend.modules.popup.showPopup({ id: 55 });
                } else {
                    console.error("Elementor Pro popup is not available.");
                }
            } else {
                window.location.href = "/menu";
            }
        });
    }

    // Settings menu toggle
    settingsToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        settingsMenu.classList.toggle('hidden');
    });

    document.addEventListener('click', (event) => {
        if (!settingsMenu.contains(event.target) && event.target !== settingsToggle) {
            settingsMenu.classList.add('hidden');
        }
    });

    // Clear Chat functionality (exactly once!)
    document.getElementById('clear-chat-btn').addEventListener('click', () => {
        if (confirm("Are you sure you want to clear the chat history?")) {
            fetch(mindthriveChat.ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'clear_chat_history',
                    security: mindthriveChat.security
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) chatWindow.innerHTML = '';
            });
        }
    });
});

// Font resizing (global scope)
function resizeFont(sizeChange) {
    document.querySelectorAll('.message-text').forEach(msg => {
        let currentSize = parseFloat(window.getComputedStyle(msg).fontSize);
        msg.style.fontSize = (currentSize + sizeChange) + 'px';
    });
}
