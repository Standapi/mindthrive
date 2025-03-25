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
  let isProcessing = false;

  function appendMessage(text, sender) {
    if (!chatWindow) return;

    const messageDiv = document.createElement("div");
    messageDiv.classList.add(
      "message",
      sender === "user" ? "user-message" : "ai-message"
    );

    const textSpan = document.createElement("div");
    textSpan.classList.add("message-text");
    textSpan.innerHTML = text;

    messageDiv.appendChild(textSpan);
    chatWindow.appendChild(messageDiv);

    // ✅ Wait for layout to update, then scroll
    requestAnimationFrame(() => {
      messageDiv.scrollIntoView({ behavior: "smooth", block: "end" });
    });
  }

  // ---------------------------
  // Usage UI
  // ---------------------------
  function updateUsageUI() {
    const counter = document.getElementById("usage-counter");
    counter.classList.remove("limit-reached");

    if (messageLimit.unlimited) {
      counter.innerHTML = `💜 Unlimited messages with the Heal Plan`;
      return;
    }

    if (messageLimit.used >= messageLimit.max) {
      counter.classList.add("limit-reached");
      counter.innerHTML = `
        🔒 Daily Message limit reached — 
        <a href="/upgrade" style="color: inherit; text-decoration: underline; font-weight: 600;">Upgrade to continue</a>
      `;
    } else {
      counter.innerHTML = `Messages used: <strong>${messageLimit.used}</strong> / ${messageLimit.max}`;
    }
  }

  function fetchMessageUsage() {

    if (!messageLimit.unlimited && messageLimit.used >= messageLimit.max) {
      sendBtn.disabled = true;
      userInput.disabled = true;
      userInput.placeholder = "You've reached your daily message limit";
    
      const resetAt = data.data.reset_at;
      startCountdown(resetAt); // ⏳ Start timer
    
      return;
    }
    
    
    fetch(mindthriveChat.ajaxurl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        action: "get_message_usage",
        security: mindthriveChat.security,
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data || !data.data) {
          console.error("Missing usage data", data);
          return;
        }

        messageLimit = {
          used: data.data.used,
          max: data.data.max,
          unlimited: data.data.role === "heal_user", // 👈 this is key
        };

        const counter = document.getElementById("usage-counter");

        if (messageLimit.unlimited) {
          counter.classList.remove("limit-reached");
          counter.innerHTML = `💜 Unlimited messages with the Heal Plan`;
        } else {
          updateUsageUI();
        }
      });
  }
  function startCountdown(resetAtTimestamp) {
    const counter = document.getElementById("usage-counter");
  
    function updateTimer() {
      const now = Date.now() / 1000; // current UNIX time in seconds
      const remaining = Math.max(0, resetAtTimestamp - now);
  
      const hours = Math.floor(remaining / 3600);
      const minutes = Math.floor((remaining % 3600) / 60);
      const seconds = Math.floor(remaining % 60);
  
      counter.innerHTML = `
        ⏳ You can send messages again in ${hours}h ${minutes}m ${seconds}s 
        <a href="/upgrade" style="margin-left: 1rem; text-decoration: underline;">Upgrade</a>
      `;
  
      if (remaining > 0) {
        setTimeout(updateTimer, 1000);
      } else {
        // Auto-refresh the page or re-fetch usage
        location.reload(); // or fetchMessageUsage()
      }
    }
  
    updateTimer();
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
        offset: offset,
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success || !Array.isArray(data.data.history)) return;

        const history = data.data.history;
        if (history.length < 20) allMessagesLoaded = true;

        const fragment = document.createDocumentFragment();

        history.forEach((msg) => {
          if (msg.message_text) {
            const user = document.createElement("div");
            user.classList.add("message", "user-message");
            user.innerHTML = `<div class="message-text">${msg.message_text}</div>`;
            fragment.appendChild(user);
          }
          if (msg.ai_response) {
            const ai = document.createElement("div");
            ai.classList.add("message", "ai-message");
            ai.innerHTML = `<div class="message-text">${marked.parse(
              msg.ai_response
            )}</div>`;
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
  fetch(mindthriveChat.ajaxurl, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      action: "fetch_chat_history",
      security: mindthriveChat.security,
      offset: 0,
    }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (!data.success || !Array.isArray(data.data.history)) return;

      const total = data.data.total || 0;
      const latestPageOffset = Math.max(0, total - 20);

      // Now load the last page
      loadChatHistory(latestPageOffset, false).then(() => {
        requestAnimationFrame(() => {
          const lastMessage = chatWindow.lastElementChild;
          if (lastMessage) {
            lastMessage.scrollIntoView({ behavior: "instant" });
          } else {
            chatWindow.scrollTop = chatWindow.scrollHeight;
          }
          firstLoadDone = true;
        });
      });
    });

  // Scroll pagination
  chatWindow.addEventListener("scroll", () => {
    if (
      firstLoadDone &&
      chatWindow.scrollTop < 50 &&
      !allMessagesLoaded &&
      !isLoadingHistory
    ) {
      isLoadingHistory = true;
      loadingIndicator.classList.remove("hidden");

      const prevHeight = chatWindow.scrollHeight;

      loadChatHistory(loadedMessageCount, true).then(() => {
        isLoadingHistory = false;
        loadingIndicator.classList.add("hidden");

        const newHeight = chatWindow.scrollHeight;
        chatWindow.scrollTop += newHeight - prevHeight;
      });
    }
  });

  // ---------------------------
  // Send Message
  // ---------------------------
  function sendMessage() {
    if (isProcessing) return;

    if (!messageLimit.unlimited && messageLimit.used >= messageLimit.max) {
      
      sendBtn.disabled = true;
      userInput.disabled = true;
      userInput.blur(); // optional: remove cursor/focus

      return;
    }

    isProcessing = true; // 🔒 lock input

    if (!messageLimit.unlimited && messageLimit.used >= messageLimit.max) {
      const upgradePrompt = document.createElement("div");
      upgradePrompt.className = "usage-toast";
      upgradePrompt.innerHTML = `
                You've reached your daily message limit. 
                <a href="/upgrade" style="text-decoration: underline; font-weight: bold;">Upgrade your plan</a> to continue.
            `;
      document.body.appendChild(upgradePrompt);
      return;
    }

    if (!messageLimit.unlimited) {
      messageLimit.used++;
      updateUsageUI();
    }

    const message = userInput.value.trim();
    if (!message) return;

    appendMessage(message, "user");
    userInput.value = "";
    sendBtn.disabled = true;
    userInput.disabled = true; // ✅ disables the input field

    const aiMessageDiv = document.createElement("div");
    aiMessageDiv.classList.add("message", "ai-message");
    const textSpan = document.createElement("div");
    textSpan.classList.add("message-text");
    aiMessageDiv.appendChild(textSpan);
    chatWindow.appendChild(aiMessageDiv);

    typingIndicator.style.display = "block";

    const eventSource = new EventSource(
      `${
        mindthriveChat.ajaxurl
      }?action=mindthrive_chat_stream&message=${encodeURIComponent(
        message
      )}&security=${mindthriveChat.security}`
    );
    let markdownBuffer = "";

    eventSource.onmessage = (e) => {
      if (e.data === "[DONE]") {
        typingIndicator.style.display = "none";
        eventSource.close();
        sendBtn.disabled = false;
        userInput.disabled = false;
        userInput.focus(); // ✅ auto-focus so user can type again
        isProcessing = false;

        textSpan.innerHTML = marked.parse(markdownBuffer);
        aiMessageDiv.scrollIntoView({ behavior: "smooth" });
        markdownBuffer = "";
        return;
      }

      try {
        const json = JSON.parse(e.data);
        if (json.content) {
          markdownBuffer += json.content;
          textSpan.textContent += json.content;
          textSpan.scrollIntoView({ behavior: "auto" });
        }
      } catch (err) {
        console.error("Streaming error:", err, e.data);
      }
    };

    eventSource.onerror = (err) => {
      console.error("Streaming error:", err);
      eventSource.close();
      sendBtn.disabled = false;
      userInput.disabled = false;
      userInput.focus(); // ✅ give them a fresh start
      isProcessing = false;
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
    if (
      window.innerWidth < 768 &&
      typeof elementorProFrontend !== "undefined"
    ) {
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
    if (
      !settingsMenu.contains(event.target) &&
      event.target !== settingsToggle
    ) {
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
          security: mindthriveChat.security,
        }),
      })
        .then((res) => res.json())
        .then((data) => {
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
  document.querySelectorAll(".message-text").forEach((msg) => {
    let currentSize = parseFloat(window.getComputedStyle(msg).fontSize);
    msg.style.fontSize = currentSize + sizeChange + "px";
  });
}
