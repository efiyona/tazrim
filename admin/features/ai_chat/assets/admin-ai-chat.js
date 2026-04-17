(function () {
  "use strict";

  const baseUrlRaw = window.ADMIN_AI_CHAT_BASE_URL || "/";
  const baseUrl = baseUrlRaw.endsWith("/") ? baseUrlRaw : baseUrlRaw + "/";
  const apiBase = baseUrl + "admin/features/ai_chat/api/";

  // תוקף הצעת פעולה (ביצוע שינויים במסד) — 5 דקות מרגע ההצעה.
  // מעבר לזה הכפתור ננעל ולא מתבצע שום דבר, מסיבות בטיחות.
  const ACTION_PROPOSAL_TTL_MS = 5 * 60 * 1000;

  const state = {
    open: false,
    activeChatId: null,
    currentChatTitle: "",
    sending: false,
    historyDrawerOpen: false,
    chatPayloadCache: {},
    pendingQuestions: null,
  };

  let streamAbortController = null;

  function qs(id) {
    return document.getElementById(id);
  }

  function assistantDisplayName() {
    if (typeof window.ADMIN_AI_CHAT_ASSISTANT_NAME === "string" && window.ADMIN_AI_CHAT_ASSISTANT_NAME.trim() !== "") {
      return window.ADMIN_AI_CHAT_ASSISTANT_NAME.trim();
    }
    const modal = qs("adminAiChatModal");
    const fromDom = modal && modal.getAttribute("data-admin-ai-chat-assistant");
    if (fromDom && fromDom.trim() !== "") {
      return fromDom.trim();
    }
    return "תזרי מנהל";
  }

  function userInitials() {
    if (typeof window.ADMIN_AI_CHAT_USER_INITIALS === "string" && window.ADMIN_AI_CHAT_USER_INITIALS.trim() !== "") {
      return window.ADMIN_AI_CHAT_USER_INITIALS.trim();
    }
    const modal = qs("adminAiChatModal");
    const fromDom = modal && modal.getAttribute("data-admin-ai-chat-user-initials");
    if (fromDom && fromDom.trim() !== "") {
      return fromDom.trim();
    }
    return "מנ";
  }

  function messageAvatarHtml(role) {
    if (role === "user") {
      return (
        '<div class="admin-ai-chat-message-avatar admin-ai-chat-message-avatar--user" aria-hidden="true">' +
        '<span class="admin-ai-chat-message-avatar-label">' + escapeHtml(userInitials()) + "</span>" +
        "</div>"
      );
    }
    return (
      '<div class="admin-ai-chat-message-avatar admin-ai-chat-message-avatar--assistant" aria-hidden="true">' +
      '<span class="admin-ai-chat-message-avatar-label">AI</span>' +
      "</div>"
    );
  }

  function isMobileLayout() {
    return window.matchMedia("(max-width: 980px)").matches;
  }

  function setHistoryDrawerOpen(open) {
    const layout = document.querySelector(".admin-ai-chat-layout");
    if (!layout) return;
    const shouldOpen = !!open && isMobileLayout();
    state.historyDrawerOpen = shouldOpen;
    layout.classList.toggle("admin-ai-chat-layout--history-open", shouldOpen);
  }

  function escapeHtml(str) {
    return (str || "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  function fieldValuesDifferForDiff(beforeVal, afterVal) {
    const b = beforeVal == null ? "" : String(beforeVal).trim();
    const a = afterVal == null ? "" : String(afterVal).trim();
    return b !== a;
  }

  /** טבלת לפני/אחרי לעדכון — רק שדות שב־data ושערכם שונה מ־before_row */
  function renderUpdateDiffTable(beforeRow, data) {
    if (!data || typeof data !== "object") return "";
    const keys = Object.keys(data);
    if (!keys.length) return "";
    const br = beforeRow && typeof beforeRow === "object" ? beforeRow : {};
    const rowsHtml = [];
    keys.forEach((k) => {
      if (!fieldValuesDifferForDiff(br[k], data[k])) return;
      rowsHtml.push(
        "<tr><td><code>" +
          escapeHtml(k) +
          "</code></td><td>" +
          escapeHtml(String(br[k] != null ? br[k] : "—")) +
          "</td><td>" +
          escapeHtml(String(data[k] != null ? data[k] : "—")) +
          "</td></tr>"
      );
    });
    if (!rowsHtml.length) return "";
    return (
      '<div class="admin-ai-chat-update-diff-wrap">' +
      '<div class="admin-ai-chat-update-diff-title">השוואה לפני אישור (שדות משתנים)</div>' +
      '<table class="admin-ai-chat-update-diff-table"><thead><tr><th>שדה</th><th>לפני</th><th>אחרי</th></tr></thead><tbody>' +
      rowsHtml.join("") +
      "</tbody></table></div>"
    );
  }

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

  const SCHEMA = window.ADMIN_AI_CHAT_SCHEMA || { tables: [], fields_by_table: {}, all_fields: [], actions: ["create", "update", "delete"] };
  const TABLE_SET = new Set((SCHEMA.tables || []).map((t) => String(t).toLowerCase()));
  const FIELD_SET = new Set((SCHEMA.all_fields || []).map((f) => String(f).toLowerCase()));
  const ACTION_SET = new Set((SCHEMA.actions || ["create", "update", "delete"]).map((a) => String(a).toLowerCase()));
  const ACTION_META = {
    create: { icon: "fa-plus", label: "יצירה" },
    update: { icon: "fa-pen", label: "עדכון" },
    delete: { icon: "fa-trash", label: "מחיקה" },
  };

  function classifyInlineToken(raw) {
    const txt = String(raw || "").trim();
    if (!txt) return null;
    const lower = txt.toLowerCase();
    const dotMatch = txt.match(/^([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)$/);
    if (dotMatch) {
      const tbl = dotMatch[1].toLowerCase();
      if (TABLE_SET.has(tbl)) {
        return { type: "field-path", label: txt };
      }
    }
    if (TABLE_SET.has(lower)) return { type: "table", label: txt };
    if (ACTION_SET.has(lower)) return { type: "action", label: lower };
    if (FIELD_SET.has(lower)) return { type: "field", label: txt };
    if (/^\d+$/.test(txt)) return { type: "id", label: txt };
    return { type: "code", label: txt };
  }

  function renderInlineToken(info) {
    const safeLabel = escapeHtml(info.label);
    switch (info.type) {
      case "table":
        return '<code class="admin-ai-chat-code admin-ai-chat-code--table" title="טבלה"><i class="fa-solid fa-table" aria-hidden="true"></i>' + safeLabel + "</code>";
      case "field":
        return '<code class="admin-ai-chat-code admin-ai-chat-code--field" title="שדה"><i class="fa-solid fa-list-check" aria-hidden="true"></i>' + safeLabel + "</code>";
      case "field-path":
        return '<code class="admin-ai-chat-code admin-ai-chat-code--field-path" title="שדה בטבלה"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i>' + safeLabel + "</code>";
      case "action": {
        const meta = ACTION_META[info.label] || { icon: "fa-bolt", label: info.label };
        return '<code class="admin-ai-chat-code admin-ai-chat-code--action admin-ai-chat-code--action-' + info.label + '" title="פעולה"><i class="fa-solid ' + meta.icon + '" aria-hidden="true"></i>' + safeLabel + "</code>";
      }
      case "id":
        return '<code class="admin-ai-chat-code admin-ai-chat-code--id" title="מזהה"><i class="fa-solid fa-hashtag" aria-hidden="true"></i>' + safeLabel + "</code>";
      default:
        return '<code class="admin-ai-chat-code">' + safeLabel + "</code>";
    }
  }

  function markdownInlineCodeToHtml(escapedPlain) {
    if (!escapedPlain || escapedPlain.indexOf("`") === -1) return escapedPlain;
    return escapedPlain.replace(/`([^`\n]{1,80})`/g, (match, content) => {
      const info = classifyInlineToken(content);
      if (!info) return match;
      return renderInlineToken(info);
    });
  }

  // Auto-highlight bare table/field names (without backticks) when they appear as standalone identifiers in the text.
  // Conservative: only lowercase ascii identifiers; skips content already inside <code>/<a>/<strong> tags.
  const AUTO_TOKEN_RE = /(^|[\s()\[\],.;:/\\|،؛·"'?!])([a-z][a-z0-9_]{1,39})(?=($|[\s()\[\],.;:/\\|،؛·"'?!]))/g;
  function autoHighlightSchemaTokens(html) {
    if (!html || html.indexOf("<") === -1 && html.length === 0) return html;
    const parts = html.split(/(<\/?(?:code|a|strong|em|i|b)\b[^>]*>|<[^>]+>)/);
    let skipDepth = 0;
    for (let i = 0; i < parts.length; i++) {
      const p = parts[i];
      if (!p) continue;
      if (/^<(code|a|strong|em|i|b)\b/i.test(p)) { skipDepth++; continue; }
      if (/^<\/(code|a|strong|em|i|b)\b/i.test(p)) { skipDepth = Math.max(0, skipDepth - 1); continue; }
      if (p.startsWith("<")) continue;
      if (skipDepth > 0) continue;
      parts[i] = p.replace(AUTO_TOKEN_RE, (match, pre, word) => {
        const lower = word.toLowerCase();
        if (TABLE_SET.has(lower)) {
          return pre + renderInlineToken({ type: "table", label: word });
        }
        // Only auto-highlight fields with underscore (e.g. user_id, home_id) to avoid false positives on common English words
        if (FIELD_SET.has(lower) && lower.indexOf("_") !== -1) {
          return pre + renderInlineToken({ type: "field", label: word });
        }
        return match;
      });
    }
    return parts.join("");
  }

  function assistantPlainSliceToHtml(slice) {
    let html = escapeHtml(slice);
    html = markdownBoldToHtml(html);
    html = markdownInlineCodeToHtml(html);
    html = autoHighlightSchemaTokens(html);
    return html.replace(/\n/g, "<br>");
  }

  function resolveSiteHref(pathOrUrl) {
    const raw = (pathOrUrl || "").trim();
    if (!raw) return baseUrlRaw.endsWith("/") ? baseUrlRaw : baseUrlRaw + "/";
    if (/^https?:\/\//i.test(raw)) return raw;
    const path = raw.startsWith("/") ? raw.slice(1) : raw;
    return baseUrl + path;
  }

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
        '<a class="admin-ai-chat-page-link" href="' +
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
    const btn = qs("adminAiChatSendBtn");
    const stopBtn = qs("adminAiChatStopBtn");
    if (btn) {
      btn.disabled = v;
      btn.innerHTML = v ? '<i class="fa-solid fa-spinner fa-spin"></i>' : '<i class="fa-solid fa-paper-plane"></i>';
    }
    if (stopBtn) {
      stopBtn.hidden = !v;
      stopBtn.disabled = !v;
    }
  }

  function typingIndicatorHtml() {
    return (
      '<span class="admin-ai-chat-typing" aria-hidden="true">' +
      '<span class="admin-ai-chat-typing-dot"></span>' +
      '<span class="admin-ai-chat-typing-dot"></span>' +
      '<span class="admin-ai-chat-typing-dot"></span>' +
      "</span>"
    );
  }

  function normalizeChatTitle(raw) {
    const clean = (raw || "").replace(/\s+/g, " ").trim();
    return clean ? clean.slice(0, 70) : "";
  }

  function setCurrentChatTitle(title) {
    const titleEl = qs("adminAiChatCurrentTitle");
    if (!titleEl) return;
    const safeTitle = normalizeChatTitle(title);
    state.currentChatTitle = safeTitle;
    titleEl.textContent = safeTitle;
    titleEl.classList.toggle("admin-ai-chat-chat-title--hidden", safeTitle === "");
  }

  function setComposerVisible(visible) {
    const composer = qs("adminAiChatComposer");
    if (!composer) return;
    composer.classList.toggle("admin-ai-chat-composer--hidden", !visible);
    composer.setAttribute("aria-hidden", visible ? "false" : "true");
  }

  function forkMessageToNewChat(rawText) {
    const t = String(rawText || "")
      .trim()
      .slice(0, 1500);
    if (!t) return;
    startDraftChat();
    const input = qs("adminAiChatInput");
    if (input) {
      input.value = t;
      input.focus();
      try {
        input.setSelectionRange(t.length, t.length);
      } catch (e) {
        /* ignore */
      }
    }
    const composer = qs("adminAiChatComposer");
    if (composer && typeof composer.scrollIntoView === "function") {
      try {
        composer.scrollIntoView({ block: "nearest", behavior: "smooth" });
      } catch (e) {
        /* ignore */
      }
    }
  }

  function addMessageBubble(role, text, opts = {}) {
    const wrap = qs("adminAiChatMessages");
    if (!wrap) return null;
    const cls = role === "user" ? "user" : "assistant";
    const metaLabel = role === "user" ? "אתה" : assistantDisplayName();
    const idAttr = opts.id ? ` data-msg-id="${opts.id}"` : "";
    let innerContent;
    if (role === "assistant" && opts.loading) {
      innerContent = '<div class="admin-ai-chat-bubble-inner admin-ai-chat-bubble-inner--typing">' + typingIndicatorHtml() + "</div>";
    } else if (role === "assistant") {
      innerContent = '<div class="admin-ai-chat-bubble-inner">' + formatAssistantHtml(text) + "</div>";
    } else {
      const forkPayload = String(text || "")
        .trim()
        .slice(0, 1500);
      const forkBtn =
        forkPayload !== ""
          ? '<button type="button" class="admin-ai-chat-fork-msg-btn" data-admin-ai-chat-fork="1" data-fork-payload="' +
            escapeHtml(encodeURIComponent(forkPayload)) +
            '" title="שיחה חדשה עם ניסוח זה (בלי היסטוריית השיחה)" aria-label="שיחה חדשה עם ניסוח זה (בלי היסטוריה)">' +
            '<i class="fa-solid fa-clone" aria-hidden="true"></i></button>'
          : "";
      innerContent =
        '<div class="admin-ai-chat-bubble-meta-row">' +
        '<div class="admin-ai-chat-bubble-meta">' +
        escapeHtml(metaLabel) +
        "</div>" +
        forkBtn +
        "</div>" +
        '<div class="admin-ai-chat-bubble-inner">' +
        formatUserHtml(text) +
        "</div>";
    }
    const html =
      `<article class="admin-ai-chat-bubble-row admin-ai-chat-bubble-row--${cls}">` +
      messageAvatarHtml(role) +
      `<section class="admin-ai-chat-bubble ${cls}"${idAttr}>` +
      (role === "assistant"
        ? `<div class="admin-ai-chat-bubble-meta">${escapeHtml(metaLabel)}</div>` + innerContent
        : innerContent) +
      `</section>` +
      `</article>`;
    wrap.insertAdjacentHTML("beforeend", html);
    wrap.scrollTop = wrap.scrollHeight;
    return wrap.lastElementChild ? wrap.lastElementChild.querySelector(".admin-ai-chat-bubble") : null;
  }

  function updateBubbleStreamingAssistant(el, text, opts = {}) {
    if (!el) return;
    let inner = el.querySelector(".admin-ai-chat-bubble-inner");
    if (!inner) return;
    el.classList.remove("admin-ai-chat-bubble--thinking");
    el.classList.remove("admin-ai-chat-bubble--agent-working");
    if (opts.clearThinking) {
      clearThinkingBanner(el);
    }
    inner.classList.remove("admin-ai-chat-bubble-inner--typing");
    const cleaned = stripActionProposedBlock(stripQuestionsBlock(text));
    inner.innerHTML = escapeHtml(cleaned).replace(/\n/g, "<br>");
    const wrap = qs("adminAiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  function showAgentWorkingBanner(bubbleEl, opts) {
    const o = opts || {};
    const inner = bubbleEl && bubbleEl.querySelector(".admin-ai-chat-bubble-inner");
    if (!inner) return;
    bubbleEl.classList.add("admin-ai-chat-bubble--agent-working");
    inner.classList.remove("admin-ai-chat-bubble-inner--typing");
    const title = o.title || "עובד על זה";
    const hint = o.hint || "";
    const variant = o.variant || "data";
    const iconClass = variant === "validating" ? "fa-shield-halved" : "fa-database";
    inner.innerHTML =
      '<div class="admin-ai-chat-agent-banner admin-ai-chat-agent-banner--' + escapeHtml(variant) + '" role="status">' +
      '<span class="admin-ai-chat-agent-banner-icon" aria-hidden="true"><i class="fa-solid ' + iconClass + '"></i></span>' +
      '<span class="admin-ai-chat-agent-banner-title">' + escapeHtml(title) + "</span>" +
      (hint ? '<span class="admin-ai-chat-agent-banner-hint">' + escapeHtml(hint) + "</span>" : "") +
      '<span class="admin-ai-chat-thinking-dots">' +
      '<span class="admin-ai-chat-typing-dot"></span>' +
      '<span class="admin-ai-chat-typing-dot"></span>' +
      '<span class="admin-ai-chat-typing-dot"></span>' +
      "</span>" +
      "</div>";
    const wrap = qs("adminAiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  function clearAgentWorkingBanner(bubbleEl) {
    const inner = bubbleEl && bubbleEl.querySelector(".admin-ai-chat-bubble-inner");
    if (!inner) return;
    bubbleEl.classList.remove("admin-ai-chat-bubble--agent-working");
    if (inner.querySelector(".admin-ai-chat-agent-banner")) {
      inner.innerHTML = "";
    }
  }

  function showValidationRejectedNotice(bubbleEl, analysis, suggestion, attempt) {
    const inner = bubbleEl && bubbleEl.querySelector(".admin-ai-chat-bubble-inner");
    if (!inner) return;
    bubbleEl.classList.add("admin-ai-chat-bubble--agent-working");
    const parts = [];
    parts.push(
      '<div class="admin-ai-chat-agent-banner admin-ai-chat-agent-banner--rejected" role="status">'
    );
    parts.push(
      '<span class="admin-ai-chat-agent-banner-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>'
    );
    parts.push(
      '<span class="admin-ai-chat-agent-banner-title">הפעולה נדחתה ע"י הוולידטור (ניסיון ' + (attempt || 1) + ")</span>"
    );
    if (analysis) {
      parts.push('<div class="admin-ai-chat-agent-banner-analysis">' + escapeHtml(analysis) + "</div>");
    }
    if (suggestion) {
      parts.push(
        '<div class="admin-ai-chat-agent-banner-suggestion"><i class="fa-solid fa-rotate"></i> מתקן ומנסה שוב: ' + escapeHtml(suggestion) + "</div>"
      );
    }
    parts.push("</div>");
    inner.innerHTML = parts.join("");
    const wrap = qs("adminAiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  function stripQuestionsBlock(text) {
    return (text || "")
      .replace(/\[\[QUESTIONS\]\][\s\S]*?\[\[\/QUESTIONS\]\]/g, "")
      .replace(/\[\[QUESTIONS_ASKED\]\]/g, "")
      .trim();
  }

  function stripActionProposedBlock(text) {
    return (text || "").replace(/\[\[ACTION_PROPOSED\]\][\s\S]*?\[\[\/ACTION_PROPOSED\]\]/g, "").trim();
  }

  function stripExecutionResultBlock(text) {
    return (text || "").replace(/\[\[EXECUTION_RESULT\]\][\s\S]*?\[\[\/EXECUTION_RESULT\]\]/g, "").trim();
  }

  function extractActionProposedBlock(text) {
    const m = (text || "").match(/\[\[ACTION_PROPOSED\]\]([\s\S]*?)\[\[\/ACTION_PROPOSED\]\]/);
    if (!m) return null;
    try {
      return JSON.parse(m[1]);
    } catch (e) {
      return null;
    }
  }

  function extractExecutionResultBlock(text) {
    const m = (text || "").match(/\[\[EXECUTION_RESULT\]\]([\s\S]*?)\[\[\/EXECUTION_RESULT\]\]/);
    if (!m) return null;
    try {
      return JSON.parse(m[1]);
    } catch (e) {
      return null;
    }
  }

  function finalizeAssistantBubble(el, fullText, opts = {}) {
    if (!el) return;
    const inner = el.querySelector(".admin-ai-chat-bubble-inner");
    if (!inner) return;
    el.classList.remove("admin-ai-chat-bubble--thinking");
    inner.classList.remove("admin-ai-chat-bubble-inner--typing");
    const cleaned = stripQuestionsBlock(fullText);
    inner.innerHTML = formatAssistantHtml(cleaned);
    el.querySelectorAll(".admin-ai-chat-deep-footnote").forEach((n) => n.remove());
    if (opts.deepPass) {
      const fn = document.createElement("div");
      fn.className = "admin-ai-chat-deep-footnote";
      fn.setAttribute("role", "note");
      fn.innerHTML =
        '<i class="fa-solid fa-brain" aria-hidden="true"></i> ' +
        escapeHtml("התשובה נוסחה במצב חשיבה מתקדמת");
      el.appendChild(fn);
    }
    const wrap = qs("adminAiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  function showThinkingBanner(bubbleEl, hint) {
    const inner = bubbleEl && bubbleEl.querySelector(".admin-ai-chat-bubble-inner");
    if (!inner) return;
    bubbleEl.classList.add("admin-ai-chat-bubble--thinking");
    const h = (hint || "").trim();
    const hintBlock = h
      ? '<span class="admin-ai-chat-thinking-hint">' + escapeHtml(h) + "</span>"
      : "";
    inner.classList.remove("admin-ai-chat-bubble-inner--typing");
    inner.innerHTML =
      '<div class="admin-ai-chat-thinking-banner" role="status">' +
      '<span class="admin-ai-chat-thinking-icon" aria-hidden="true"><i class="fa-solid fa-brain"></i></span>' +
      '<span class="admin-ai-chat-thinking-title">חשיבה מתקדמת</span>' +
      hintBlock +
      '<span class="admin-ai-chat-thinking-dots">' +
      '<span class="admin-ai-chat-typing-dot"></span>' +
      '<span class="admin-ai-chat-typing-dot"></span>' +
      '<span class="admin-ai-chat-typing-dot"></span>' +
      "</span>" +
      "</div>";
    const wrap = qs("adminAiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  function clearThinkingBanner(bubbleEl) {
    const inner = bubbleEl && bubbleEl.querySelector(".admin-ai-chat-bubble-inner");
    if (!inner) return;
    bubbleEl.classList.remove("admin-ai-chat-bubble--thinking");
    if (inner.querySelector(".admin-ai-chat-thinking-banner")) {
      inner.innerHTML = "";
    }
  }

  function actionLabelHebrew(action) {
    switch ((action || "").toLowerCase()) {
      case "create": return "יצירה";
      case "update": return "עדכון";
      case "delete": return "מחיקה";
      case "sql": return "SQL";
      default: return action || "פעולה";
    }
  }

  function actionBtnLabel(action) {
    switch ((action || "").toLowerCase()) {
      case "create": return "צור רשומה";
      case "update": return "עדכן";
      case "delete": return "מחק";
      case "sql": return "הרץ SQL";
      default: return "בצע פעולה";
    }
  }

  function renderActionDataList(data) {
    if (!data || typeof data !== "object") return "";
    const keys = Object.keys(data);
    if (keys.length === 0) return "";
    let rows = "";
    keys.forEach((k) => {
      const v = data[k];
      let display;
      if (v === null) display = "<em>null</em>";
      else if (typeof v === "object") display = escapeHtml(JSON.stringify(v));
      else display = escapeHtml(String(v));
      rows +=
        '<div class="admin-ai-chat-action-data-row">' +
        '<span class="admin-ai-chat-action-data-key">' + escapeHtml(k) + ":</span>" +
        '<span class="admin-ai-chat-action-data-val">' + display + "</span>" +
        "</div>";
    });
    return '<div class="admin-ai-chat-action-data">' + rows + "</div>";
  }

  function formatDurationMs(ms) {
    if (!isFinite(ms) || ms < 0) ms = 0;
    const totalSec = Math.floor(ms / 1000);
    const m = Math.floor(totalSec / 60);
    const s = totalSec % 60;
    return m + ":" + (s < 10 ? "0" + s : String(s));
  }

  function actionProposalRemainingMs(actionPayload) {
    const t = actionPayload && actionPayload.proposedAt;
    if (!t) return ACTION_PROPOSAL_TTL_MS;
    return Math.max(0, (t + ACTION_PROPOSAL_TTL_MS) - Date.now());
  }

  function isActionProposalExpired(actionPayload) {
    return actionProposalRemainingMs(actionPayload) <= 0;
  }

  function clearActionExpiryTimer(bubbleEl) {
    if (!bubbleEl) return;
    const t = bubbleEl.__adminAiActionExpiryTimer;
    if (t) {
      clearInterval(t);
      bubbleEl.__adminAiActionExpiryTimer = null;
    }
  }

  function extractExecutionIdFromResult(result) {
    if (!result || result.status !== "success") return null;
    if (result.id != null && result.id !== "") return Number(result.id);
    if (result.insert_id != null && result.insert_id !== "") return Number(result.insert_id);
    return null;
  }

  function resolveSequencePlaceholders(value, stepResults) {
    if (value === null || value === undefined) return value;
    if (typeof value === "string") {
      const m = value.match(/^\{\{step:(\d+)\}\}$/);
      if (m) {
        const idx = parseInt(m[1], 10);
        const got = stepResults[idx];
        const idVal = got && got.id != null ? got.id : null;
        if (idVal == null) return value;
        return typeof idVal === "number" ? idVal : parseInt(String(idVal), 10);
      }
      return value;
    }
    if (Array.isArray(value)) {
      return value.map((v) => resolveSequencePlaceholders(v, stepResults));
    }
    if (typeof value === "object") {
      const out = {};
      Object.keys(value).forEach((k) => {
        out[k] = resolveSequencePlaceholders(value[k], stepResults);
      });
      return out;
    }
    return value;
  }

  function buildExecuteBodyFromLeaf(leaf, proposedAt, chatId) {
    const token = window.ADMIN_AI_CHAT_API_TOKEN || "";
    const body = {
      api_token: token,
      action: leaf.action,
      table: leaf.table || "",
      chat_id: chatId,
      proposed_at: proposedAt || Date.now(),
    };
    if (leaf.id != null && leaf.id !== "") body.id = leaf.id;
    if (leaf.data) body.data = leaf.data;
    if (leaf.sql) body.sql = leaf.sql;
    if (leaf.kind) body.kind = leaf.kind;
    return body;
  }

  async function postAgentExecuteRequest(body) {
    const url = apiBase + "agent_execute.php";
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(body),
        credentials: "same-origin",
      });
      const rawText = await res.text();
      let parsed = null;
      if (rawText) {
        try {
          parsed = JSON.parse(rawText);
        } catch (e) {
          parsed = null;
        }
      }
      if (parsed && typeof parsed === "object") {
        if (!parsed.status) parsed.status = res.ok ? "success" : "error";
        return parsed;
      }
      const snippet = (rawText || "").replace(/\s+/g, " ").trim().slice(0, 240);
      return {
        status: "error",
        message: res.ok
          ? "השרת החזיר תשובה לא-JSON (HTTP " + res.status + ")"
          : "הבקשה נכשלה (HTTP " + res.status + ")",
        detail: snippet || "HTTP " + res.status + " · " + res.statusText,
        http_status: res.status,
      };
    } catch (err) {
      return {
        status: "error",
        message: "שגיאת רשת בדרך לשרת",
        detail: err && err.message ? String(err.message) : "Fetch failed",
      };
    }
  }

  function formatSequenceVerificationMessage(vr) {
    if (!vr || vr.status !== "success") {
      return "אימות מול המסד לא הצליח.";
    }
    if (vr.ok) {
      return "אימות אוטומטי: המצב במסד תואם את הבדיקות שהוגדרו.";
    }
    const lines = [];
    (vr.checks || []).forEach((c) => {
      if (c && c.ok) return;
      const mm = c && c.mismatches ? c.mismatches : {};
      const parts = Object.keys(mm).map((col) => {
        const cell = mm[col];
        if (cell && Object.prototype.hasOwnProperty.call(cell, "expected")) {
          return (
            col +
            ": צפוי " +
            JSON.stringify(cell.expected) +
            ", בפועל " +
            JSON.stringify(cell.actual)
          );
        }
        return col + ": " + (cell && cell.reason ? String(cell.reason) : "סטיה");
      });
      lines.push(
        "• " +
          String((c && c.table) || "?") +
          " id=" +
          String(c && c.id != null ? c.id : "?") +
          (parts.length ? " — " + parts.join("; ") : "")
      );
    });
    return "אימות אוטומטי: נמצאו סטיות.\n" + (lines.length ? lines.join("\n") : String(vr.message || ""));
  }

  async function postSequenceVerification(resolvedVerification) {
    const token = window.ADMIN_AI_CHAT_API_TOKEN || "";
    if (!token) {
      return { status: "error", message: "missing_api_token" };
    }
    try {
      const res = await fetch(apiBase + "verify_sequence.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ api_token: token, verification: resolvedVerification }),
        credentials: "same-origin",
      });
      const data = await res.json();
      if (!data || typeof data !== "object") {
        return { status: "error", message: "bad_response" };
      }
      if (!data.status) data.status = res.ok ? "success" : "error";
      return data;
    } catch (err) {
      return { status: "error", message: err && err.message ? String(err.message) : "network" };
    }
  }

  function renderSequenceActionCards(bubbleEl, actionPayload, opts) {
    const o = opts || {};
    const inner = bubbleEl && bubbleEl.querySelector(".admin-ai-chat-bubble-inner");
    if (!inner) return;
    clearActionExpiryTimer(bubbleEl);
    if (!actionPayload.proposedAt) {
      actionPayload.proposedAt = Date.now();
    }
    const steps = actionPayload.steps || [];
    const seqDesc = actionPayload.description || "";
    const validation = actionPayload.validation || {};
    const n = steps.length;

    if (!bubbleEl.__adminAiSequenceState) {
      bubbleEl.__adminAiSequenceState = { results: [], failedStep: null };
    }
    const seqSt = bubbleEl.__adminAiSequenceState;
    if (o.executed && o.executionResult && o.executionResult.status === "historical") {
      seqSt.results = steps.map(() => ({ id: null, historical: true }));
    }

    let preambleHtml = "";
    if (o.preamble) {
      preambleHtml = '<div class="admin-ai-chat-action-preamble">' + formatAssistantHtml(o.preamble) + "</div>";
    }

    let html =
      preambleHtml +
      '<div class="admin-ai-chat-action-sequence">' +
      '<div class="admin-ai-chat-action-seq-head">' +
      '<span class="admin-ai-chat-action-badge">רצף פעולות</span>' +
      '<span class="admin-ai-chat-action-table">' +
      escapeHtml(String(n)) +
      " שלבים · יש לבצע לפי סדר" +
      "</span></div>";

    if (seqDesc) {
      html += '<div class="admin-ai-chat-action-desc admin-ai-chat-action-seq-overall">' + escapeHtml(seqDesc) + "</div>";
    }

    if (validation && validation.analysis) {
      const confidence = validation.confidence || "medium";
      html += '<div class="admin-ai-chat-action-validation admin-ai-chat-action-validation--' + escapeHtml(confidence) + '">';
      html += '<div class="admin-ai-chat-action-validation-head">';
      html += '<i class="fa-solid fa-shield-halved"></i> <strong>אומת ע"י וולידטור</strong> · ביטחון: ' + escapeHtml(confidence);
      html += "</div>";
      html += '<div class="admin-ai-chat-action-validation-body">' + escapeHtml(validation.analysis) + "</div>";
      if (validation.warnings && validation.warnings.length > 0) {
        html +=
          '<div class="admin-ai-chat-action-validation-warnings"><i class="fa-solid fa-triangle-exclamation"></i> ' +
          escapeHtml(validation.warnings.join(", ")) +
          "</div>";
      }
      html += "</div>";
    }

    const hist = o.executed && o.executionResult && o.executionResult.status === "historical";
    const cancelled = o.executed && o.executionResult && o.executionResult.status === "cancelled";
    const globalFail =
      o.executed &&
      o.executionResult &&
      o.executionResult.status === "error" &&
      seqSt.failedStep == null &&
      seqSt.results.length === 0;

    if (!hist && !cancelled && !globalFail && !o.executionResult) {
      const remainingMs = actionProposalRemainingMs(actionPayload);
      const alreadyExpired = remainingMs <= 0;
      const ttlClass = alreadyExpired
        ? "admin-ai-chat-action-ttl--expired"
        : remainingMs <= 60 * 1000
          ? "admin-ai-chat-action-ttl--warning"
          : "";
      html += '<div class="admin-ai-chat-action-ttl ' + ttlClass + '" data-admin-ai-action-ttl>';
      if (alreadyExpired) {
        html +=
          '<i class="fa-solid fa-hourglass-end"></i> <span data-admin-ai-action-ttl-text>תוקף ההצעה פג — בקש מהסוכן להציע מחדש</span>';
      } else {
        html +=
          '<i class="fa-solid fa-hourglass-half"></i> <span data-admin-ai-action-ttl-text>תוקף: נותרו <strong data-admin-ai-action-ttl-clock>' +
          formatDurationMs(remainingMs) +
          "</strong></span>";
      }
      html += "</div>";
    }

    steps.forEach((step, i) => {
      const stAct = (step.action || "").toLowerCase();
      const stTable = step.table || "";
      const stDesc = step.description || "";
      const isSql = stAct === "sql";
      const sqlText = step.sql || "";
      const sqlVerb = isSql && sqlText ? (sqlText.match(/^\s*([A-Za-z]+)/) || [])[1] : "";
      const sqlVerbU = (sqlVerb || "").toUpperCase();
      const dangerous = stAct === "delete" || (isSql && ["DROP", "TRUNCATE", "DELETE", "ALTER"].includes(sqlVerbU));
      const canRun =
        !hist &&
        !cancelled &&
        !globalFail &&
        seqSt.failedStep === null &&
        seqSt.results.length === i &&
        !isActionProposalExpired(actionPayload);
      const done = seqSt.results[i] && seqSt.results[i].ok;
      const failedHere = seqSt.failedStep === i;

      html += '<div class="admin-ai-chat-action-step admin-ai-chat-action-card admin-ai-chat-action-card--' + escapeHtml(stAct);
      if (dangerous) html += " admin-ai-chat-action-card--dangerous";
      html += '" data-seq-step-index="' + i + '">';
      html += '<div class="admin-ai-chat-action-step-label">שלב ' + (i + 1) + " מתוך " + n + "</div>";
      html += '<div class="admin-ai-chat-action-header">';
      html += '<span class="admin-ai-chat-action-badge">' + escapeHtml(actionLabelHebrew(step.action)) + "</span>";
      if (isSql) {
        html += '<span class="admin-ai-chat-action-table">SQL · <code>' + escapeHtml(sqlVerbU || "?") + "</code></span>";
      } else {
        html += '<span class="admin-ai-chat-action-table">טבלה: <code>' + escapeHtml(stTable) + "</code></span>";
      }
      html += "</div>";
      if (stDesc) {
        html += '<div class="admin-ai-chat-action-desc">' + escapeHtml(stDesc) + "</div>";
      }
      if (isSql && sqlText) {
        html += '<div class="admin-ai-chat-action-sql"><pre class="admin-ai-chat-action-sql-code"><code>' + escapeHtml(sqlText) + "</code></pre></div>";
      }
      const previewData = resolveSequencePlaceholders(step.data || {}, seqSt.results.map((r) => ({ id: r && r.id != null ? r.id : null })));
      html += renderActionDataList(previewData);
      if (stAct === "update" && step.before_row && previewData && typeof previewData === "object") {
        html += renderUpdateDiffTable(step.before_row, previewData);
      }
      if (step.id != null && step.id !== "") {
        const rid = resolveSequencePlaceholders(step.id, seqSt.results.map((r) => ({ id: r && r.id != null ? r.id : null })));
        html +=
          '<div class="admin-ai-chat-action-seq-rowid">מזהה שורה: <code>' + escapeHtml(String(rid)) + "</code></div>";
      }

      if (hist) {
        html += '<div class="admin-ai-chat-seq-step-foot"><i class="fa-solid fa-clock-rotate-left"></i> הצעה מההיסטוריה</div>';
      } else if (cancelled) {
        html += '<div class="admin-ai-chat-seq-step-foot admin-ai-chat-seq-step-foot--muted">בוטל</div>';
      } else if (done) {
        html +=
          '<div class="admin-ai-chat-seq-step-foot admin-ai-chat-seq-step-foot--ok"><i class="fa-solid fa-check"></i> בוצע (id=' +
          escapeHtml(String(seqSt.results[i].id != null ? seqSt.results[i].id : "—")) +
          ")</div>";
      } else if (failedHere && o.executionResult) {
        html +=
          '<div class="admin-ai-chat-seq-step-foot admin-ai-chat-seq-step-foot--err"><i class="fa-solid fa-xmark"></i> ' +
          escapeHtml(o.executionResult.message || "נכשל") +
          "</div>";
        html +=
          '<button type="button" class="admin-ai-chat-action-retry admin-ai-chat-seq-retry" data-seq-retry="' +
          i +
          '"><i class="fa-solid fa-rotate-right"></i> נסה שוב שלב זה</button>';
      } else if (canRun) {
        html +=
          '<div class="admin-ai-chat-action-actions admin-ai-chat-seq-step-actions">' +
          '<button type="button" class="admin-ai-chat-action-btn admin-ai-chat-seq-step-btn' +
          (dangerous ? " admin-ai-chat-action-btn--danger" : "") +
          '" data-seq-run="' +
          i +
          '"' +
          (isActionProposalExpired(actionPayload) ? " disabled" : "") +
          ">" +
          '<i class="fa-solid fa-bolt"></i> ' +
          escapeHtml("בצע שלב " + (i + 1)) +
          "</button></div>";
      } else {
        html +=
          '<div class="admin-ai-chat-seq-step-foot admin-ai-chat-seq-step-foot--wait"><i class="fa-solid fa-lock"></i> ממתין לשלבים קודמים</div>';
      }
      html += "</div>";
    });

    if (!hist && !cancelled && !globalFail && !o.executionResult) {
      html +=
        '<div class="admin-ai-chat-seq-cancel-wrap"><button type="button" class="admin-ai-chat-action-cancel" data-seq-cancel="1">' +
        escapeHtml("בטל את כל הרצף") +
        "</button></div>";
    }

    html += "</div>";
    inner.innerHTML = html;

    if (!hist && !cancelled && !globalFail && !o.executionResult) {
      const ttlClock = inner.querySelector("[data-admin-ai-action-ttl-clock]");
      const ttlWrap = inner.querySelector("[data-admin-ai-action-ttl]");
      const ttlText = inner.querySelector("[data-admin-ai-action-ttl-text]");
      if (actionProposalRemainingMs(actionPayload) > 0) {
        bubbleEl.__adminAiActionExpiryTimer = setInterval(() => {
          if (!bubbleEl.isConnected) {
            clearActionExpiryTimer(bubbleEl);
            return;
          }
          const remaining = actionProposalRemainingMs(actionPayload);
          if (remaining <= 0) {
            clearActionExpiryTimer(bubbleEl);
            inner.querySelectorAll(".admin-ai-chat-seq-step-btn").forEach((b) => {
              b.disabled = true;
              b.classList.add("admin-ai-chat-action-btn--expired");
            });
            if (ttlWrap) {
              ttlWrap.classList.remove("admin-ai-chat-action-ttl--warning");
              ttlWrap.classList.add("admin-ai-chat-action-ttl--expired");
            }
            if (ttlText) ttlText.textContent = "תוקף ההצעה פג — בקש מהסוכן להציע מחדש";
            return;
          }
          if (ttlClock) ttlClock.textContent = formatDurationMs(remaining);
          if (ttlWrap && remaining <= 60 * 1000) ttlWrap.classList.add("admin-ai-chat-action-ttl--warning");
        }, 1000);
      }

      inner.querySelectorAll(".admin-ai-chat-seq-step-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
          const idx = parseInt(btn.getAttribute("data-seq-run") || "0", 10);
          void runSequenceStep(bubbleEl, actionPayload, idx, o.preamble || "");
        });
      });
      const cbtn = inner.querySelector("[data-seq-cancel]");
      if (cbtn) {
        cbtn.addEventListener("click", () => {
          renderSequenceActionCards(bubbleEl, actionPayload, {
            preamble: o.preamble || "",
            executed: true,
            executionResult: { status: "cancelled", message: "הרצף בוטל" },
          });
        });
      }
    }

    inner.querySelectorAll(".admin-ai-chat-seq-retry").forEach((retryBtn) => {
      retryBtn.addEventListener("click", () => {
        const idx = parseInt(retryBtn.getAttribute("data-seq-retry") || "0", 10);
        if (bubbleEl.__adminAiSequenceState) bubbleEl.__adminAiSequenceState.failedStep = null;
        void runSequenceStep(bubbleEl, actionPayload, idx, o.preamble || "");
      });
    });

    const wrap = qs("adminAiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  async function runSequenceStep(bubbleEl, actionPayload, stepIndex, preamble) {
    const steps = actionPayload.steps || [];
    const step = steps[stepIndex];
    if (!step) return;
    if (isActionProposalExpired(actionPayload)) {
      renderSequenceActionCards(bubbleEl, actionPayload, {
        preamble: preamble,
        executed: true,
        executionResult: { status: "error", message: "תוקף הפעולה פג (מעל 5 דקות)." },
      });
      return;
    }
    const token = window.ADMIN_AI_CHAT_API_TOKEN || "";
    if (!token) {
      renderSequenceActionCards(bubbleEl, actionPayload, {
        preamble: preamble,
        executed: true,
        executionResult: { status: "error", message: "חסר api_token" },
      });
      return;
    }
    const seqSt = bubbleEl.__adminAiSequenceState || { results: [], failedStep: null };
    bubbleEl.__adminAiSequenceState = seqSt;
    const refs = seqSt.results.map((r) => ({ id: r && r.id != null ? r.id : null }));
    const raw = JSON.parse(JSON.stringify(step));
    const resolved = {
      action: raw.action,
      table: raw.table,
      description: raw.description,
      sql: raw.sql,
      kind: raw.kind,
      data: raw.data ? resolveSequencePlaceholders(raw.data, refs) : undefined,
    };
    if (raw.id !== undefined && raw.id !== null && raw.id !== "") {
      resolved.id = resolveSequencePlaceholders(raw.id, refs);
    }
    const unresolved = JSON.stringify(resolved).indexOf("{{step:") !== -1;
    if (unresolved) {
      seqSt.failedStep = stepIndex;
      renderSequenceActionCards(bubbleEl, actionPayload, {
        preamble: preamble,
        executed: true,
        executionResult: {
          status: "error",
          message: "חסרים תוצאות משלב קודם — לא ניתן למלא את המזהים. נסה מהשלב הראשון או בקש הצעה מחדש.",
        },
      });
      return;
    }

    clearActionExpiryTimer(bubbleEl);
    const inner = bubbleEl.querySelector(".admin-ai-chat-bubble-inner");
    const runBtn = inner && inner.querySelector('.admin-ai-chat-seq-step-btn[data-seq-run="' + stepIndex + '"]');
    if (runBtn) {
      runBtn.disabled = true;
      runBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מבצע…';
    }

    const body = buildExecuteBodyFromLeaf(resolved, actionPayload.proposedAt, state.activeChatId);
    const result = await postAgentExecuteRequest(body);

    if (result.status === "success") {
      const newId = extractExecutionIdFromResult(result);
      seqSt.results[stepIndex] = { ok: true, id: newId != null ? newId : null };
      seqSt.failedStep = null;
      if (stepIndex >= steps.length - 1) {
        renderSequenceActionCards(bubbleEl, actionPayload, {
          preamble: preamble,
          executed: true,
          executionResult: { status: "success", message: "כל השלבים הושלמו" },
        });
        let verifyLine = "";
        if (actionPayload.verification) {
          const refs = seqSt.results.map((r) => ({ id: r && r.id != null ? r.id : null }));
          const resolvedVer = resolveSequencePlaceholders(actionPayload.verification, refs);
          const vr = await postSequenceVerification(resolvedVer);
          verifyLine = "\n\n" + formatSequenceVerificationMessage(vr);
        }
        addMessageBubble(
          "assistant",
          "רצף הפעולות הושלם בהצלחה (" + steps.length + " שלבים). " + (result.message || "") + verifyLine,
          { loading: false }
        );
        invalidateChatCache(state.activeChatId);
        await loadHistory();
        return;
      }
      renderSequenceActionCards(bubbleEl, actionPayload, { preamble: preamble });
      invalidateChatCache(state.activeChatId);
      return;
    }

    seqSt.failedStep = stepIndex;
    renderSequenceActionCards(bubbleEl, actionPayload, {
      preamble: preamble,
      executed: true,
      executionResult: result,
    });
    addMessageBubble(
      "assistant",
      "שלב " + (stepIndex + 1) + " נכשל: " + (result.message || "שגיאה"),
      { loading: false }
    );
    invalidateChatCache(state.activeChatId);
  }

  function renderActionCard(bubbleEl, actionPayload, opts) {
    const o = opts || {};
    const inner = bubbleEl && bubbleEl.querySelector(".admin-ai-chat-bubble-inner");
    if (!inner) return;
    bubbleEl.classList.remove("admin-ai-chat-bubble--agent-working");
    clearActionExpiryTimer(bubbleEl);

    // מוודאים חותמת זמן על ההצעה, למימוש TTL של 5 דקות
    if (!actionPayload.proposedAt) {
      actionPayload.proposedAt = Date.now();
    }

    if (
      (actionPayload.action || "").toLowerCase() === "sequence" &&
      Array.isArray(actionPayload.steps) &&
      actionPayload.steps.length >= 2
    ) {
      renderSequenceActionCards(bubbleEl, actionPayload, o);
      return;
    }

    const action = actionPayload.action || "";
    const table = actionPayload.table || "";
    const rowId = actionPayload.id || null;
    const description = actionPayload.description || "";
    const validation = actionPayload.validation || {};
    const data = actionPayload.data || null;
    const sqlText = actionPayload.sql || "";
    const sqlKind = (actionPayload.kind || "").toLowerCase();
    const actionType = action.toLowerCase();
    const isSql = actionType === "sql";
    const sqlVerb = isSql && sqlText ? (sqlText.match(/^\s*([A-Za-z]+)/) || [])[1] : "";
    const sqlVerbUpper = (sqlVerb || "").toUpperCase();
    const dangerousSqlVerbs = ["DROP", "TRUNCATE", "DELETE", "ALTER"];
    const dangerous = actionType === "delete" || (isSql && dangerousSqlVerbs.includes(sqlVerbUpper));

    let preambleHtml = "";
    if (o.preamble) {
      preambleHtml = '<div class="admin-ai-chat-action-preamble">' + formatAssistantHtml(o.preamble) + "</div>";
    }

    let html = preambleHtml + '<div class="admin-ai-chat-action-card admin-ai-chat-action-card--' + escapeHtml(actionType) + (dangerous ? " admin-ai-chat-action-card--dangerous" : "") + '">';
    html += '<div class="admin-ai-chat-action-header">';
    html += '<span class="admin-ai-chat-action-badge">' + escapeHtml(actionLabelHebrew(action)) + "</span>";
    if (isSql) {
      const kindLabel = sqlKind === "ddl" ? "שינוי מבנה (DDL)" : (sqlKind === "dml" ? "נתונים (DML)" : "SQL");
      html += '<span class="admin-ai-chat-action-table">' + escapeHtml(kindLabel);
      if (sqlVerbUpper) {
        html += ' · פקודה: <code>' + escapeHtml(sqlVerbUpper) + '</code>';
      }
      html += "</span>";
    } else {
      html += '<span class="admin-ai-chat-action-table">טבלה: <code>' + escapeHtml(table) + "</code>";
      if (rowId) html += ' · ID: <code>' + escapeHtml(String(rowId)) + "</code>";
      html += "</span>";
    }
    html += "</div>";
    if (description) {
      html += '<div class="admin-ai-chat-action-desc">' + escapeHtml(description) + "</div>";
    }
    if (isSql && sqlText) {
      html += '<div class="admin-ai-chat-action-sql">';
      html += '<div class="admin-ai-chat-action-sql-label"><i class="fa-solid fa-terminal"></i> משפט SQL שיורץ</div>';
      html += '<pre class="admin-ai-chat-action-sql-code"><code>' + escapeHtml(sqlText) + '</code></pre>';
      html += "</div>";
    }
    html += renderActionDataList(data);
    if (actionType === "update" && actionPayload.before_row && data && typeof data === "object") {
      html += renderUpdateDiffTable(actionPayload.before_row, data);
    }
    if (validation && validation.analysis) {
      const confidence = validation.confidence || "medium";
      html += '<div class="admin-ai-chat-action-validation admin-ai-chat-action-validation--' + escapeHtml(confidence) + '">';
      html += '<div class="admin-ai-chat-action-validation-head">';
      html += '<i class="fa-solid fa-shield-halved"></i> <strong>אומת ע"י וולידטור</strong> · ביטחון: ' + escapeHtml(confidence);
      html += "</div>";
      html += '<div class="admin-ai-chat-action-validation-body">' + escapeHtml(validation.analysis) + "</div>";
      if (validation.warnings && validation.warnings.length > 0) {
        html += '<div class="admin-ai-chat-action-validation-warnings"><i class="fa-solid fa-triangle-exclamation"></i> ' + escapeHtml(validation.warnings.join(", ")) + "</div>";
      }
      html += "</div>";
    }
    html += '<div class="admin-ai-chat-action-actions">';
    const resultState = o.executed && o.executionResult ? o.executionResult : null;
    const resultStatus = resultState ? (resultState.status || "") : "";
    const isSuccess = resultStatus === "success";
    const isHistorical = resultStatus === "historical";
    const isCancelled = resultStatus === "cancelled";
    const isFailure = resultState && !isSuccess && !isHistorical && !isCancelled;

    if (resultState) {
      const stateClass = isSuccess ? "success" : (isFailure ? "error" : "info");
      const icon = isSuccess ? "fa-circle-check" : (isFailure ? "fa-circle-xmark" : "fa-circle-info");
      html += '<div class="admin-ai-chat-action-result admin-ai-chat-action-result--' + stateClass + '">';
      html += '<i class="fa-solid ' + icon + '"></i> ';
      html += escapeHtml(resultState.message || (isSuccess ? "בוצע בהצלחה" : (isFailure ? "נכשל" : "—")));
      if (isFailure && resultState.detail) {
        html += '<div class="admin-ai-chat-action-result-detail">' + escapeHtml(String(resultState.detail)) + "</div>";
      }
      html += "</div>";

      if (isFailure && !isHistorical) {
        html += '<button type="button" class="admin-ai-chat-action-retry"><i class="fa-solid fa-rotate-right"></i> נסה שוב</button>';
        html += '<button type="button" class="admin-ai-chat-action-cancel admin-ai-chat-action-cancel--after-fail">סגור</button>';
      } else {
        html += '<button type="button" class="admin-ai-chat-action-btn admin-ai-chat-action-btn--done" disabled>';
        html += '<i class="fa-solid fa-check"></i> ' + (isSuccess ? "בוצע" : (isCancelled ? "בוטל" : "נסגר"));
        html += "</button>";
      }
    } else {
      const remainingMs = actionProposalRemainingMs(actionPayload);
      const alreadyExpired = remainingMs <= 0;
      const ttlClass = alreadyExpired
        ? "admin-ai-chat-action-ttl--expired"
        : (remainingMs <= 60 * 1000 ? "admin-ai-chat-action-ttl--warning" : "");
      html += '<div class="admin-ai-chat-action-ttl ' + ttlClass + '" data-admin-ai-action-ttl>';
      if (alreadyExpired) {
        html += '<i class="fa-solid fa-hourglass-end"></i> ';
        html += '<span data-admin-ai-action-ttl-text>תוקף ההצעה פג — בקש מהסוכן להציע מחדש</span>';
      } else {
        html += '<i class="fa-solid fa-hourglass-half"></i> ';
        html += '<span data-admin-ai-action-ttl-text>תוקף ההצעה: נותרו <strong data-admin-ai-action-ttl-clock>' + formatDurationMs(remainingMs) + '</strong> דקות</span>';
      }
      html += '</div>';
      html += '<button type="button" class="admin-ai-chat-action-btn' + (dangerous ? " admin-ai-chat-action-btn--danger" : "") + '"' + (alreadyExpired ? ' disabled' : '') + '>';
      html += '<i class="fa-solid fa-bolt"></i> ' + escapeHtml(actionBtnLabel(action));
      html += "</button>";
      html += '<button type="button" class="admin-ai-chat-action-cancel">' + escapeHtml("ביטול") + "</button>";
    }
    html += "</div>";
    html += "</div>";

    inner.innerHTML = html;

    if (!resultState) {
      const btn = inner.querySelector(".admin-ai-chat-action-btn");
      const cancelBtn = inner.querySelector(".admin-ai-chat-action-cancel");
      const ttlWrap = inner.querySelector("[data-admin-ai-action-ttl]");
      const ttlTextWrap = inner.querySelector("[data-admin-ai-action-ttl-text]");
      const ttlClock = inner.querySelector("[data-admin-ai-action-ttl-clock]");

      function markExpiredUi() {
        if (btn) {
          btn.disabled = true;
          btn.classList.add("admin-ai-chat-action-btn--expired");
        }
        if (ttlWrap) {
          ttlWrap.classList.remove("admin-ai-chat-action-ttl--warning");
          ttlWrap.classList.add("admin-ai-chat-action-ttl--expired");
        }
        if (ttlTextWrap) {
          ttlTextWrap.innerHTML = '<i class="fa-solid fa-hourglass-end"></i> תוקף ההצעה פג — בקש מהסוכן להציע מחדש';
        }
        clearActionExpiryTimer(bubbleEl);
      }

      if (actionProposalRemainingMs(actionPayload) > 0) {
        bubbleEl.__adminAiActionExpiryTimer = setInterval(() => {
          if (!bubbleEl.isConnected) {
            clearActionExpiryTimer(bubbleEl);
            return;
          }
          const remaining = actionProposalRemainingMs(actionPayload);
          if (remaining <= 0) {
            markExpiredUi();
            return;
          }
          if (ttlClock) ttlClock.textContent = formatDurationMs(remaining);
          if (ttlWrap && remaining <= 60 * 1000) {
            ttlWrap.classList.add("admin-ai-chat-action-ttl--warning");
          }
        }, 1000);
      }

      if (btn) {
        btn.addEventListener("click", () => {
          if (isActionProposalExpired(actionPayload)) {
            markExpiredUi();
            return;
          }
          executeAction(bubbleEl, actionPayload, o.preamble || "");
        });
      }
      if (cancelBtn) {
        cancelBtn.addEventListener("click", () => {
          renderActionCard(bubbleEl, actionPayload, {
            preamble: o.preamble || "",
            executed: true,
            executionResult: { status: "cancelled", message: "הפעולה בוטלה" },
          });
        });
      }
    } else if (isFailure) {
      const retryBtn = inner.querySelector(".admin-ai-chat-action-retry");
      const closeBtn = inner.querySelector(".admin-ai-chat-action-cancel--after-fail");
      if (retryBtn) {
        retryBtn.addEventListener("click", () => {
          if (isActionProposalExpired(actionPayload)) {
            renderActionCard(bubbleEl, actionPayload, {
              preamble: o.preamble || "",
              executed: true,
              executionResult: {
                status: "error",
                message: "תוקף הפעולה פג (מעל 5 דקות). בקש מהסוכן להציע אותה מחדש.",
              },
            });
            return;
          }
          // חזור למצב "טרם הורץ" ואז הפעל מחדש
          renderActionCard(bubbleEl, actionPayload, { preamble: o.preamble || "" });
          executeAction(bubbleEl, actionPayload, o.preamble || "");
        });
      }
      if (closeBtn) {
        closeBtn.addEventListener("click", () => {
          renderActionCard(bubbleEl, actionPayload, {
            preamble: o.preamble || "",
            executed: true,
            executionResult: {
              status: "cancelled",
              message: "הפעולה נסגרה. ניתן לנסח את הבקשה מחדש.",
            },
          });
        });
      }
    }

    const wrap = qs("adminAiChatMessages");
    if (wrap) wrap.scrollTop = wrap.scrollHeight;
  }

  async function executeAction(bubbleEl, actionPayload, preamble, execOpts) {
    const xo = execOpts || {};
    if (isActionProposalExpired(actionPayload)) {
      clearActionExpiryTimer(bubbleEl);
      renderActionCard(bubbleEl, actionPayload, {
        preamble: preamble,
        executed: true,
        executionResult: {
          status: "error",
          message: "תוקף הפעולה פג (מעל 5 דקות). בקש מהסוכן להציע אותה מחדש.",
        },
      });
      return;
    }

    const token = window.ADMIN_AI_CHAT_API_TOKEN || "";
    if (!token) {
      renderActionCard(bubbleEl, actionPayload, {
        preamble: preamble,
        executed: true,
        executionResult: { status: "error", message: "חסר api_token — לא ניתן לבצע את הפעולה" },
      });
      return;
    }
    clearActionExpiryTimer(bubbleEl);

    const inner = bubbleEl && bubbleEl.querySelector(".admin-ai-chat-bubble-inner");
    if (inner) {
      const btn = inner.querySelector(".admin-ai-chat-action-btn");
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מבצע…';
      }
      const cancelBtn = inner.querySelector(".admin-ai-chat-action-cancel");
      if (cancelBtn) cancelBtn.disabled = true;
    }

    const result = await postAgentExecuteRequest(
      buildExecuteBodyFromLeaf(
        {
          action: actionPayload.action,
          table: actionPayload.table,
          id: actionPayload.id,
          data: actionPayload.data,
          sql: actionPayload.sql,
          kind: actionPayload.kind,
        },
        actionPayload.proposedAt,
        state.activeChatId
      )
    );

    renderActionCard(bubbleEl, actionPayload, {
      preamble: preamble,
      executed: true,
      executionResult: result,
    });

    if (!xo.skipSummaryBubble) {
      const summaryText =
        result.status === "success"
          ? "הפעולה בוצעה. " + (result.message || "")
          : "הפעולה נכשלה: " + (result.message || "שגיאה לא ידועה");
      addMessageBubble("assistant", summaryText, { loading: false });
    }

    invalidateChatCache(state.activeChatId);
    await loadHistory();
  }

  function renderQuestionsUI(questions, bubbleEl) {
    const wrap = qs("adminAiChatMessages");
    if (!wrap) return;
    state.pendingQuestions = questions;

    let html = '<div class="admin-ai-chat-questions-panel">';
    html += '<div class="admin-ai-chat-questions-title"><i class="fa-solid fa-circle-question"></i> צריך עוד קצת פרטים</div>';
    questions.forEach((q) => {
      html += '<div class="admin-ai-chat-question-block" data-qid="' + escapeHtml(q.id) + '">';
      html += '<div class="admin-ai-chat-question-text">' + escapeHtml(q.text) + '</div>';
      if (q.options && q.options.length > 0) {
        html += '<div class="admin-ai-chat-question-options">';
        q.options.forEach((opt) => {
          html += '<button type="button" class="admin-ai-chat-question-option" data-qid="' + escapeHtml(q.id) + '" data-value="' + escapeHtml(opt) + '">' + escapeHtml(opt) + '</button>';
        });
        html += '</div>';
      }
      html += '<input type="text" class="admin-ai-chat-question-input" data-qid="' + escapeHtml(q.id) + '" placeholder="או כתבו תשובה חופשית…">';
      html += '</div>';
    });
    html += '<button type="button" class="admin-ai-chat-questions-submit" id="adminAiChatQuestionsSubmit"><i class="fa-solid fa-paper-plane"></i> שלח תשובות</button>';
    html += '</div>';

    wrap.insertAdjacentHTML("beforeend", html);
    setComposerVisible(false);

    const panel = wrap.querySelector(".admin-ai-chat-questions-panel:last-of-type");
    if (!panel) return;
    requestAnimationFrame(() => {
      panel.scrollIntoView({ block: "end", behavior: "smooth" });
      requestAnimationFrame(() => {
        wrap.scrollTop = wrap.scrollHeight;
        setTimeout(() => { wrap.scrollTop = wrap.scrollHeight; }, 120);
      });
    });

    panel.addEventListener("click", (e) => {
      const optBtn = e.target.closest(".admin-ai-chat-question-option");
      if (optBtn) {
        const qid = optBtn.getAttribute("data-qid");
        const value = optBtn.getAttribute("data-value");
        const inputEl = panel.querySelector('.admin-ai-chat-question-input[data-qid="' + qid + '"]');
        if (inputEl) inputEl.value = value;
        const block = panel.querySelector('.admin-ai-chat-question-block[data-qid="' + qid + '"]');
        if (block) {
          block.querySelectorAll(".admin-ai-chat-question-option").forEach((b) => b.classList.remove("selected"));
          optBtn.classList.add("selected");
        }
      }
    });

    const submitBtn = panel.querySelector("#adminAiChatQuestionsSubmit");
    if (submitBtn) {
      submitBtn.addEventListener("click", () => {
        const answers = [];
        let allAnswered = true;
        questions.forEach((q) => {
          const inputEl = panel.querySelector('.admin-ai-chat-question-input[data-qid="' + q.id + '"]');
          const val = inputEl ? inputEl.value.trim() : "";
          if (val === "") allAnswered = false;
          answers.push({ id: q.id, value: val });
        });
        if (!allAnswered) {
          const unanswered = answers.find((a) => a.value === "");
          if (unanswered) {
            const inputEl = panel.querySelector('.admin-ai-chat-question-input[data-qid="' + unanswered.id + '"]');
            if (inputEl) {
              inputEl.focus();
              inputEl.classList.add("admin-ai-chat-question-input--error");
              setTimeout(() => inputEl.classList.remove("admin-ai-chat-question-input--error"), 1500);
            }
          }
          return;
        }

        panel.remove();
        state.pendingQuestions = null;

        let answerText = "";
        answers.forEach((a) => {
          const q = questions.find((qq) => qq.id === a.id);
          answerText += (q ? q.text : a.id) + ": " + a.value + "\n";
        });

        setComposerVisible(true);
        sendMessage(answerText.trim());
      });
    }
  }

  async function loadHistory() {
    const list = qs("adminAiChatHistoryList");
    if (!list) return;
    list.innerHTML = '<div class="admin-ai-chat-history-loading"><i class="fa-solid fa-spinner fa-spin"></i> טוען...</div>';
    try {
      const data = await fetchJson(apiBase + "list_chats.php");
      if (!data.items || data.items.length === 0) {
        list.innerHTML = '<div class="admin-ai-chat-history-empty">עדיין אין שיחות שמורות.</div>';
        return;
      }
      list.innerHTML = "";
      data.items.forEach((item) => {
        const title = escapeHtml(item.title || "שיחה");
        const isActive = Number(item.id) === Number(state.activeChatId);
        if (isActive) {
          setCurrentChatTitle(item.title || "");
        }
        const html = `<article class="admin-ai-chat-history-item ${isActive ? "active" : ""}">
            <button type="button" class="admin-ai-chat-history-open" data-chat-id="${item.id}">
              <strong>${title}</strong><span></span>
            </button>
            <button type="button" class="admin-ai-chat-history-delete" data-delete-chat-id="${item.id}" aria-label="מחיקת שיחה" title="מחיקת שיחה">
              <i class="fa-regular fa-trash-can"></i>
            </button>
          </article>`;
        list.insertAdjacentHTML("beforeend", html);
      });
    } catch (err) {
      list.innerHTML = '<div class="admin-ai-chat-history-empty">שגיאה בטעינת היסטוריה.</div>';
    }
  }

  function invalidateChatCache(chatId) {
    const id = Number(chatId);
    if (id > 0 && state.chatPayloadCache[id]) {
      delete state.chatPayloadCache[id];
    }
  }

  function applyChatPayload(chatId, payload) {
    const messagesEl = qs("adminAiChatMessages");
    if (!messagesEl) return;
    state.activeChatId = Number(chatId);
    messagesEl.innerHTML = "";
    setComposerVisible(true);
    const msgs = payload.messages || [];
    if (msgs.length === 0) {
      setCurrentChatTitle("");
      messagesEl.innerHTML = '<div class="admin-ai-chat-empty">שאלו כל שאלה על פאנל הניהול.</div>';
      return;
    }
    setCurrentChatTitle(payload.title || "");
    msgs.forEach((m) => {
      let content = (m.content || "").replace(/\[\[QUESTIONS_ASKED\]\]/g, "");
      const actionPayload = m.role === "assistant" ? extractActionProposedBlock(content) : null;
      const executionResult = m.role === "assistant" ? extractExecutionResultBlock(content) : null;
      content = stripExecutionResultBlock(stripActionProposedBlock(content)).trim();

      if (m.role === "assistant") {
        if (executionResult) {
          const success = executionResult.status === "success";
          let summary;
          if (success) {
            if (executionResult.action === "sql") {
              const verb = executionResult.verb ? " " + executionResult.verb : "";
              const aff = executionResult.affected != null ? " · הושפעו " + executionResult.affected + " שורות" : "";
              summary = "SQL הורץ" + verb + aff + ". " + (executionResult.message || "");
            } else {
              summary = "הפעולה בוצעה (" + (executionResult.action || "") + " ב-" + (executionResult.table || "") + (executionResult.id ? " · id=" + executionResult.id : "") + "). " + (executionResult.message || "");
            }
          } else {
            summary = "הפעולה נכשלה: " + (executionResult.message || "שגיאה");
          }
          addMessageBubble("assistant", summary, { loading: false });
        } else if (actionPayload) {
          const bubble = addMessageBubble("assistant", "", { loading: false });
          renderActionCard(bubble, actionPayload, {
            preamble: content,
            executed: true,
            executionResult: { status: "historical", message: "הצעה קודמת — הציגה כפתור בזמנו" },
          });
        } else if (content !== "") {
          addMessageBubble("assistant", content, { loading: false });
        }
      } else if (content !== "") {
        addMessageBubble("user", content);
      }
    });
    requestAnimationFrame(() => {
      messagesEl.scrollTop = messagesEl.scrollHeight;
      requestAnimationFrame(() => {
        messagesEl.scrollTop = messagesEl.scrollHeight;
      });
    });
  }

  async function openChat(chatId, opts) {
    const force = opts && opts.force;
    const id = Number(chatId);
    const messagesEl = qs("adminAiChatMessages");
    if (!messagesEl) return;

    if (!force && state.chatPayloadCache[id]) {
      applyChatPayload(id, state.chatPayloadCache[id]);
      await loadHistory();
      setHistoryDrawerOpen(false);
      return;
    }

    messagesEl.innerHTML = '<div class="admin-ai-chat-empty"><i class="fa-solid fa-spinner fa-spin"></i> טוען שיחה...</div>';
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
      messagesEl.innerHTML = '<div class="admin-ai-chat-empty">לא ניתן לטעון את השיחה.</div>';
      setComposerVisible(true);
    }
  }

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
    state.pendingQuestions = null;
    const messagesEl = qs("adminAiChatMessages");
    if (messagesEl) {
      messagesEl.innerHTML = '<div class="admin-ai-chat-empty">שאלו כל שאלה על פאנל הניהול.</div>';
    }
    setCurrentChatTitle("");
    setComposerVisible(true);
    setHistoryDrawerOpen(false);
    const input = qs("adminAiChatInput");
    if (input) input.focus();
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

  async function sendMessage(text) {
    if (state.sending) return;
    if (!text) return;
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
      };
      try {
        streamPayload.page_context = {
          path: typeof window.location.pathname === "string" ? window.location.pathname : "",
          title: (document.title || "").slice(0, 240),
        };
        if (window.ADMIN_AI_PAGE_ENTITY && typeof window.ADMIN_AI_PAGE_ENTITY === "object") {
          streamPayload.page_context.entity = window.ADMIN_AI_PAGE_ENTITY;
        }
      } catch (e) {
        /* ignore */
      }
      streamAbortController = new AbortController();
      const response = await fetch(apiBase + "stream_message.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(streamPayload),
        signal: streamAbortController.signal,
      });

      if (!response.ok || !response.body) {
        let extra = "HTTP " + response.status + " " + response.statusText;
        try {
          const t = await response.text();
          if (t) extra += " · " + t.replace(/\s+/g, " ").slice(0, 240);
        } catch (_e) { /* ignore */ }
        throw new Error(extra);
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = "";
      let assistantText = "";
      let streamHasDoneEvent = false;
      let streamDeepPass = false;
      let streamQuestions = null;
      let streamPreamble = "";
      let streamAction = null;

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
        } else if (eventName === "data_fetching") {
          assistantText = "";
          showAgentWorkingBanner(assistantBubble, {
            title: "שולף נתונים ממסד",
            hint: payload.hint || "",
            variant: "data",
          });
        } else if (eventName === "validating") {
          assistantText = "";
          showAgentWorkingBanner(assistantBubble, {
            title: "מאמת את הפעולה",
            hint: payload.hint || "",
            variant: "validating",
          });
        } else if (eventName === "validation_rejected") {
          showValidationRejectedNotice(assistantBubble, payload.analysis || "", payload.suggestion || "", payload.attempt || 1);
          assistantText = "";
        } else if (eventName === "action") {
          streamAction = payload;
        } else if (eventName === "token") {
          const chunk = payload.text || "";
          const hasThinkingBanner =
            assistantBubble && assistantBubble.querySelector(".admin-ai-chat-thinking-banner");
          const hasAgentBanner =
            assistantBubble && assistantBubble.querySelector(".admin-ai-chat-agent-banner");
          assistantText += chunk;
          updateBubbleStreamingAssistant(assistantBubble, assistantText, {
            clearThinking: !!hasThinkingBanner || !!hasAgentBanner,
          });
        } else if (eventName === "questions") {
          streamQuestions = payload.questions || null;
          streamPreamble = payload.preamble || "";
        } else if (eventName === "error") {
          streamDeepPass = false;
          const msg = (payload && payload.message) ? String(payload.message) : "אירעה שגיאה בקבלת תשובה.";
          const detail = (payload && payload.detail) ? String(payload.detail) : "";
          const code = (payload && payload.code) ? String(payload.code) : "";
          // שומרים אך לא מציגים עדיין — כדי לא לדחוק טקסט שה-token אחריו עוד יחליף.
          // אם לא יגיע token, זה יישאר כ-assistantText הסופי.
          assistantText = msg + (detail ? "\n\nפרטים: " + detail : "") + (code ? "\n(קוד: " + code + ")" : "");
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

      if (streamQuestions && Array.isArray(streamQuestions) && streamQuestions.length > 0) {
        const preamble = streamPreamble || stripQuestionsBlock(assistantText);
        if (preamble) {
          finalizeAssistantBubble(assistantBubble, preamble);
        } else {
          if (assistantBubble) {
            const row = assistantBubble.closest(".admin-ai-chat-bubble-row");
            if (row) row.remove();
          }
        }
        renderQuestionsUI(streamQuestions, assistantBubble);
        invalidateChatCache(state.activeChatId);
        await loadHistory();
      } else if (streamAction) {
        const preamble = stripActionProposedBlock(stripQuestionsBlock(assistantText));
        renderActionCard(assistantBubble, streamAction, { preamble: preamble });
        invalidateChatCache(state.activeChatId);
        await loadHistory();
      } else {
        if (!streamHasDoneEvent && assistantText === "") {
          assistantText = "לא התקבלה תשובה מלאה — השידור הסתיים בלי סיום תקין.\n\nסיבות אפשריות: שגיאת PHP בשרת, חריגה מזמן ריצה, או המודל החזיר תשובה ריקה.\nבדקו ב-ai_api_logs לפרטים, או נסו שוב עם ניסוח אחר.";
        } else if (!streamHasDoneEvent && assistantText !== "") {
          assistantText += "\n\n⚠️ השידור לא הסתיים בצורה תקינה — ייתכן שהתשובה חלקית.";
        }
        finalizeAssistantBubble(assistantBubble, assistantText, { deepPass: streamDeepPass });
        invalidateChatCache(state.activeChatId);
        await loadHistory();
      }
    } catch (err) {
      const errName = err && err.name ? String(err.name) : "";
      if (errName === "AbortError") {
        finalizeAssistantBubble(assistantBubble, "הופסק על ידך — השליחה והחשיבה בוטלו.", { deepPass: false });
      } else {
        const errMsg = (err && err.message) ? String(err.message) : "unknown_error";
        const human = "לא הצלחתי להשיב כרגע.\n\nפרטים טכניים: " + errMsg + "\nנסו שוב בעוד רגע.";
        finalizeAssistantBubble(assistantBubble, human);
      }
    } finally {
      streamAbortController = null;
      setSending(false);
    }
  }

  async function handleSend(e) {
    e.preventDefault();
    if (state.sending) return;
    const input = qs("adminAiChatInput");
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;
    input.value = "";
    await sendMessage(text);
  }

  function toggleModal(open) {
    const modal = qs("adminAiChatModal");
    if (!modal) return;
    state.open = open;
    modal.classList.toggle("open", open);
    modal.setAttribute("aria-hidden", open ? "false" : "true");
    document.body.classList.toggle("no-scroll", open);
    if (!open) {
      setHistoryDrawerOpen(false);
    }
    if (open) {
      const input = qs("adminAiChatInput");
      if (input) input.focus();
    }
  }

  function attachEvents() {
    const launcher = qs("adminAiChatLauncher");
    const closeBtn = qs("adminAiChatClose");
    const modal = qs("adminAiChatModal");
    const newBtn = qs("adminAiChatNewBtn");
    const deleteAllBtn = qs("adminAiChatDeleteAllBtn");
    const historyToggleBtn = qs("adminAiChatHistoryToggle");
    const historyCloseBtn = qs("adminAiChatHistoryClose");
    const historyOverlayBtn = qs("adminAiChatHistoryOverlay");
    const form = qs("adminAiChatForm");
    const input = qs("adminAiChatInput");
    const messages = qs("adminAiChatMessages");
    if (!launcher || !closeBtn || !modal || !newBtn || !form || !messages) return;

    launcher.addEventListener("click", async () => {
      toggleModal(true);
      await onModalOpen();
    });

    closeBtn.addEventListener("click", () => toggleModal(false));
    modal.addEventListener("click", (e) => {
      if (e.target && e.target.getAttribute("data-admin-ai-chat-close") === "1") {
        toggleModal(false);
      }
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && state.open) toggleModal(false);
    });

    form.addEventListener("submit", handleSend);
    const stopBtn = qs("adminAiChatStopBtn");
    if (stopBtn) {
      stopBtn.addEventListener("click", () => {
        if (streamAbortController) {
          try {
            streamAbortController.abort();
          } catch (e) {
            /* ignore */
          }
        }
      });
    }
    messages.addEventListener("click", (e) => {
      const forkBtn = e.target && e.target.closest ? e.target.closest("[data-admin-ai-chat-fork]") : null;
      if (!forkBtn) return;
      e.preventDefault();
      e.stopPropagation();
      const enc = forkBtn.getAttribute("data-fork-payload") || "";
      let decoded = "";
      try {
        decoded = decodeURIComponent(enc);
      } catch (err) {
        return;
      }
      forkMessageToNewChat(decoded);
    });
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

    const history = qs("adminAiChatHistoryList");
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

  window.AdminAIChatBootstrap = function (initialOpen) {
    if (typeof window.__adminAiChatDetachEarlyLauncher === "function") {
      window.__adminAiChatDetachEarlyLauncher();
    }
    if (!window.__adminAiChatBooted) {
      window.__adminAiChatBooted = true;
      attachEvents();
    }
    if (initialOpen) {
      toggleModal(true);
      void onModalOpen();
    }
  };
})();
