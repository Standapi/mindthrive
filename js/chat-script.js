document.addEventListener("DOMContentLoaded", function() {
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
        textSpan.textContent = text;

        messageDiv.appendChild(textSpan);
        chatWindow.appendChild(messageDiv);
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    /**
     * Fetch chat history from the server and display it.
     */
    function loadChatHistory() {
        fetch(mindthriveChat.ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "fetch_chat_history",
                security: mindthriveChat.security
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.history) {
                data.data.history.forEach(msg => {
                    appendMessage(msg.message_text, 'user');
                    appendMessage(msg.ai_response, 'ai');
                });
            } else {
                console.error("Could not load chat history:", data);
            }
        })
        .catch(error => {
            console.error("Error fetching chat history:", error);
        });
    }

    // Load history on startup
    loadChatHistory();

    /**
     * Sends the user's message to the server via AJAX and handles the streaming response.
     */
    function sendMessage() {
        const message = userInput.value.trim();
        if (!message) return;

        appendMessage(message, "user");
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

        eventSource.onmessage = (e) => {
            if (e.data === '[DONE]') {
                typingIndicator.style.display = 'none';
                eventSource.close();
                sendBtn.disabled = false;
                return;
            }
            try {
                const json = JSON.parse(e.data);
                if (json.content) {
                    textSpan.textContent += json.content;
                    chatWindow.scrollTop = chatWindow.scrollHeight;
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
