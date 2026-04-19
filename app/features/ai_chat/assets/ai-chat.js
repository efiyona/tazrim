(function () {
  "use strict";

  const baseUrlRaw = window.AI_CHAT_BASE_URL || "/";
  const baseUrl = baseUrlRaw.endsWith("/") ? baseUrlRaw : baseUrlRaw + "/";
  const apiBase = baseUrl + "app/features/ai_chat/api/";

  const state = {
    open: false,
    activeChatId: null,
    currentChatTitle: "",
    topic: "system",
    topicLocked: false,
    draftNeedsTopic: true,
    sending: false,
    historyDrawerOpen: false,
    /** מטמון הודעות לפי chat_id — לא מריצים get_chat מחדש בכל פתיחת מודאל */
    chatPayloadCache: {},
  };

  function qs(id) {
    return document.getElementById(id);
  }

  function assistantDisplayName() {
    if (typeof window.AI_CHAT_ASSISTANT_NAME === "string" && window.AI_CHAT_ASSISTANT_NAME.trim() !== "") {
      return window.AI_CHAT_ASSISTANT_NAME.trim();
    }
    const modal = qs("aiChatModal");
    const fromDom = modal && modal.getAttribute("data-ai-chat-assistant");
    if (fromDom && fromDom.trim() !== "") {
      return fromDom.trim();
    }
    return "התזרים החכם";
  }

  function userInitials() {
    if (typeof window.AI_CHAT_USER_INITIALS === "string" && window.AI_CHAT_USER_INITIALS.trim() !== "") {
      return window.AI_CHAT_USER_INITIALS.trim();
    }
    const modal = qs("aiChatModal");
    const fromDom = modal && modal.getAttribute("data-ai-chat-user-initials");
    if (fromDom && fromDom.trim() !== "") {
      return fromDom.trim();
    }
    return "מנ";
  }

  function messageAvatarHtml(role) {
    if (role === "user") {
      return (
        '<div class="ai-chat-message-avatar ai-chat-message-avatar--user" aria-hidden="true">' +
        '<span class="ai-chat-message-avatar-label">' + escapeHtml(userInitials()) + "</span>" +
        "</div>"
      );
    }
    return (
      '<div class="ai-chat-message-avatar ai-chat-message-avatar--assistant" aria-hidden="true">' +
      '<span class="ai-chat-message-avatar-label">AI</span>' +
      "</div>"
    );
  }

  function isMobileLayout() {
    return window.matchMedia("(max-width: 980px)").matches;
  }

  function setHistoryDrawerOpen(open) {
    const layout = document.querySelector(".ai-chat-layout");
    if (!layout) return;
    const shouldOpen = !!open && isMobileLayout();
    state.historyDrawerOpen = shouldOpen;
    layout.classList.toggle("ai-chat-layout--history-open", shouldOpen);
  }

  function escapeHtml(str) {
    return (str || "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  /** Markdown מצומצם אחרי escape: **מודגש** → <strong> (בטוח לתוכן שכבר escaped) */
  function markdownBoldToHtml(escapedPlain) {
    const parts = escapedPlain.split("**");
    if (parts.length < 2) return escapedPlain;
    let out = "";
    for (let i = 0; i < parts.length; i++) {
      if (i % 2 === 0) out += parts[i];
      else out += "<strong>" + parts[i] + "</strong>";
    }
    return out;
  }

  function assistantPlainSliceToHtml(slice) {
    return markdownBoldToHtml(escapeHtml(slice)).replace(/\n/g, "<br>");
  }

  /** נתיב יחסי מהשורש (למשל /pages/x.php) או URL מלא */
  function resolveSiteHref(pathOrUrl) {
    const raw = (pathOrUrl || "").trim();
    if (!raw) return baseUrlRaw.endsWith("/") ? baseUrlRaw : baseUrlRaw + "/";
    if (/^https?:\/\//i.test(raw)) return raw;
    const path = raw.startsWith("/") ? raw.slice(1) : raw;
    return baseUrl + path;
  }

  /**
   * תשובת עוזר: טקסט + מקומי [[PAGE:/path|label]] -> כפתורי קישור
   */
  function formatAssistantHtml(raw) {
    const text = raw || "";
    const pattern = /\[\[PAGE:([^\]|]+)\|([^\]]+)\]\]/g;
    let html = "";
    let lastIndex = 0;
    let m;
    while ((m = pattern.exec(text)) !== null) {
      html += assistantPlainSliceToHtml(text.slice(lastIndex, m.index));
      const path = (m[1] || "").trim();
      const label = (m[2] || "").trim();
      const href = resolveSiteHref(path);
      html +=
        '<a class="ai-chat-page-link" href="' +
        escapeHtml(href) +
        '" target="_self" rel="noopener">' +
        '<i class="fa-solid fa-arrow-up-left-from-bracket" aria-hidden="true"></i> ' +
        escapeHtml(label) +
        "</a>";
      lastIndex = m.index + m[0].length;
    }
    html += assistantPlainSliceToHtml(text.slice(lastIndex));
    return html;
  }

  function formatUserHtml(raw) {
    return escapeHtml(raw || "").replace(/\n/g, "<br>");
  }

  function safeParseJson(text, fallback) {
    try {
      const parsed = JSON.parse(text || "");
      return parsed == null ? fallback : parsed;
    } catch (err) {
      return fallback;
    }
  }

  async function fetchJson(url, options) {
    const res = await fetch(url, options);
    const data = await res.json();
    if (!res.ok || data.status === "error") {
      throw new Error(data.message || "request_failed");
    }
    return data;
  }

  function setSending(v) {
    state.sending = v;
    const btn = qs("aiChatSendBtn");
    if (!btn) return;
    btn.disabled = v;
    btn.innerHTML = v ? '<i class="fa-solid fa-spinner fa-spin"></i>' : '<i class="fa-solid fa-paper-plane"></i>';
  }

  function typingIndicatorHtml() {
    return (
      '<span class="ai-chat-typing" aria-hidden="true">' +
      '<span class="ai-chat-typing-dot"></span>' +
      '<span class="ai-chat-typing-dot"></span>' +
      '<span class="ai-chat-typing-dot"></span>' +
      "</span>"
    );
  }

  function normalizeChatTitle(raw) {
    const clean = (raw || "").replace(/\s+/g, " ").trim();
    return clean ? clean.slice(0, 70) : "";
  }

  function setCurrentChatTitle(title) {
    const titleEl = qs("aiChatCurrentTitle");
    if (!titleEl) return;
    const safeTitle = normalizeChatTitle(title);
    state.currentChatTitle = safeTitle;
    titleEl.textContent = safeTitle;
    titleEl.classList.toggle("ai-chat-chat-title--hidden", safeTitle === "");
  }

  function topicGateHtml() {
    return (
      '<div class="ai-chat-topic-gate">' +
      '<div class="ai-chat-topic-gate-panel">' +
      '<p class="ai-chat-topic-gate-kicker">לפני שנתחיל</p>' +
      '<p class="ai-chat-topic-gate-title">מה בא לך לשאול?</p>' +
      '<p class="ai-chat-topic-gate-sub">בוחרים פעם אחת — ואז כל השיחה באותו כיוון. רוצים משהו אחר? תמיד אפשר להתחיל שיחה חדשה.</p>' +
      '<div class="ai-chat-topic-cards">' +
      '<button type="button" class="ai-chat-topic-card" data-topic="financial">' +
      '<span class="ai-chat-topic-card-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>' +
      "<strong>על תזרים וכסף</strong>" +
      '<span class="ai-chat-topic-card-desc">יתרה, הכנסות והוצאות — בעיקר מהחודשים האחרונים.</span>' +
      "</button>" +
      '<button type="button" class="ai-chat-topic-card" data-topic="system">' +
      '<span class="ai-chat-topic-card-icon"><i class="fa-regular fa-compass" aria-hidden="true"></i></span>' +
      "<strong>על המערכת ואיך משתמשים</strong>" +
      '<span class="ai-chat-topic-card-desc">איפה כל מסך ואיך עושים פעולה — בלי לשלוח נתונים פיננסיים.</span>' +
      "</button>" +
      "</div></div></div>"
    );
  }

  function setComposerVisible(visible) {
    const composer = qs("aiChatComposer");
    if (!composer) return;
    composer.classList.toggle("ai-chat-composer--hidden", !visible);
    composer.setAttribute("aria-hidden", visible ? "false" : "true");
  }

  function renderTopicStrip() {
    const strip = qs("aiChatTopicStrip");
    if (!strip) return;
    strip.querySelectorAll("[data-topic]").forEach((btn) => {
      const t = btn.getAttribute("data-topic");
      const active = state.topic === t;
      const hideNonSelected = state.topicLocked && !active;
      btn.classList.toggle("active", active);
      btn.classList.toggle("ai-chat-topic-chip-hidden", hideNonSelected);
      btn.disabled = state.topicLocked;
      btn.setAttribute("aria-pressed", active ? "true" : "false");
      btn.setAttribute("aria-hidden", hideNonSelected ? "true" : "false");
    });
  }

  function selectTopic(topic) {
    state.topic = topic === "financial" ? "financial" : "system";
    state.topicLocked = true;
    state.draftNeedsTopic = false;
    const messagesEl = qs("aiChatMessages");
    if (messagesEl) {
      messagesEl.innerHTML = '<div class="ai-chat-empty">בשמחה — שאלו כאן.</div>';
    }
    setCurrentChatTitle("");
    renderTopicStrip();
    setComposerVisible(true);
    const input = qs("aiChatInput");
    if (input) input.focus();
  }

  function addMessageBubble(role, text, opts = {}) {
    const wrap = qs("aiChatMessages");
    if (!wrap) return null;
    const cls = role === "user" ? "user" : "assistant";
    const metaLabel = role === "user" ? "אתה" : assistantDisplayName();
    const idAttr = opts.id ? ` data-msg-id="${opts.id}"` : "";
    let innerContent;
    if (role === "assistant" && opts.loading) {
      innerContent = '<div class="ai-chat-bubble-inner ai-chat-bubble-inner--typing">' + typingIndicatorHtml() + "</div>";
    } else if (role === "assistant") {
      innerContent = '<div class="ai-chat-bubble-inner">' + formatAssistantHtml(text) + "</div>";
    } else {
      innerContent = '<div class="ai-chat-bubble-inner">' + formatUserHtml(text) + "</div>";
    }
    const html =
      `<article class="ai-chat-bubble-row ai-chat-bubble-row--${cls}">` +
      messageAvatarHtml(role) +
      `<section class="ai-chat-bubble ${cls}"${idAttr}>` +
      `<div class="ai-chat-bubble-meta">${escapeHtml(metaLabel)}</div>` +
      innerContent +
      `</section>` +
      `</article>`;
    wrap.insertAdjacentHTML("beforeend", html);
    wrap.scrollTop = wrap.scrollHeight;
    return wrap.lastElementChild ? wrap.lastElementChild.querySelector(".ai-chat-bubble") : null;
  }

  function updateBubbleStreamingAssistant(el, text, opts = {}) {
    if (!el) return;
    let inner = el.querySelector(".ai-chat-bubble-inner");
    if (!inner) return;
    el.classList.remove("ai-chat-bubble--thinking");
    if (opts.clearThinking) {
      clearThinkingBanner(el);
    }
    inner.classList.remove("ai-chat-bubble-inner--typing");
    inner.innerHTML = escapeHtml(text).replace(/\n/g, "<br>");
    const wrap = qs("aiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  function finalizeAssistantBubble(el, fullText, opts = {}) {
    if (!el) return;
    const inner = el.querySelector(".ai-chat-bubble-inner");
    if (!inner) return;
    el.classList.remove("ai-chat-bubble--thinking");
    inner.classList.remove("ai-chat-bubble-inner--typing");
    inner.innerHTML = formatAssistantHtml(fullText);
    el.querySelectorAll(".ai-chat-deep-footnote").forEach((n) => n.remove());
    if (opts.deepPass) {
      const fn = document.createElement("div");
      fn.className = "ai-chat-deep-footnote";
      fn.setAttribute("role", "note");
      fn.innerHTML =
        '<i class="fa-solid fa-brain" aria-hidden="true"></i> ' +
        escapeHtml("התשובה נוסחה במצב חשיבה מתקדמת");
      el.appendChild(fn);
    }
    const wrap = qs("aiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  /** מחווה בזמן המתנה לשלב תשובה מעמיקה (לפני סטרים של הטקסט) */
  function showThinkingBanner(bubbleEl, hint) {
    const inner = bubbleEl && bubbleEl.querySelector(".ai-chat-bubble-inner");
    if (!inner) return;
    bubbleEl.classList.add("ai-chat-bubble--thinking");
    const h = (hint || "").trim();
    const hintBlock = h
      ? '<span class="ai-chat-thinking-hint">' + escapeHtml(h) + "</span>"
      : "";
    inner.classList.remove("ai-chat-bubble-inner--typing");
    inner.innerHTML =
      '<div class="ai-chat-thinking-banner" role="status">' +
      '<span class="ai-chat-thinking-icon" aria-hidden="true"><i class="fa-solid fa-brain"></i></span>' +
      '<span class="ai-chat-thinking-title">חשיבה מתקדמת</span>' +
      hintBlock +
      '<span class="ai-chat-thinking-dots">' +
      '<span class="ai-chat-typing-dot"></span>' +
      '<span class="ai-chat-typing-dot"></span>' +
      '<span class="ai-chat-typing-dot"></span>' +
      "</span>" +
      "</div>";
    const wrap = qs("aiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  function clearThinkingBanner(bubbleEl) {
    const inner = bubbleEl && bubbleEl.querySelector(".ai-chat-bubble-inner");
    if (!inner) return;
    bubbleEl.classList.remove("ai-chat-bubble--thinking");
    if (inner.querySelector(".ai-chat-thinking-banner")) {
      inner.innerHTML = "";
    }
  }

  async function loadHistory() {
    const list = qs("aiChatHistoryList");
    if (!list) return;
    list.innerHTML = '<div class="ai-chat-history-loading"><i class="fa-solid fa-spinner fa-spin"></i> טוען...</div>';
    try {
      const data = await fetchJson(apiBase + "list_chats.php");
      if (!data.items || data.items.length === 0) {
        list.innerHTML = '<div class="ai-chat-history-empty">עדיין אין שיחות שמורות.</div>';
        return;
      }
      list.innerHTML = "";
      data.items.forEach((item) => {
        const title = escapeHtml(item.title || "שיחה");
        const isActive = Number(item.id) === Number(state.activeChatId);
        if (isActive) {
          setCurrentChatTitle(item.title || "");
        }
        const html = `<article class="ai-chat-history-item ${isActive ? "active" : ""}">
            <button type="button" class="ai-chat-history-open" data-chat-id="${item.id}">
              <strong>${title}</strong><span></span>
            </button>
            <button type="button" class="ai-chat-history-delete" data-delete-chat-id="${item.id}" aria-label="מחיקת שיחה" title="מחיקת שיחה">
              <i class="fa-regular fa-trash-can"></i>
            </button>
          </article>`;
        list.insertAdjacentHTML("beforeend", html);
      });
    } catch (err) {
      list.innerHTML = '<div class="ai-chat-history-empty">שגיאה בטעינת היסטוריה.</div>';
    }
  }

  function scopeToTopic(scope) {
    if (!scope || typeof scope !== "object") return "system";
    if (scope.topic === "financial") return "financial";
    return "system";
  }

  function invalidateChatCache(chatId) {
    const id = Number(chatId);
    if (id > 0 && state.chatPayloadCache[id]) {
      delete state.chatPayloadCache[id];
    }
  }

  /**
   * @param {number} chatId
   * @param {{ title?: string, messages: Array, scope_snapshot: string }} payload
   */
  function applyChatPayload(chatId, payload) {
    const messagesEl = qs("aiChatMessages");
    if (!messagesEl) return;
    state.activeChatId = Number(chatId);
    const scope = safeParseJson(payload.scope_snapshot || "{}", {});
    state.topic = scopeToTopic(scope);
    state.topicLocked = true;
    state.draftNeedsTopic = false;
    renderTopicStrip();
    messagesEl.innerHTML = "";
    setComposerVisible(true);
    const msgs = payload.messages || [];
    if (msgs.length === 0) {
      setCurrentChatTitle("");
      messagesEl.innerHTML = '<div class="ai-chat-empty">התחילו לשאול שאלה.</div>';
      return;
    }
    setCurrentChatTitle(payload.title || "");
    msgs.forEach((m) => {
      if (m.role === "assistant") {
        addMessageBubble("assistant", m.content, { loading: false });
      } else {
        addMessageBubble("user", m.content);
      }
    });
    /* אחרי החלפת DOM / חזרה ממטמון — ודא גלילה מלאה (WebKit לפעמים מחשב גובה מאוחר) */
    requestAnimationFrame(() => {
      messagesEl.scrollTop = messagesEl.scrollHeight;
      requestAnimationFrame(() => {
        messagesEl.scrollTop = messagesEl.scrollHeight;
      });
    });
  }

  /**
   * @param {number} chatId
   * @param {{ force?: boolean }} [opts] לחיצה מרשימה = force (תמיד שליפה מלאה)
   */
  async function openChat(chatId, opts) {
    const force = opts && opts.force;
    const id = Number(chatId);
    const messagesEl = qs("aiChatMessages");
    if (!messagesEl) return;

    if (!force && state.chatPayloadCache[id]) {
      applyChatPayload(id, state.chatPayloadCache[id]);
      await loadHistory();
      setHistoryDrawerOpen(false);
      return;
    }

    messagesEl.innerHTML = '<div class="ai-chat-empty"><i class="fa-solid fa-spinner fa-spin"></i> טוען שיחה...</div>';
    setComposerVisible(false);
    try {
      const data = await fetchJson(apiBase + "get_chat.php?chat_id=" + encodeURIComponent(id));
      state.chatPayloadCache[id] = {
        title: data.chat.title || "",
        messages: data.messages || [],
        scope_snapshot: data.chat.scope_snapshot || "{}",
      };
      applyChatPayload(id, state.chatPayloadCache[id]);
      await loadHistory();
      setHistoryDrawerOpen(false);
    } catch (err) {
      messagesEl.innerHTML = '<div class="ai-chat-empty">לא ניתן לטעון את השיחה.</div>';
      setComposerVisible(true);
    }
  }

  /** נתיב פתיחת מודאל: רשימת שיחות + טיוטה או שיחה ממטמון (בלי get_chat לכל פעם) */
  async function onModalOpen() {
    await loadHistory();
    if (!state.activeChatId) {
      startDraftChat();
      return;
    }
    const cached = state.chatPayloadCache[state.activeChatId];
    if (cached) {
      applyChatPayload(state.activeChatId, cached);
      return;
    }
    await openChat(state.activeChatId, { force: true });
  }

  function startDraftChat() {
    state.activeChatId = null;
    state.currentChatTitle = "";
    state.topic = "system";
    state.topicLocked = false;
    state.draftNeedsTopic = true;
    const messagesEl = qs("aiChatMessages");
    if (messagesEl) {
      messagesEl.innerHTML = topicGateHtml();
    }
    setCurrentChatTitle("");
    renderTopicStrip();
    setComposerVisible(false);
    setHistoryDrawerOpen(false);
  }

  async function deleteChat(chatId) {
    const id = Number(chatId);
    await fetchJson(apiBase + "delete_chat.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ chat_id: chatId }),
    });
    invalidateChatCache(id);
    if (Number(state.activeChatId) === id) {
      state.activeChatId = null;
      startDraftChat();
    }
    await loadHistory();
  }

  async function deleteAllChats() {
    await fetchJson(apiBase + "delete_all_chats.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: "{}",
    });
    state.chatPayloadCache = {};
    state.activeChatId = null;
    startDraftChat();
    await loadHistory();
  }

  async function handleSend(e) {
    e.preventDefault();
    if (state.sending) return;
    if (state.draftNeedsTopic) return;
    const input = qs("aiChatInput");
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;
    input.value = "";
    if (!state.currentChatTitle) {
      setCurrentChatTitle(text);
    }

    const userBubble = addMessageBubble("user", text);
    if (userBubble) userBubble.classList.add("pop-in");
    const assistantBubble = addMessageBubble("assistant", "", { loading: true });
    setSending(true);

    try {
      const streamPayload = {
        chat_id: state.activeChatId,
        message: text,
        scope: { topic: state.topic },
      };
      if (state.topic === "system") {
        try {
          streamPayload.page_context = {
            path: typeof window.location.pathname === "string" ? window.location.pathname : "",
            title: (document.title || "").slice(0, 240),
          };
        } catch (e) {
          /* ignore */
        }
      }
      const response = await fetch(apiBase + "stream_message.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(streamPayload),
      });

      if (!response.ok || !response.body) {
        throw new Error("stream_failed");
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = "";
      let assistantText = "";
      let streamHasDoneEvent = false;
      let streamDeepPass = false;

      function handleSseEvent(rawEvent) {
        const lines = rawEvent.split(/\r?\n/);
        let eventName = "message";
        const dataLines = [];
        lines.forEach((line) => {
          if (line.startsWith("event:")) eventName = line.slice(6).trim();
          else if (line.startsWith("data:")) dataLines.push(line.slice(5).replace(/^ /, ""));
        });
        const payloadText = dataLines.join("\n");
        if (!payloadText) return;
        const payload = safeParseJson(payloadText, null);
        if (!payload) return;

        if (eventName === "meta" && payload.chat_id) {
          state.activeChatId = Number(payload.chat_id);
          streamDeepPass = !!payload.deep_pass;
        } else if (eventName === "thinking") {
          showThinkingBanner(assistantBubble, payload.hint || "");
        } else if (eventName === "token") {
          const chunk = payload.text || "";
          const hasThinkingBanner =
            assistantBubble && assistantBubble.querySelector(".ai-chat-thinking-banner");
          assistantText += chunk;
          updateBubbleStreamingAssistant(assistantBubble, assistantText, {
            clearThinking: !!hasThinkingBanner,
          });
        } else if (eventName === "error") {
          streamDeepPass = false;
          assistantText = "אירעה שגיאה בקבלת תשובה.";
          updateBubbleStreamingAssistant(assistantBubble, assistantText, { clearThinking: true });
        } else if (eventName === "done") {
          streamHasDoneEvent = true;
          if (typeof payload.deep_pass === "boolean") {
            streamDeepPass = payload.deep_pass;
          }
        }
      }

      while (true) {
        const { done, value } = await reader.read();
        buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
        const chunks = buffer.split(/\r?\n\r?\n/);
        buffer = chunks.pop() || "";
        chunks.forEach(handleSseEvent);
        if (done) break;
      }

      if (buffer.trim() !== "") {
        handleSseEvent(buffer);
      }
      if (!streamHasDoneEvent && assistantText === "") {
        assistantText = "לא התקבלה תשובה מלאה. נסו שוב.";
      }
      finalizeAssistantBubble(assistantBubble, assistantText, { deepPass: streamDeepPass });
      invalidateChatCache(state.activeChatId);
      await loadHistory();
    } catch (err) {
      finalizeAssistantBubble(assistantBubble, "לא הצלחתי להשיב כרגע. נסו שוב בעוד רגע.");
    } finally {
      setSending(false);
    }
  }

  function toggleModal(open) {
    const modal = qs("aiChatModal");
    if (!modal) return;
    state.open = open;
    modal.classList.toggle("open", open);
    modal.setAttribute("aria-hidden", open ? "false" : "true");
    document.body.classList.toggle("no-scroll", open);
    if (!open) {
      setHistoryDrawerOpen(false);
    }
    if (open) {
      if (!state.draftNeedsTopic) {
        const input = qs("aiChatInput");
        if (input) input.focus();
      }
    }
  }

  function attachEvents() {
    const launcher = qs("aiChatLauncher");
    const closeBtn = qs("aiChatClose");
    const modal = qs("aiChatModal");
    const newBtn = qs("aiChatNewBtn");
    const deleteAllBtn = qs("aiChatDeleteAllBtn");
    const historyToggleBtn = qs("aiChatHistoryToggle");
    const historyCloseBtn = qs("aiChatHistoryClose");
    const historyOverlayBtn = qs("aiChatHistoryOverlay");
    const form = qs("aiChatForm");
    const input = qs("aiChatInput");
    const messages = qs("aiChatMessages");
    if (!launcher || !closeBtn || !modal || !newBtn || !form || !messages) return;

    launcher.addEventListener("click", async () => {
      toggleModal(true);
      await onModalOpen();
    });

    closeBtn.addEventListener("click", () => toggleModal(false));
    modal.addEventListener("click", (e) => {
      if (e.target && e.target.getAttribute("data-ai-chat-close") === "1") {
        toggleModal(false);
      }
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && state.open) toggleModal(false);
    });

    form.addEventListener("submit", handleSend);
    newBtn.addEventListener("click", () => {
      startDraftChat();
      loadHistory();
    });
    if (historyToggleBtn) {
      historyToggleBtn.addEventListener("click", () => {
        setHistoryDrawerOpen(!state.historyDrawerOpen);
      });
    }
    if (historyCloseBtn) {
      historyCloseBtn.addEventListener("click", () => setHistoryDrawerOpen(false));
    }
    if (historyOverlayBtn) {
      historyOverlayBtn.addEventListener("click", () => setHistoryDrawerOpen(false));
    }
    if (deleteAllBtn) {
      deleteAllBtn.addEventListener("click", () => {
        const removeAll = () => deleteAllChats().catch(() => {});
        if (typeof window.tazrimConfirm === "function") {
          window
            .tazrimConfirm({
              title: "מחיקת כל השיחות",
              message: "למחוק לצמיתות את כל השיחות שלך עם " + assistantDisplayName() + "?",
              confirmText: "כן, למחוק הכל",
              cancelText: "ביטול",
              danger: true,
            })
            .then((ok) => {
              if (ok) removeAll();
            });
        } else if (window.confirm("למחוק לצמיתות את כל השיחות שלך עם " + assistantDisplayName() + "?")) {
          removeAll();
        }
      });
    }

    messages.addEventListener("click", (e) => {
      const card = e.target.closest(".ai-chat-topic-card");
      if (!card || !state.draftNeedsTopic) return;
      const topic = card.getAttribute("data-topic");
      if (topic) selectTopic(topic);
    });

    if (input) {
      input.addEventListener("keydown", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          if (!state.sending && !state.draftNeedsTopic) form.requestSubmit();
        }
      });
    }

    const history = qs("aiChatHistoryList");
    history.addEventListener("click", (e) => {
      const deleteBtn = e.target.closest("[data-delete-chat-id]");
      if (deleteBtn) {
        const chatId = Number(deleteBtn.getAttribute("data-delete-chat-id"));
        if (!chatId) return;
        const removeChat = () => deleteChat(chatId).catch(() => {});
        if (typeof window.tazrimConfirm === "function") {
          window
            .tazrimConfirm({
              title: "מחיקת שיחה",
              message: "למחוק את השיחה הזו לצמיתות?",
              confirmText: "כן, למחוק",
              cancelText: "ביטול",
              danger: true,
            })
            .then((ok) => {
              if (ok) removeChat();
            });
        } else if (window.confirm("למחוק את השיחה הזו לצמיתות?")) {
          removeChat();
        }
        return;
      }

      const openBtn = e.target.closest("[data-chat-id]");
      if (!openBtn) return;
      openChat(Number(openBtn.getAttribute("data-chat-id")), { force: true });
    });

    window.addEventListener("resize", () => {
      if (!isMobileLayout()) {
        setHistoryDrawerOpen(false);
      }
    });
  }

  window.AIChatBootstrap = function (initialOpen) {
    if (typeof window.__aiChatDetachEarlyLauncher === "function") {
      window.__aiChatDetachEarlyLauncher();
    }
    if (!window.__aiChatBooted) {
      window.__aiChatBooted = true;
      attachEvents();
    }
    if (initialOpen) {
      toggleModal(true);
      void onModalOpen();
    }
  };
})();
