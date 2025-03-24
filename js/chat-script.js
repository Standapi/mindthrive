document.addEventListener("DOMContentLoaded", function () {
    const chatWindow = document.getElementById("chat-window");
    const loadingIndicator = document.getElementById("chat-loading-indicator");
    const userInput = document.getElementById("user-input");
    const sendBtn = document.getElementById("send-btn");
    const menuBtn = document.getElementById("menu-toggle");
    const settingsToggle = document.getElementById("settings-toggle");
    const settingsMenu = document.getElementById("settings-menu");
    const typingIndicator = document.getElementById("typing-indicator");

    let messageLimit = { used: 0, max: 0 };
    let loadedMessageCount = 0;
    let allMessagesLoaded = false;
    let isLoadingHistory = false;
    let firstLoadDone = false;

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
    


    // ---------------------------
    // Usage UI
    // ---------------------------
    function updateUsageUI() {
        const counter = document.getElementById("usage-counter");
        if (!counter) return;

        const { used, max } = messageLimit;
        const percent = used / max;

        counter.textContent = `${used} / ${max} messages used today`;

        counter.classList.remove("low", "medium", "high");
        if (percent < 0.5) counter.classList.add("low");
        else if (percent < 1) counter.classList.add("medium");
        else counter.classList.add("high");
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

    // ---------------------------
    // Load History
    // ---------------------------
    function loadChatHistory(offset = 0, prepend = false) {
        return fetch(mindthriveChat.ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "fetch_chat_history",
                security: mindthriveChat.security,
                offset: offset
            })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.data.history)) return;

                const history = data.data.history;
                if (history.length < 20) allMessagesLoaded = true;

                const fragment = document.createDocumentFragment();

                history.forEach(msg => {
                    if (msg.message_text) {
                        const user = document.createElement("div");
                        user.classList.add("message", "user-message");
                        user.innerHTML = `<div class="message-text">${msg.message_text}</div>`;
                        fragment.appendChild(user);
                    }
                    if (msg.ai_response) {
                        const ai = document.createElement("div");
                        ai.classList.add("message", "ai-message");
                        ai.innerHTML = `<div class="message-text">${marked.parse(msg.ai_response)}</div>`;
                        fragment.appendChild(ai);
                    }
                });

                if (prepend) {
                    chatWindow.prepend(fragment);
                } else {
                    chatWindow.appendChild(fragment);
                }

                loadedMessageCount += history.length;
            });
    }

    // Initial load
    loadChatHistory(0, false).then(() => {
        // Double requestAnimationFrame ensures layout is flushed
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                chatWindow.scrollTop = chatWindow.scrollHeight;
                firstLoadDone = true;
            });
        });
    });
    
    

    // Scroll pagination
    chatWindow.addEventListener("scroll", () => {
        if (firstLoadDone && chatWindow.scrollTop < 50 && !allMessagesLoaded && !isLoadingHistory) {
            isLoadingHistory = true;
            loadingIndicator.classList.remove("hidden");

            const prevHeight = chatWindow.scrollHeight;

            loadChatHistory(loadedMessageCount, true).then(() => {
                isLoadingHistory = false;
                loadingIndicator.classList.add("hidden");

                const newHeight = chatWindow.scrollHeight;
                chatWindow.scrollTop += (newHeight - prevHeight);
            });
        }
    });

    // ---------------------------
    // Send Message
    // ---------------------------
    function sendMessage() {
        if (messageLimit.used >= messageLimit.max) {
            alert("You've reached your daily message limit.");
            return;
        }
        messageLimit.used++;
        updateUsageUI();

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

        typingIndicator.style.display = "block";

        const eventSource = new EventSource(`${mindthriveChat.ajaxurl}?action=mindthrive_chat_stream&message=${encodeURIComponent(message)}&security=${mindthriveChat.security}`);
        let markdownBuffer = '';

        eventSource.onmessage = (e) => {
            if (e.data === '[DONE]') {
                typingIndicator.style.display = 'none';
                eventSource.close();
                sendBtn.disabled = false;

                textSpan.innerHTML = marked.parse(markdownBuffer);
                aiMessageDiv.scrollIntoView({ behavior: 'smooth' });
                markdownBuffer = '';
                return;
            }

            try {
                const json = JSON.parse(e.data);
                if (json.content) {
                    markdownBuffer += json.content;
                    textSpan.textContent += json.content;
                    textSpan.scrollIntoView({ behavior: 'auto' });
                }
            } catch (err) {
                console.error("Streaming error:", err, e.data);
            }
        };

        eventSource.onerror = (err) => {
            console.error("Streaming error:", err);
            eventSource.close();
            sendBtn.disabled = false;
            typingIndicator.style.display = "none";
            appendMessage("An error occurred during streaming.", "ai");
        };
    }

    // ---------------------------
    // UI Events
    // ---------------------------
    sendBtn.addEventListener("click", sendMessage);
    userInput.addEventListener("keypress", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            sendMessage();
        }
    });

    menuBtn?.addEventListener("click", () => {
        if (window.innerWidth < 768 && typeof elementorProFrontend !== "undefined") {
            elementorProFrontend.modules?.popup?.showPopup({ id: 55 });
        } else {
            window.location.href = "/menu";
        }
    });

    settingsToggle.addEventListener("click", (e) => {
        e.stopPropagation();
        settingsMenu.classList.toggle("hidden");
    });

    document.addEventListener("click", (event) => {
        if (!settingsMenu.contains(event.target) && event.target !== settingsToggle) {
            settingsMenu.classList.add("hidden");
        }
    });

    // Clear chat
    document.getElementById("clear-chat-btn").addEventListener("click", () => {
        if (confirm("Are you sure you want to clear the chat history?")) {
            fetch(mindthriveChat.ajaxurl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "clear_chat_history",
                    security: mindthriveChat.security
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        chatWindow.innerHTML = "";
                        loadedMessageCount = 0;
                        allMessagesLoaded = false;
                        loadChatHistory(0, false);
                    }
                });
        }
    });

    fetchMessageUsage();
});

// Font resizing
function resizeFont(sizeChange) {
    document.querySelectorAll('.message-text').forEach(msg => {
        let currentSize = parseFloat(window.getComputedStyle(msg).fontSize);
        msg.style.fontSize = (currentSize + sizeChange) + 'px';
    });
}
