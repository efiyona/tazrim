/**
 * טעינה עצלה של מודול הצ'אט לאדמין: רק בלחיצה על כפתור הפתיחה.
 */
(function () {
  "use strict";

  function baseUrl() {
    const b = window.ADMIN_AI_CHAT_BASE_URL || "/";
    return b.endsWith("/") ? b : b + "/";
  }

  function loadCss(href) {
    if (document.querySelector("link[data-admin-ai-chat-css]")) return;
    const l = document.createElement("link");
    l.rel = "stylesheet";
    l.href = href;
    l.setAttribute("data-admin-ai-chat-css", "1");
    document.head.appendChild(l);
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      if (document.querySelector('script[data-admin-ai-chat-main="1"]')) {
        resolve();
        return;
      }
      const s = document.createElement("script");
      s.src = src;
      s.async = true;
      s.setAttribute("data-admin-ai-chat-main", "1");
      s.onload = function () {
        resolve();
      };
      s.onerror = function () {
        reject(new Error("admin_ai_chat_script"));
      };
      document.head.appendChild(s);
    });
  }

  function injectShell(html) {
    const wrap = document.createElement("div");
    wrap.innerHTML = html.trim();
    const node = wrap.firstElementChild;
    if (node) {
      document.body.appendChild(node);
    }
  }

  async function loadModule() {
    const base = baseUrl();
    loadCss(base + "admin/features/ai_chat/assets/admin-ai-chat.css");
    const shellUrl = base + "admin/features/ai_chat/chat_shell.php";
    const res = await fetch(shellUrl, { credentials: "same-origin", cache: "no-store" });
    if (!res.ok) throw new Error("shell");
    const html = await res.text();
    injectShell(html);
    await loadScript(base + "admin/features/ai_chat/assets/admin-ai-chat.js");
    if (typeof window.AdminAIChatBootstrap !== "function") {
      throw new Error("bootstrap_fn");
    }
    window.ADMIN_AI_CHAT_LAZY_LOADED = true;
    window.AdminAIChatBootstrap(true);
  }

  let loadInFlight = null;

  document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("adminAiChatLauncher");
    if (!btn) return;

    function onEarlyClick(e) {
      if (window.ADMIN_AI_CHAT_LAZY_LOADED) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      if (loadInFlight) {
        return;
      }
      btn.classList.add("admin-ai-chat-loading");
      btn.setAttribute("aria-busy", "true");
      btn.disabled = true;
      loadInFlight = loadModule()
        .catch(function () {
          alert("לא ניתן לטעון את הצ'אט כרגע. נסו שוב בעוד רגע.");
        })
        .finally(function () {
          loadInFlight = null;
          btn.classList.remove("admin-ai-chat-loading");
          btn.removeAttribute("aria-busy");
          btn.disabled = false;
        });
    }

    window.__adminAiChatDetachEarlyLauncher = function () {
      btn.removeEventListener("click", onEarlyClick, true);
    };
    btn.addEventListener("click", onEarlyClick, true);
  });
})();
