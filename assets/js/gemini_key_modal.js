/**
 * פופאפ מפתח Gemini — open/close, שלבי מדריך→הדבקה, שמירה, דגל גלובלי.
 */
(function () {
    "use strict";

    function resolveBase() {
        var m = document.getElementById("tazrim-gemini-key-modal");
        if (m && m.getAttribute("data-base")) {
            return m.getAttribute("data-base");
        }
        if (typeof window.TAZRIM_BASE_URL === "string" && window.TAZRIM_BASE_URL) {
            return window.TAZRIM_BASE_URL.replace(/\/?$/, "/");
        }
        return "";
    }

    function apiUrl(path) {
        var b = resolveBase();
        if (!b && typeof window.location !== "undefined") {
            b =
                window.location.origin +
                window.location.pathname.replace(/[^/]*$/, "");
        }
        b = (b || "/").replace(/\/?$/, "/");
        return b + path.replace(/^\//, "");
    }

    function getModal() {
        return document.getElementById("tazrim-gemini-key-modal");
    }

    function modalIsVisible(m) {
        if (!m) return false;
        if (m.classList.contains("tazrim-gemini-key-modal--open")) return true;
        var d = m.style.display;
        return d === "block" || d === "flex";
    }

    function setBodyScrollLock(open) {
        var openDialogs = document.querySelectorAll(".modal");
        var anyOpen = false;
        openDialogs.forEach(function (el) {
            if (modalIsVisible(el)) anyOpen = true;
        });
        document.body.classList.toggle("no-scroll", open || anyOpen);
    }

    function updateGlobalConfigured(configured, mask) {
        window.__TAZRIM_GEMINI_CONFIGURED__ = !!configured;
        window.__TAZRIM_GEMINI_MASK__ = mask || "";
        if (configured) {
            var cl = document.getElementById("aiChatLauncher");
            if (cl) {
                cl.classList.remove("ai-chat-launcher--locked");
                cl.setAttribute("data-gemini-configured", "1");
            }
            var adm = document.getElementById("adminAiChatLauncher");
            if (adm) {
                adm.setAttribute("data-gemini-configured", "1");
                adm.style.opacity = "";
                adm.style.filter = "";
            }
        }
        try {
            document.dispatchEvent(
                new CustomEvent("tazrim:gemini-key-changed", {
                    detail: { configured: !!configured, mask: mask || "" },
                })
            );
        } catch (e) {}
    }

    function showMsg(el, text, kind) {
        if (!el) return;
        el.textContent = text || "";
        el.classList.remove("tazrim-gemini-key-modal__msg--err", "tazrim-gemini-key-modal__msg--ok");
        if (kind === "err") el.classList.add("tazrim-gemini-key-modal__msg--err");
        if (kind === "ok") el.classList.add("tazrim-gemini-key-modal__msg--ok");
    }

    function setGeminiScrollFitMode(stepNum) {
        var sc = document.getElementById("tazrim-gemini-key-scroll");
        if (!sc) return;
        /* שלב 1 קומפקטי ללא גלילה ברוב המסכים; בשלב 2 מאפשרים גלילה אם צריך */
        if (stepNum === 1) {
            sc.classList.add("tazrim-gemini-key-modal__scroll--fit");
        } else {
            sc.classList.remove("tazrim-gemini-key-modal__scroll--fit");
        }
    }

    function goToStep(stepNum) {
        var s1 = document.getElementById("tazrim-gemini-step-1");
        var s2 = document.getElementById("tazrim-gemini-step-2");
        var d1 = document.getElementById("gemini-dot-1");
        var d2 = document.getElementById("gemini-dot-2");
        if (!s1 || !s2) return;
        /* כמו בשקופינג: .shopping-wizard-step.active + נקודות .dot.active */
        if (stepNum === 2) {
            s1.classList.remove("active");
            s2.classList.add("active");
            if (d1) d1.classList.remove("active");
            if (d2) d2.classList.add("active");
            setGeminiScrollFitMode(2);
            setTimeout(function () {
                var inp = document.getElementById("tazrim-gemini-key-input");
                if (inp) inp.focus();
            }, 80);
        } else {
            s2.classList.remove("active");
            s1.classList.add("active");
            if (d2) d2.classList.remove("active");
            if (d1) d1.classList.add("active");
            setGeminiScrollFitMode(1);
        }
    }

    function openModal(reason) {
        var modal = getModal();
        if (!modal) return;
        var msg = document.getElementById("tazrim-gemini-key-msg");
        showMsg(msg, "", "");
        var reasonEl = document.getElementById("tazrim-gemini-open-reason");
        if (reasonEl) {
            if (reason) {
                reasonEl.textContent = reason;
                reasonEl.hidden = false;
            } else {
                reasonEl.textContent = "";
                reasonEl.hidden = true;
            }
        }
        modal.classList.add("tazrim-gemini-key-modal--open");
        modal.style.display = "flex";
        goToStep(1);
        setBodyScrollLock(true);
    }

    function closeModal() {
        var modal = getModal();
        if (!modal) return;
        modal.classList.remove("tazrim-gemini-key-modal--open");
        modal.style.display = "none";
        goToStep(1);
        setBodyScrollLock(false);
        var inp = document.getElementById("tazrim-gemini-key-input");
        if (inp) inp.value = "";
        var msg = document.getElementById("tazrim-gemini-key-msg");
        showMsg(msg, "", "");
        var reasonEl = document.getElementById("tazrim-gemini-open-reason");
        if (reasonEl) {
            reasonEl.textContent = "";
            reasonEl.hidden = true;
        }
        resetGeminiToggleVisibilityBtn();
    }

    /** כפתור עין: מצב מוסתר (ברירת מחדל) */
    function resetGeminiToggleVisibilityBtn() {
        var inp = document.getElementById("tazrim-gemini-key-input");
        var tog = document.getElementById("tazrim-gemini-key-toggle");
        var icon = tog ? tog.querySelector("i") : null;
        if (inp) inp.type = "password";
        if (tog) {
            tog.setAttribute("aria-label", "הצגת המפתח");
            tog.setAttribute("title", "הצגת המפתח");
        }
        if (icon) {
            icon.className = "fa-regular fa-eye";
        }
    }

    function setGeminiToggleVisibilityVisible(isVisible) {
        var inp = document.getElementById("tazrim-gemini-key-input");
        var tog = document.getElementById("tazrim-gemini-key-toggle");
        var icon = tog ? tog.querySelector("i") : null;
        if (!inp || !tog || !icon) return;
        if (isVisible) {
            inp.type = "text";
            icon.className = "fa-regular fa-eye-slash";
            tog.setAttribute("aria-label", "הסתרת המפתח");
            tog.setAttribute("title", "הסתרת המפתח");
        } else {
            inp.type = "password";
            icon.className = "fa-regular fa-eye";
            tog.setAttribute("aria-label", "הצגת המפתח");
            tog.setAttribute("title", "הצגת המפתח");
        }
    }

    function readCsrf() {
        var m = getModal();
        return (m && m.getAttribute("data-csrf")) || "";
    }

    function syncFromDataset() {
        var m = getModal();
        if (!m) return;
        var c = m.getAttribute("data-configured") === "1";
        var mask = m.getAttribute("data-mask") || "";
        updateGlobalConfigured(c, mask);
    }

    function saveKey() {
        var inp = document.getElementById("tazrim-gemini-key-input");
        var msg = document.getElementById("tazrim-gemini-key-msg");
        var btn = document.getElementById("tazrim-gemini-key-save");
        if (!inp || !msg) return;
        var key = (inp.value || "").trim();
        if (key === "") {
            showMsg(msg, "נא להדביק את מפתח ה-API.", "err");
            return;
        }
        if (btn) btn.disabled = true;
        showMsg(msg, "שומרים ובודקים מול Google…", "");
        fetch(apiUrl("app/ajax/user_gemini_key_save.php"), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                api_key: key,
                csrf_token: readCsrf(),
            }),
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (data && data.status === "ok") {
                    showMsg(msg, data.message || "נשמר בהצלחה.", "ok");
                    var m = getModal();
                    if (m) {
                        m.setAttribute("data-configured", "1");
                        if (data.mask) m.setAttribute("data-mask", data.mask);
                    }
                    updateGlobalConfigured(true, data.mask || "");
                    setTimeout(closeModal, 700);
                } else {
                    var code = data && data.code ? String(data.code) : "";
                    var mtext =
                        (data && data.message) ||
                        (code === "gemini_key_invalid"
                            ? "המפתח לא אומת מול Google. בדקו או צרו מפתח חדש."
                            : "לא ניתן לשמור. נסו שוב.");
                    showMsg(msg, mtext, "err");
                }
            })
            .catch(function () {
                showMsg(msg, "שגיאת רשת. נסו שוב.", "err");
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
    }

    document.addEventListener("DOMContentLoaded", function () {
        syncFromDataset();

        var modal = getModal();
        if (!modal) return;

        var closer = document.getElementById("tazrim-gemini-key-close");
        if (closer) closer.addEventListener("click", closeModal);
        modal.addEventListener("click", function (e) {
            if (e.target === modal) closeModal();
        });

        var saveBtn = document.getElementById("tazrim-gemini-key-save");
        if (saveBtn) saveBtn.addEventListener("click", saveKey);

        var nextBtn = document.getElementById("tazrim-gemini-step-next");
        if (nextBtn) nextBtn.addEventListener("click", function () { goToStep(2); });

        var backBtn = document.getElementById("tazrim-gemini-step-back");
        if (backBtn) backBtn.addEventListener("click", function () { goToStep(1); });

        document.addEventListener("keydown", function (e) {
            if (!modalIsVisible(modal)) return;
            if (e.key === "Escape") {
                closeModal();
            }
        });

        var tog = document.getElementById("tazrim-gemini-key-toggle");
        var inp = document.getElementById("tazrim-gemini-key-input");
        if (tog && inp) {
            tog.addEventListener("click", function () {
                setGeminiToggleVisibilityVisible(inp.type === "password");
            });
        }
    });

    /** לשימוש מדפים: true אם יש מפתח, אחרת פותח את המודאל ומחזיר false */
    window.tazrimRequireGeminiKey = function () {
        syncFromDataset();
        if (window.__TAZRIM_GEMINI_CONFIGURED__) {
            return true;
        }
        if (window.tazrimGeminiKeyModal && window.tazrimGeminiKeyModal.isConfigured()) {
            return true;
        }
        if (window.tazrimGeminiKeyModal && typeof window.tazrimGeminiKeyModal.open === "function") {
            window.tazrimGeminiKeyModal.open({});
        }
        return false;
    };

    window.tazrimGeminiKeyModal = {
        open: function (opts) {
            var r = opts && opts.reason ? String(opts.reason) : "";
            openModal(r);
        },
        close: closeModal,
        isConfigured: function () {
            return !!window.__TAZRIM_GEMINI_CONFIGURED__;
        },
        refreshStatus: function () {
            return fetch(apiUrl("app/ajax/user_gemini_key_status.php"))
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (data && data.status === "ok") {
                        updateGlobalConfigured(!!data.configured, data.mask || "");
                        var m = getModal();
                        if (m) {
                            m.setAttribute("data-configured", data.configured ? "1" : "0");
                            m.setAttribute("data-mask", data.mask || "");
                        }
                    }
                    return data;
                });
        },
    };
})();
