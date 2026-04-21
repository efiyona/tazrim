(function () {
  "use strict";

  const baseUrlRaw = window.AI_CHAT_BASE_URL || "/";
  const baseUrl = baseUrlRaw.endsWith("/") ? baseUrlRaw : baseUrlRaw + "/";
  const apiBase = baseUrl + "app/features/ai_chat/api/";

  const state = {
    open: false,
    activeChatId: null,
    currentChatTitle: "",
    sending: false,
    historyDrawerOpen: false,
    /** מטמון הודעות לפי chat_id — לא מריצים get_chat מחדש בכל פתיחת מודאל */
    chatPayloadCache: {},
    pendingQuestions: null,
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

  function cssAttrEscape(str) {
    const s = String(str || "");
    if (typeof CSS !== "undefined" && typeof CSS.escape === "function") {
      return CSS.escape(s);
    }
    return s.replace(/\\/g, "\\\\").replace(/"/g, '\\"');
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

  /** סנכרון עם `allowed_chat_pages.php` — גיבוי אם המשתנה הגלובלי חסר */
  const AI_CHAT_PAGE_ALLOWLIST_FALLBACK = [
    "/",
    "/index.php",
    "/pages/reports.php",
    "/pages/shopping.php",
    "/pages/welcome.php",
    "/pages/settings/user_profile.php",
    "/pages/settings/manage_home.php",
  ];

  function getPageAllowlist() {
    if (Array.isArray(window.AI_CHAT_PAGE_ALLOWLIST) && window.AI_CHAT_PAGE_ALLOWLIST.length) {
      return window.AI_CHAT_PAGE_ALLOWLIST.map((p) => String(p || "").trim()).filter(Boolean);
    }
    return AI_CHAT_PAGE_ALLOWLIST_FALLBACK.slice();
  }

  function normalizeInternalPagePath(raw) {
    const s = String(raw || "").trim();
    if (!s || /^https?:\/\//i.test(s)) return "";
    let path = s.startsWith("/") ? s : "/" + s;
    path = path.split("?")[0].split("#")[0];
    path = path.replace(/\/+$/, "") || "/";
    return path;
  }

  function pagePathIsAllowed(rawPath) {
    const n = normalizeInternalPagePath(rawPath);
    if (!n) return true;
    const list = getPageAllowlist();
    const lower = n.toLowerCase();
    for (let i = 0; i < list.length; i++) {
      if (String(list[i]).toLowerCase() === lower) return true;
    }
    if (n === "/" && list.indexOf("/index.php") !== -1) return true;
    if (n === "/index.php" && list.indexOf("/") !== -1) return true;
    return false;
  }

  function useRichLayoutForSlice(slice) {
    const s = slice || "";
    const nl = (s.match(/\n/g) || []).length;
    return s.length > 320 || nl >= 3 || /\n\s*[*\-•]/.test(s) || /^[ \t]*[*\-•]/m.test(s) || /^\s*\d+\.\s/m.test(s);
  }

  function assistantSliceRichHtml(slice) {
    const raw = (slice || "").trim();
    if (!raw) return "";
    const blocks = raw.split(/\n{2,}/);
    const out = [];
    for (let b = 0; b < blocks.length; b++) {
      const block = blocks[b].trim();
      if (!block) continue;
      const lines = block
        .split(/\r?\n/)
        .map((l) => l.trim())
        .filter(Boolean);
      if (!lines.length) continue;
      const bulletRe = /^(?:[*\-•]|\d+\.)\s+/;
      const allBullet = lines.length >= 1 && lines.every((l) => bulletRe.test(l));
      if (lines.length === 1) {
        const one = lines[0];
        if (/^\*\*.+\*\*$/.test(one)) {
          const inner = one.replace(/^\*\*|\*\*$/g, "");
          out.push('<h4 class="ai-chat-rich-heading">' + markdownBoldToHtml(escapeHtml(inner)) + "</h4>");
          continue;
        }
      }
      if (allBullet) {
        const items = lines.map((l) => l.replace(bulletRe, "").trim());
        out.push(
          '<ul class="ai-chat-rich-ul">' +
            items.map((item) => "<li>" + markdownBoldToHtml(escapeHtml(item)) + "</li>").join("") +
            "</ul>"
        );
        continue;
      }
      out.push(
        '<p class="ai-chat-rich-p">' + lines.map((l) => markdownBoldToHtml(escapeHtml(l))).join("<br>") + "</p>"
      );
    }
    return '<div class="ai-chat-rich-stack">' + out.join("") + "</div>";
  }

  function assistantSliceToDisplayHtml(slice) {
    return useRichLayoutForSlice(slice) ? assistantSliceRichHtml(slice) : assistantPlainSliceToHtml(slice);
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
  /** מסיר בלוקים טכניים שנשמרים בהיסטוריה — לא להציג למשתמש */
  function stripDisplayedTransportBlocks(t) {
    return (t || "")
      .replace(/\[\[ACTION_PROPOSED\]\][\s\S]*?\[\[\/ACTION_PROPOSED\]\]/g, "")
      .replace(/\[\[ACTION\]\][\s\S]*?\[\[\/ACTION\]\]/g, "")
      .replace(/\[\[QUESTIONS\]\][\s\S]*?\[\[\/QUESTIONS\]\]/g, "")
      .replace(/\[\[QUESTIONS_ASKED\]\]/g, "")
      .replace(/\[\[QUESTIONS_CONTEXT\]\][\s\S]*?\[\[\/QUESTIONS_CONTEXT\]\]/g, "")
      .trim();
  }

  function formatAssistantHtmlForDisplay(raw) {
    return formatAssistantHtml(stripDisplayedTransportBlocks(raw));
  }

  function formatAssistantHtml(raw) {
    const text = raw || "";
    const pattern = /\[\[PAGE:([^\]|]+)\|([^\]]+)\]\]/g;
    let html = "";
    let lastIndex = 0;
    let usedRich = false;
    let m;
    while ((m = pattern.exec(text)) !== null) {
      const mid = text.slice(lastIndex, m.index);
      if (useRichLayoutForSlice(mid)) usedRich = true;
      html += assistantSliceToDisplayHtml(mid);
      const path = (m[1] || "").trim();
      const label = (m[2] || "").trim();
      const allowed = pagePathIsAllowed(path);
      if (allowed) {
        const href = resolveSiteHref(path);
        html +=
          '<a class="ai-chat-page-link" href="' +
          escapeHtml(href) +
          '" target="_self" rel="noopener">' +
          '<i class="fa-solid fa-arrow-up-left-from-bracket" aria-hidden="true"></i> ' +
          escapeHtml(label) +
          "</a>";
      } else {
        html +=
          '<span class="ai-chat-page-fallback" title="הנתיב לא זמין כקישור מהמערכת">' +
          '<i class="fa-solid fa-link-slash" aria-hidden="true"></i> ' +
          escapeHtml(label) +
          "</span>";
      }
      lastIndex = m.index + m[0].length;
    }
    const tail = text.slice(lastIndex);
    if (useRichLayoutForSlice(tail)) usedRich = true;
    html += assistantSliceToDisplayHtml(tail);
    if (usedRich || html.indexOf("ai-chat-rich-stack") !== -1) {
      html = '<div class="ai-chat-rich-root">' + html + "</div>";
    }
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

  function setComposerVisible(visible) {
    const composer = qs("aiChatComposer");
    if (!composer) return;
    composer.classList.toggle("ai-chat-composer--hidden", !visible);
    composer.setAttribute("aria-hidden", visible ? "false" : "true");
  }

  function buildCurrentViewPayload() {
    const out = {};
    try {
      out.path = typeof window.location.pathname === "string" ? window.location.pathname : "";
      out.title = (document.title || "").slice(0, 240);
      const sp = new URLSearchParams(window.location.search || "");
      const m = parseInt(sp.get("m") || "", 10);
      const y = parseInt(sp.get("y") || "", 10);
      if (m >= 1 && m <= 12 && y >= 2000 && y <= 2100) {
        out.view_month = m;
        out.view_year = y;
      }
    } catch (e) {
      /* ignore */
    }
    return out;
  }

  function removeAiChatEphemeralPanels() {
    document.querySelectorAll(".ai-chat-ephemeral-panel").forEach((n) => n.remove());
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
      innerContent = '<div class="ai-chat-bubble-inner">' + formatAssistantHtmlForDisplay(text) + "</div>";
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
    inner.innerHTML = formatAssistantHtmlForDisplay(text);
    const wrap = qs("aiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  function finalizeAssistantBubble(el, fullText, opts = {}) {
    if (!el) return;
    const inner = el.querySelector(".ai-chat-bubble-inner");
    if (!inner) return;
    el.classList.remove("ai-chat-bubble--thinking");
    inner.classList.remove("ai-chat-bubble-inner--typing");
    inner.innerHTML = formatAssistantHtmlForDisplay(fullText);
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
  function showThinkingBanner(bubbleEl, hint, opts) {
    const o = opts || {};
    const inner = bubbleEl && bubbleEl.querySelector(".ai-chat-bubble-inner");
    if (!inner) return;
    bubbleEl.classList.add("ai-chat-bubble--thinking");
    const h = (hint || "").trim();
    const titleText =
      (o.title && String(o.title).trim()) || "חשיבה מתקדמת";
    const hintBlock = h
      ? '<span class="ai-chat-thinking-hint">' + escapeHtml(h) + "</span>"
      : "";
    inner.classList.remove("ai-chat-bubble-inner--typing");
    inner.innerHTML =
      '<div class="ai-chat-thinking-banner" role="status">' +
      '<span class="ai-chat-thinking-icon" aria-hidden="true"><i class="fa-solid fa-brain"></i></span>' +
      '<span class="ai-chat-thinking-title">' +
      escapeHtml(titleText) +
      "</span>" +
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
    removeAiChatEphemeralPanels();
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
    removeAiChatEphemeralPanels();
    const messagesEl = qs("aiChatMessages");
    if (messagesEl) {
      messagesEl.innerHTML = '<div class="ai-chat-empty">שאלו כאן — המערכת תנתב את השאלה אוטומטית.</div>';
    }
    setCurrentChatTitle("");
    setComposerVisible(true);
    setHistoryDrawerOpen(false);
    const input = qs("aiChatInput");
    if (input) {
      input.value = "";
      input.focus();
    }
  }

  function summarizeActionProposal(p) {
    const kind = String(p.kind || p.action || "").toLowerCase();
    if (kind === "create_category") {
      const n = (p.name && String(p.name).trim()) || "קטגוריה חדשה";
      const hasTx = p.initial_transaction && typeof p.initial_transaction === "object";
      return hasTx ? "יצירת קטגוריה \"" + n + "\" ורישום פעולה ראשונה" : "יצירת קטגוריה \"" + n + "\"";
    }
    if (kind === "create_transaction") {
      const t = p.type === "income" ? "הכנסה" : "הוצאה";
      const amt = p.amount != null ? String(p.amount) : "";
      return "רישום " + t + (amt ? " בסך " + amt + " ₪" : "");
    }
    if (kind === "save_user_preference") {
      const k = p.pref_key || p.key || "";
      return "שמירת העדפה: " + k;
    }
    if (kind === "update_user_nickname") {
      return "עדכון כינוי במערכת";
    }
    return "פעולה במערכת";
  }

  function hitlDetailRow(label, valueHtml) {
    return (
      '<div class="ai-chat-hitl-row"><span class="ai-chat-hitl-k">' +
      escapeHtml(label) +
      '</span><span class="ai-chat-hitl-v">' +
      valueHtml +
      "</span></div>"
    );
  }

  function hitlActionDetailsHtml(payload) {
    const k = String(payload.kind || "").toLowerCase();
    if (k === "create_category") {
      let h = "";
      h += hitlDetailRow("שם", escapeHtml(String(payload.name || "")));
      h += hitlDetailRow("סוג", escapeHtml(payload.type === "income" ? "הכנסה" : "הוצאה"));
      if (payload.budget_limit != null && Number(payload.budget_limit) > 0) {
        h += hitlDetailRow("תקציב חודשי", escapeHtml(String(payload.budget_limit)) + " ₪");
      }
      const it = payload.initial_transaction;
      if (it && typeof it === "object") {
        h += hitlDetailRow("פעולה ראשונה", escapeHtml(String(it.description || "")));
        h += hitlDetailRow("סכום", escapeHtml(String(it.amount != null ? it.amount : "")) + " ₪");
        h += hitlDetailRow("תאריך", escapeHtml(String(it.transaction_date || "")));
      }
      return h;
    }
    if (k === "create_transaction") {
      let h = "";
      h += hitlDetailRow("סוג", escapeHtml(payload.type === "income" ? "הכנסה" : "הוצאה"));
      h += hitlDetailRow("סכום", escapeHtml(String(payload.amount != null ? payload.amount : "")) + " ₪");
      const catLabel =
        payload.category_display_name && String(payload.category_display_name).trim() !== ""
          ? String(payload.category_display_name).trim()
          : "קטגוריה מהמערכת";
      h += hitlDetailRow("קטגוריה", escapeHtml(catLabel));
      h += hitlDetailRow("תיאור", escapeHtml(String(payload.description || "")));
      h += hitlDetailRow("תאריך", escapeHtml(String(payload.transaction_date || "")));
      return h;
    }
    if (k === "save_user_preference") {
      return (
        hitlDetailRow("מפתח", escapeHtml(String(payload.pref_key || payload.key || ""))) +
        hitlDetailRow("ערך", escapeHtml(String(payload.pref_value || payload.value || "").slice(0, 400)))
      );
    }
    if (k === "update_user_nickname") {
      return hitlDetailRow("כינוי חדש", escapeHtml(String(payload.nickname || "")));
    }
    return hitlDetailRow("סוג", escapeHtml(k || "—"));
  }

  function mountHitlActionCard(assistantBubble, payload, preambleText) {
    const inner = assistantBubble && assistantBubble.querySelector(".ai-chat-bubble-inner");
    if (!inner) return;

    async function callExecute(accept) {
      const body = {
        proposal_id: payload.proposal_id,
        signature: payload.signature,
        chat_id: payload.chat_id,
        proposed_at: payload.proposed_at,
        accept: accept,
      };
      const res = await fetch(apiBase + "agent_execute.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok || data.status === "error") {
        throw new Error(data.message || "execute_failed");
      }
      return data;
    }

    const preamble = stripDisplayedTransportBlocks(preambleText || "").trim();
    const preHtml = preamble
      ? '<div class="ai-chat-hitl-preamble">' + formatAssistantHtml(preamble) + "</div>"
      : "";
    const card =
      '<div class="ai-chat-hitl-card" data-ai-user-hitl="1">' +
      '<div class="ai-chat-hitl-card-head"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> ' +
      escapeHtml("אישור פעולה במערכת") +
      "</div>" +
      '<div class="ai-chat-hitl-card-sub">' +
      escapeHtml(summarizeActionProposal(payload)) +
      "</div>" +
      '<div class="ai-chat-hitl-card-body">' +
      hitlActionDetailsHtml(payload) +
      "</div>" +
      '<div class="ai-chat-hitl-card-actions">' +
      '<button type="button" class="ai-chat-hitl-btn ai-chat-hitl-btn--ok" data-hitl="approve"><i class="fa-solid fa-check"></i> אשר ובצע</button>' +
      '<button type="button" class="ai-chat-hitl-btn ai-chat-hitl-btn--no" data-hitl="reject">ביטול</button>' +
      "</div></div>";

    inner.innerHTML = preHtml + card;

    const actionsEl = inner.querySelector(".ai-chat-hitl-card-actions");

    inner.querySelectorAll("[data-hitl]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const mode = btn.getAttribute("data-hitl");
        if (!actionsEl) return;
        actionsEl.querySelectorAll("button").forEach((b) => {
          b.disabled = true;
        });
        try {
          const data = await callExecute(mode === "approve");
          const ok = mode === "approve";
          const msg = ok ? data.message || "הפעולה בוצעה בהצלחה." : "ביטלת את ההצעה.";
          if (actionsEl) {
            actionsEl.innerHTML =
              '<div class="ai-chat-hitl-done ' +
              (ok ? "ai-chat-hitl-done--ok" : "ai-chat-hitl-done--muted") +
              '"><i class="fa-solid ' +
              (ok ? "fa-circle-check" : "fa-circle-xmark") +
              '" aria-hidden="true"></i> ' +
              escapeHtml(msg) +
              "</div>";
          }
          invalidateChatCache(Number(payload.chat_id));
          await loadHistory();
        } catch (err) {
          if (actionsEl) {
            actionsEl.innerHTML =
              '<div class="ai-chat-hitl-done ai-chat-hitl-done--err"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> ' +
              escapeHtml("לא הצלחנו לבצע. נסו שוב.") +
              "</div>";
          }
        }
        const w = qs("aiChatMessages");
        if (w) w.scrollTop = w.scrollHeight;
      });
    });
    const w = qs("aiChatMessages");
    if (w) w.scrollTop = w.scrollHeight;
  }

  function renderQuestionsUI(questions) {
    const wrap = qs("aiChatMessages");
    if (!wrap || !Array.isArray(questions) || questions.length === 0) return;
    removeAiChatEphemeralPanels();
    state.pendingQuestions = questions;

    let html = '<div class="ai-chat-ephemeral-panel ai-chat-questions-panel-v2" role="region" aria-label="שאלות הבהרה">';
    html += '<div class="ai-chat-questions-title-v2"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> צריך עוד קצת פרטים</div>';
    questions.forEach((q) => {
      const qid = escapeHtml(String((q && q.id) || ""));
      const qtext = escapeHtml((q && q.text) || "");
      html += '<div class="ai-chat-question-block-v2" data-qid="' + qid + '">';
      html += '<div class="ai-chat-question-text-v2">' + qtext + "</div>";
      const opts = Array.isArray(q && q.options) ? q.options : [];
      if (opts.length) {
        html += '<div class="ai-chat-question-options-v2">';
        opts.forEach((opt) => {
          const o = String(opt);
          html +=
            '<button type="button" class="ai-chat-question-option-v2" data-qid="' +
            qid +
            '" data-value="' +
            escapeHtml(o) +
            '">' +
            escapeHtml(o) +
            "</button>";
        });
        html += "</div>";
      }
      html +=
        '<input type="text" class="ai-chat-question-input-v2" data-qid="' +
        qid +
        '" placeholder="או כתבו כאן תשובה חופשית…" maxlength="800">';
      html += "</div>";
    });
    html +=
      '<button type="button" class="ai-chat-questions-submit-v2" id="aiChatQuestionsSubmitBtn"><i class="fa-solid fa-paper-plane"></i> שלח תשובות</button></div>';
    wrap.insertAdjacentHTML("beforeend", html);
    setComposerVisible(false);

    const panel = wrap.querySelector(".ai-chat-questions-panel-v2:last-of-type");
    if (!panel) return;

    panel.addEventListener("click", (e) => {
      const optBtn = e.target.closest(".ai-chat-question-option-v2");
      if (!optBtn) return;
      const qid = optBtn.getAttribute("data-qid");
      const value = optBtn.getAttribute("data-value") || "";
      const qEsc = cssAttrEscape(qid || "");
      const inputEl = panel.querySelector('.ai-chat-question-input-v2[data-qid="' + qEsc + '"]');
      if (inputEl) inputEl.value = value;
      const block = panel.querySelector('.ai-chat-question-block-v2[data-qid="' + qEsc + '"]');
      if (block) {
        block.querySelectorAll(".ai-chat-question-option-v2").forEach((b) => b.classList.remove("selected"));
        optBtn.classList.add("selected");
      }
    });

    const sub = panel.querySelector("#aiChatQuestionsSubmitBtn");
    if (sub) {
      sub.addEventListener("click", () => {
        const answers = [];
        let allAnswered = true;
        questions.forEach((q) => {
          const id = String((q && q.id) || "");
          const inputEl = panel.querySelector(
            '.ai-chat-question-input-v2[data-qid="' + cssAttrEscape(id) + '"]',
          );
          const val = inputEl ? inputEl.value.trim() : "";
          if (val === "") allAnswered = false;
          answers.push({ id: id, value: val });
        });
        if (!allAnswered) {
          const unanswered = answers.find((a) => a.value === "");
          if (unanswered) {
            const inputEl = panel.querySelector(
              '.ai-chat-question-input-v2[data-qid="' + cssAttrEscape(unanswered.id) + '"]',
            );
            if (inputEl) {
              inputEl.focus();
              inputEl.classList.add("ai-chat-question-input-v2--err");
              setTimeout(() => inputEl.classList.remove("ai-chat-question-input-v2--err"), 1500);
            }
          }
          return;
        }
        panel.remove();
        state.pendingQuestions = null;
        setComposerVisible(true);
        let answerText = "";
        answers.forEach((a) => {
          const q = questions.find((qq) => String(qq.id) === String(a.id));
          answerText += (q ? q.text : a.id) + ": " + a.value + "\n";
        });
        const input = qs("aiChatInput");
        const form = qs("aiChatForm");
        if (input && form) {
          input.value = answerText.trim();
          form.requestSubmit();
        }
      });
    }
    wrap.scrollTop = wrap.scrollHeight;
    requestAnimationFrame(() => {
      panel.scrollIntoView({ block: "nearest", behavior: "smooth" });
    });
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
    const input = qs("aiChatInput");
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;
    input.value = "";
    state.pendingQuestions = null;
    removeAiChatEphemeralPanels();
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
        current_view: buildCurrentViewPayload(),
      };
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
      let branchQuestions = null;
      let branchAction = null;

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
        } else if (eventName === "agent_step") {
          const title = (payload.label && String(payload.label).trim()) || "מעבדים";
          const h = (payload.hint && String(payload.hint)) || "";
          showThinkingBanner(assistantBubble, h, { title: title });
        } else if (eventName === "validating") {
          const h = (payload.hint && String(payload.hint)) || "";
          showThinkingBanner(assistantBubble, h, { title: "אימות פעולה" });
        } else if (eventName === "thinking") {
          showThinkingBanner(assistantBubble, payload.hint || "", {
            title: "חשיבה מתקדמת",
          });
        } else if (eventName === "token") {
          const chunk = payload.text || "";
          const hasThinkingBanner =
            assistantBubble && assistantBubble.querySelector(".ai-chat-thinking-banner");
          assistantText += chunk;
          updateBubbleStreamingAssistant(assistantBubble, assistantText, {
            clearThinking: !!hasThinkingBanner,
          });
        } else if (eventName === "agent_error") {
          streamHasDoneEvent = true;
          streamDeepPass = false;
          assistantText = (payload.message || "משהו השתבש. נסו שוב בעוד רגע.").trim();
          updateBubbleStreamingAssistant(assistantBubble, assistantText, { clearThinking: true });
        } else if (eventName === "error") {
          streamDeepPass = false;
          assistantText = "אירעה שגיאה בקבלת תשובה.";
          updateBubbleStreamingAssistant(assistantBubble, assistantText, { clearThinking: true });
        } else if (eventName === "questions") {
          branchQuestions = payload;
        } else if (eventName === "action") {
          branchAction = payload;
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
      if (!streamHasDoneEvent && assistantText === "" && !branchQuestions && !branchAction) {
        assistantText = "לא התקבלה תשובה מלאה. נסו שוב.";
      }
      if (branchQuestions) {
        const qsList = Array.isArray(branchQuestions.questions) ? branchQuestions.questions : [];
        let pre = assistantText;
        if (branchQuestions.preamble != null && String(branchQuestions.preamble).trim() !== "") {
          pre = String(branchQuestions.preamble);
        }
        finalizeAssistantBubble(assistantBubble, stripDisplayedTransportBlocks(pre), { deepPass: streamDeepPass });
        renderQuestionsUI(qsList);
      } else if (branchAction) {
        const pre = stripDisplayedTransportBlocks(assistantText);
        finalizeAssistantBubble(assistantBubble, pre, { deepPass: streamDeepPass });
        mountHitlActionCard(assistantBubble, branchAction, pre);
      } else {
        finalizeAssistantBubble(assistantBubble, assistantText, { deepPass: streamDeepPass });
      }
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
      const input = qs("aiChatInput");
      if (input) input.focus();
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

    if (input) {
      input.addEventListener("keydown", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          if (!state.sending) form.requestSubmit();
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
