<div class="chat-container">
    <div class="chat-box">
        <!-- Chat Header -->
        <div class="chat-header">
            <h2 class="chat-logo">
                <img src="http://mindthrive.me/wp-content/uploads/2025/03/logo-mindthrive-white.webp" alt="MindThrive">
            </h2>
            <div class="chat-controls">
                
                <div class="chat-settings">
                    <button id="settings-toggle" class="settings-btn">
                        <span class="material-symbols-outlined">settings</span>
                    </button>
                    <div class="settings-menu hidden" id="settings-menu">
                        <div class="dark-toggle-container">
                        <span><span class="material-symbols-outlined">dark_mode</span> Dark Mode</span>

                            <label class="switch">
                                <input type="checkbox" id="dark-toggle" />
                                <span class="slider"></span>
                            </label>
                        </div>

                        <button id="clear-chat-btn">Clear Chat</button>
                        <div class="font-controls">
                            <span>Font Size:</span>
                            <button onclick="resizeFont(1)">A+</button>
                            <button onclick="resizeFont(-1)">A-</button>
                        </div>
                    </div>
                </div>
                <button class="menu-btn" id="menu-toggle">☰</button>
            </div>
        </div>



        <!-- Chat messages -->
        <div class="chat-window" id="chat-window">
            <!-- messages go here -->
            <div id="chat-loading-indicator" class="chat-loading-indicator hidden">
                Loading previous messages...
            </div>



            <!-- Initial AI Message SHOULD be here -->
            <div class="message ai-message">
                <div class="message-text">
                    Hey! I'm here to listen, ask questions, and help you sort through your thoughts.
                </div>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="chat-input">
            <input type="text" id="user-input" placeholder="Type your message...">
            <button id="send-btn">➤</button>
        </div>
        <div id="usage-counter" class="usage-bar">
            Loading usage...
        </div>
    </div>
</div>
<!-- Usage Counter -->



<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>