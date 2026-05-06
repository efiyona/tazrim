/**
 * טעינה עצלה של מודול הצ'אט: רק בלחיצה על כפתור הפתיחה.
 */
(function () {
  "use strict";

  function baseUrl() {
    const b = window.AI_CHAT_BASE_URL || "/";
    return b.endsWith("/") ? b : b + "/";
  }

  function assetsVerQuery() {
    const v = typeof window.AI_CHAT_ASSETS_VER === "string" ? window.AI_CHAT_ASSETS_VER.trim() : "";
    return v !== "" ? "?v=" + encodeURIComponent(v) : "";
  }

  function loadCss(href) {
    if (document.querySelector("link[data-ai-chat-css]")) return;
    const l = document.createElement("link");
    l.rel = "stylesheet";
    l.href = href;
    l.setAttribute("data-ai-chat-css", "1");
    document.head.appendChild(l);
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      if (document.querySelector('script[data-ai-chat-main="1"]')) {
        resolve();
        return;
      }
      const s = document.createElement("script");
      s.src = src;
      s.async = true;
      s.setAttribute("data-ai-chat-main", "1");
      s.onload = function () {
        resolve();
      };
      s.onerror = function () {
        reject(new Error("ai_chat_script"));
      };
      document.head.appendChild(s);
    });
  }

  function injectShell(html) {
    let h = html.trim();
    const wrap = document.createElement("div");
    wrap.innerHTML = h;
    while (wrap.firstElementChild) {
      document.body.appendChild(wrap.firstElementChild);
    }
  }

  async function loadModule() {
    const base = baseUrl();
    const vq = assetsVerQuery();
    loadCss(base + "app/features/ai_chat/assets/ai-chat.css" + vq);
    const shellUrl = base + "app/features/ai_chat/chat_shell.php";
    const res = await fetch(shellUrl, { credentials: "same-origin", cache: "no-store" });
    if (!res.ok) throw new Error("shell");
    const html = await res.text();
    injectShell(html);
    await loadScript(base + "app/features/ai_chat/assets/ai-chat.js" + vq);
    if (typeof window.AIChatBootstrap !== "function") {
      throw new Error("bootstrap_fn");
    }
    window.AI_CHAT_LAZY_LOADED = true;
    window.AIChatBootstrap(true);
  }

  let loadInFlight = null;

  document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("aiChatLauncher");
    if (!btn) return;

    function onEarlyClick(e) {
      if (!window.__TAZRIM_GEMINI_CONFIGURED__) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (typeof window.tazrimRequireGeminiKey === "function") {
          window.tazrimRequireGeminiKey();
        } else if (window.tazrimGeminiKeyModal && window.tazrimGeminiKeyModal.open) {
          window.tazrimGeminiKeyModal.open({});
        }
        return;
      }
      if (window.AI_CHAT_LAZY_LOADED) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      if (loadInFlight) {
        return;
      }
      btn.classList.add("ai-chat-loading");
      btn.setAttribute("aria-busy", "true");
      btn.disabled = true;
      loadInFlight = loadModule()
        .catch(function () {
          alert("לא ניתן לטעון את הצ'אט כרגע. נסו שוב בעוד רגע.");
        })
        .finally(function () {
          loadInFlight = null;
          btn.classList.remove("ai-chat-loading");
          btn.removeAttribute("aria-busy");
          btn.disabled = false;
        });
    }

    window.__aiChatDetachEarlyLauncher = function () {
      btn.removeEventListener("click", onEarlyClick, true);
    };
    btn.addEventListener("click", onEarlyClick, true);
  });
})();
