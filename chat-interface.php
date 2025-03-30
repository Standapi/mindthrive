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
                <button type="button" class="menu-btn" id="menu-toggle">☰</button>

                <div id="menu-dropdown" class="menu-dropdown hidden">
  <ul>
    <li><a href="/dashboard">Dashboard</a></li>
    <li><a href="/profile">My Profile</a></li>
    <li><a href="/upgrade">Upgrade</a></li>
    <li><a href="/logout">Logout</a></li>
  </ul>
</div>

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


    <div id="plan-popup" class="plan-popup hidden">
  <div class="plan-popup-overlay"></div>
  <div class="plan-popup-content">
    <button class="popup-close-btn" id="close-plan-popup">✖</button>
    <span class="badge">UPGRADE</span>
    <h2 class="section-title">Choose the right plan for your journey</h2>
    <p class="subheading-center">You've reached your daily limit. Unlock more support by upgrading your plan:</p>

    <div class="pricing-grid">

      <!-- Plan: Empower -->
      <div class="pricing-card featured">
        <div class="most-popular">Most Popular</div>
        <h2>Empower</h2>
        <p class="price">$14.99 <span>/month</span></p>
        <ul class="pricing-features">
          <li>🔄 Unlimited messages</li>
          <li>⏱️ Faster response time</li>
          <li>🧘 Emotional check-ins</li>
          <li>🤖 Superior AI guidance</li>
        </ul>
        <a href="/subscribe/empower" class="btn btn-primary">Choose Empower</a>
      </div>

      <!-- Plan: Heal -->
      <div class="pricing-card">
        <h2>Heal</h2>
        <p class="price">$29.99 <span>/month</span></p>
        <ul class="pricing-features">
          <li>🔓 Unlimited messages</li>
          <li>📊 Emotional analytics</li>
          <li>🧠 Advanced insight tracking</li>
          <li>🤖 Best AI guidance</li>
        </ul>
        <a href="/subscribe/heal" class="btn btn-secondary">Choose Heal</a>
      </div>

    </div>
  </div>
</div>

</div>
<!-- Usage Counter -->



<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>