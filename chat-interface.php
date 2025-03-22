<div class="chat-container">
    <div class="chat-box">
        <!-- Chat Header -->
        <div class="chat-header">
        <h2 class="chat-logo">
            <img 
                src="http://mindthrive.me/wp-content/uploads/2025/03/logo-mindthrive-white.webp" 
                alt="MindThrive"
            >
        </h2>
        <div class="chat-controls">
            <button class="menu-btn" id="menu-toggle">☰</button>

            <div class="chat-settings">
                <button id="settings-toggle" class="settings-btn">⚙️</button>
                <div class="settings-menu hidden" id="settings-menu">
                    <button id="clear-chat-btn">Clear Chat</button>
                    <div class="font-controls">
                        <span>Font Size:</span>
                        <button onclick="resizeFont(1)">A+</button>
                        <button onclick="resizeFont(-1)">A-</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Chat Window -->
        <div class="chat-window" id="chat-window">
            <!-- Initial AI Message -->
            <div class="message ai-message">
                <div class="message-text">
                    Hey! I'm here to listen, ask questions, and help you sort through your thoughts.
                </div>
            </div>
        </div>
        <div id="typing-indicator" class="typing-indicator" style="display: none;">
    <span></span><span></span><span></span>
</div>

        <!-- Chat Input -->
        <div class="chat-input">
            <input type="text" id="user-input" placeholder="Type your message...">
            <button id="send-btn">➤</button>
        </div>
    </div>
</div>
