<?php
/**
 * פופאפ מפתח Gemini — אשף דו־שלבי כמו באשף רשימת הקניות (shopping-welcome-card).
 */
if (!defined('BASE_URL')) {
    require_once dirname(__DIR__, 2) . '/path.php';
}
if (!function_exists('tazrim_app_csrf_token')) {
    require_once ROOT_PATH . '/app/functions/app_session_csrf.php';
}
$gemini_modal_csrf = function_exists('tazrim_app_csrf_token') ? tazrim_app_csrf_token() : '';
$gemini_modal_configured = !empty($tazrim_gemini_configured);
$gemini_modal_mask = isset($tazrim_gemini_mask) ? (string) $tazrim_gemini_mask : '';
?>
<style>
/* פופאפ — מעטפת; תוכן האשף משתמש ב־.user.css כמו בשורות השקופינג (.shopping-welcome-card, וכו׳) */
#tazrim-gemini-key-modal.tazrim-gemini-key-modal {
    z-index: 3400 !important;
}
#tazrim-gemini-key-modal.tazrim-gemini-key-modal--open {
    display: flex !important;
    align-items: center;
    justify-content: center;
    padding-top: max(10px, env(safe-area-inset-top));
    padding-right: max(12px, env(safe-area-inset-right));
    padding-bottom: max(10px, env(safe-area-inset-bottom));
    padding-left: max(12px, env(safe-area-inset-left));
    box-sizing: border-box;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
#tazrim-gemini-key-modal .tazrim-gemini-key-modal__shell.modal-content.tazrim-app-dialog__content {
    position: relative;
    width: min(420px, 92vw);
    max-height: min(88dvh, 540px);
    margin: 0 auto;
    text-align: center;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.14);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 0;
}
#tazrim-gemini-key-modal .tazrim-gemini-key-modal__close {
    position: absolute;
    left: 14px;
    top: 14px;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.25rem;
    min-height: 2.25rem;
    padding: 0;
    border: none;
    background: none;
    border-radius: 999px;
    cursor: pointer;
    color: #64748b;
    font-size: 1.05rem;
}
#tazrim-gemini-key-modal .tazrim-gemini-key-modal__scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    overflow-x: hidden;
}
/* שלב 1 ממוקד — ללא גלילה במסכים רגילים (תוכן קומפקטי) */
#tazrim-gemini-key-modal .tazrim-gemini-key-modal__scroll--fit {
    overflow-y: hidden;
}
@media (max-height: 520px) {
    #tazrim-gemini-key-modal .tazrim-gemini-key-modal__scroll--fit {
        overflow-y: auto;
    }
}

/* --- אותו חוקי עיצוב כמו בשקופינג (user.css) — כאן כפולים כדי שיעבוד גם בפאנל אדמין בלי user.css --- */
#tazrim-gemini-key-modal .shopping-welcome-card.tazrim-gemini-welcome {
    background: #fff;
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
    padding: 42px 16px 20px;
    border-radius: 0;
    box-shadow: none;
    text-align: center;
}
#tazrim-gemini-key-modal .shopping-wizard-step {
    display: none;
}
#tazrim-gemini-key-modal .shopping-wizard-step.active {
    display: block;
    animation: tazrimGeminiWizardFade 0.4s;
}
@keyframes tazrimGeminiWizardFade {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
#tazrim-gemini-key-modal .shopping-stepper-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 30px;
}
#tazrim-gemini-key-modal .shopping-stepper-dots .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ddd;
}
#tazrim-gemini-key-modal .shopping-stepper-dots .dot.active {
    background: var(--main, #38c172);
    width: 25px;
    border-radius: 10px;
    transition: 0.3s;
}
#tazrim-gemini-key-modal .shopping-wizard-icon {
    font-size: 4rem;
    color: var(--main, #38c172);
    margin-bottom: 20px;
}
#tazrim-gemini-key-modal .shopping-field-label {
    display: block;
    margin-top: 14px;
    margin-bottom: 6px;
    font-weight: 700;
    font-size: 0.9rem;
    text-align: right;
    color: var(--text, #334155);
}
#tazrim-gemini-key-modal .shopping-modal-input {
    width: 100%;
    box-sizing: border-box;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    font-size: 1rem;
    font-family: inherit;
}
#tazrim-gemini-key-modal .shopping-recipe-secondary-btn {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 999px;
    padding: 12px 18px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.95rem;
}
#tazrim-gemini-key-modal .shopping-welcome-card .btn-welcome {
    background: var(--main, #38c172);
    color: #fff;
    border: none;
    padding: 14px 30px;
    border-radius: 999px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    margin-top: 25px;
    transition: 0.3s;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    font-family: inherit;
}
@media (hover: hover) {
    #tazrim-gemini-key-modal .shopping-welcome-card .btn-welcome:hover {
        filter: brightness(0.95);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(56, 193, 114, 0.3);
    }
}
#tazrim-gemini-key-modal .shopping-welcome-card .btn-welcome:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* התאמות קומפקטיות בתוך פופאפ */
#tazrim-gemini-key-modal .tazrim-gemini-welcome .shopping-stepper-dots {
    margin-bottom: 12px;
}
#tazrim-gemini-key-modal .tazrim-gemini-welcome .shopping-wizard-icon {
    font-size: 2.35rem;
    margin-bottom: 6px;
}
#tazrim-gemini-key-modal .tazrim-gemini-welcome .btn-welcome {
    margin-top: 12px;
    padding: 12px 22px;
    font-size: 1rem;
}
#tazrim-gemini-key-modal .tazrim-gemini-lead {
    color: #475569;
    line-height: 1.5;
    margin: 0 0 10px;
    padding: 0;
    font-size: 0.92rem;
    text-align: right;
    letter-spacing: 0.01em;
}
/* שלבים כממשק — כרטיסים, לא רשימת טקסט */
#tazrim-gemini-key-modal .tazrim-gemini-steps-visual {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 0 0 8px;
    text-align: right;
}
#tazrim-gemini-key-modal .tazrim-gemini-vstep {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 10px;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    direction: rtl;
}
#tazrim-gemini-key-modal .tazrim-gemini-vstep__badge {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--main, #38c172);
    color: #fff;
    font-weight: 800;
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    margin-top: 2px;
    box-shadow: 0 1px 4px rgba(56, 193, 114, 0.35);
}
#tazrim-gemini-key-modal .tazrim-gemini-vstep__ico {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(56, 193, 114, 0.12);
    color: var(--main, #38c172);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    margin-top: 1px;
}
#tazrim-gemini-key-modal .tazrim-gemini-vstep__body {
    flex: 1;
    min-width: 0;
    text-align: right;
}
#tazrim-gemini-key-modal .tazrim-gemini-vstep__title {
    display: block;
    font-size: 0.84rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}
#tazrim-gemini-key-modal .tazrim-gemini-vstep__hint {
    display: block;
    font-size: 0.74rem;
    font-weight: 500;
    color: #64748b;
    line-height: 1.4;
    margin-top: 2px;
}
#tazrim-gemini-key-modal .tazrim-gemini-vstep__lnk {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--main, #38c172);
    text-decoration: none;
}
@media (hover: hover) {
    #tazrim-gemini-key-modal .tazrim-gemini-vstep__lnk:hover {
        text-decoration: underline;
    }
}
#tazrim-gemini-key-modal .tazrim-gemini-vstep__hint code {
    font-size: 0.92em;
    padding: 0 0.2rem;
    border-radius: 4px;
    background: #f1f5f9;
}
#tazrim-gemini-key-modal .tazrim-gemini-key-modal__reason {
    font-size: 0.82rem;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 8px 10px;
    margin: 0 0 8px;
    line-height: 1.45;
    text-align: right;
}
#tazrim-gemini-key-modal .tazrim-gemini-key-modal__trust-short {
    font-size: 0.76rem;
    color: #64748b;
    line-height: 1.45;
    margin: 0 0 2px;
    padding: 6px 8px;
    background: #f0fdf4;
    border-radius: 8px;
    border: 1px solid #d1fae5;
    text-align: center;
}
#tazrim-gemini-key-modal .tazrim-gemini-key-field-row {
    display: flex;
    gap: 8px;
    align-items: stretch;
    margin-top: 8px;
}
#tazrim-gemini-key-modal .tazrim-gemini-key-field-row .shopping-modal-input {
    flex: 1;
    min-width: 0;
    text-align: left;
    direction: ltr;
    font-size: 16px;
}
#tazrim-gemini-key-modal .tazrim-gemini-toggle-vis {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    min-width: 44px;
    padding: 0;
    border-radius: 50%;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    cursor: pointer;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    transition: background 0.2s, border-color 0.2s, color 0.2s;
}
@media (hover: hover) {
    #tazrim-gemini-key-modal .tazrim-gemini-toggle-vis:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #0f172a;
    }
}
#tazrim-gemini-key-modal .tazrim-gemini-toggle-vis i {
    font-size: 1.12rem;
    pointer-events: none;
}
#tazrim-gemini-key-modal #tazrim-gemini-key-msg.tazrim-gemini-key-modal__msg {
    min-height: 1.35em;
    margin: 10px 0 6px;
    font-size: 0.88rem;
    text-align: right;
}
#tazrim-gemini-key-modal .tazrim-gemini-key-modal__msg--err {
    color: var(--error, #dc2626);
    font-weight: 700;
}
#tazrim-gemini-key-modal .tazrim-gemini-key-modal__msg--ok {
    color: var(--main);
    font-weight: 700;
}
#tazrim-gemini-key-modal .shopping-welcome-card .tazrim-gemini-wizard-actions-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    align-items: stretch;
    margin-top: 22px;
}
#tazrim-gemini-key-modal .shopping-welcome-card .tazrim-gemini-wizard-actions-row .btn-welcome {
    margin-top: 0;
    flex: 1;
    min-width: 140px;
}
#tazrim-gemini-key-modal .shopping-welcome-card .tazrim-gemini-wizard-actions-row .shopping-recipe-secondary-btn {
    flex: 0 1 auto;
    min-width: 100px;
}
</style>
<div id="tazrim-gemini-key-modal" class="modal tazrim-app-dialog tazrim-gemini-key-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="tazrim-gemini-wizard-title" data-base="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/', ENT_QUOTES, 'UTF-8'); ?>" data-csrf="<?php echo htmlspecialchars($gemini_modal_csrf, ENT_QUOTES, 'UTF-8'); ?>" data-configured="<?php echo $gemini_modal_configured ? '1' : '0'; ?>" data-mask="<?php echo htmlspecialchars($gemini_modal_mask, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="modal-content tazrim-app-dialog__content tazrim-gemini-key-modal__shell">
        <button type="button" class="close-modal-btn tazrim-gemini-key-modal__close" id="tazrim-gemini-key-close" aria-label="סגור" title="סגור">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="tazrim-gemini-key-modal__scroll tazrim-gemini-key-modal__scroll--fit" id="tazrim-gemini-key-scroll">
            <div class="shopping-welcome-card tazrim-gemini-welcome">
                <div class="shopping-stepper-dots" aria-hidden="true">
                    <div class="dot active" id="gemini-dot-1"></div>
                    <div class="dot" id="gemini-dot-2"></div>
                </div>

                <div class="shopping-wizard-step active" id="tazrim-gemini-step-1">
                    <div class="shopping-wizard-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></div>
                    <h2 id="tazrim-gemini-wizard-title" style="font-weight: 800; margin-bottom: 6px; font-size: clamp(1.15rem, 4vw, 1.65rem); line-height: 1.25;">מפתח Gemini אישי</h2>
                    <p class="tazrim-gemini-lead">צ׳אט חכם, קניות AI ומתכונים — דורשים <strong>מפתח מ־Google</strong> (ב־AI Studio יש רמת שימוש חינמית לפי מדיניות Google).</p>
                    <p id="tazrim-gemini-open-reason" class="tazrim-gemini-key-modal__reason" hidden></p>

                    <div class="tazrim-gemini-steps-visual" role="list">
                        <div class="tazrim-gemini-vstep" role="listitem">
                            <span class="tazrim-gemini-vstep__badge" aria-hidden="true">1</span>
                            <span class="tazrim-gemini-vstep__ico" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                            <div class="tazrim-gemini-vstep__body">
                                <span class="tazrim-gemini-vstep__title">יצירת המפתח</span>
                                <span class="tazrim-gemini-vstep__hint">מפתח API ב־Google.</span>
                                <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer" class="tazrim-gemini-vstep__lnk">AI Studio — פתיחה בלשונית חדשה <i class="fa-solid fa-arrow-up-left-from-square" style="font-size:0.75em;"></i></a>
                            </div>
                        </div>
                        <div class="tazrim-gemini-vstep" role="listitem">
                            <span class="tazrim-gemini-vstep__badge" aria-hidden="true">2</span>
                            <span class="tazrim-gemini-vstep__ico" aria-hidden="true"><i class="fa-regular fa-copy"></i></span>
                            <div class="tazrim-gemini-vstep__body">
                                <span class="tazrim-gemini-vstep__title">העתקה</span>
                                <span class="tazrim-gemini-vstep__hint">לרוב המפתח מתחיל ב־<code dir="ltr">AIza</code>.</span>
                            </div>
                        </div>
                        <div class="tazrim-gemini-vstep" role="listitem">
                            <span class="tazrim-gemini-vstep__badge" aria-hidden="true">3</span>
                            <span class="tazrim-gemini-vstep__ico" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
                            <div class="tazrim-gemini-vstep__body">
                                <span class="tazrim-gemini-vstep__title">הדבקה ואימות</span>
                                <span class="tazrim-gemini-vstep__hint">בשלב הבא מדביקים כאן.</span>
                            </div>
                        </div>
                    </div>

                    <p class="tazrim-gemini-key-modal__trust-short"><i class="fa-solid fa-lock" aria-hidden="true"></i> שמירה מוצפנת · לשימוש AI של המשתמש המחובר בלבד</p>
                    <button type="button" class="btn-welcome" id="tazrim-gemini-step-next">המשך <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                </div>

                <div class="shopping-wizard-step" id="tazrim-gemini-step-2">
                    <h2 style="font-weight: 800; margin-bottom: 5px; font-size: clamp(1.15rem, 3.5vw, 1.5rem);">הדבקת המפתח</h2>
                    <p style="color: #666; line-height: 1.65; font-size: 0.95rem; margin-bottom: 8px;">אחרי ההדבקה נבדוק את המפתח מול Google. רק אם האימות עובר — נשמור אותו אצלכם בהצפנה.</p>

                    <label class="shopping-field-label" for="tazrim-gemini-key-input" style="margin-top: 8px;">מפתח API</label>
                    <div class="tazrim-gemini-key-field-row">
                        <input type="password" id="tazrim-gemini-key-input" class="shopping-modal-input" dir="ltr" autocomplete="off" placeholder="AIza..." inputmode="text" />
                        <button type="button" class="tazrim-gemini-toggle-vis" id="tazrim-gemini-key-toggle" aria-label="הצגת המפתח" title="הצגת המפתח">
                            <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p id="tazrim-gemini-key-msg" class="tazrim-gemini-key-modal__msg" role="status" aria-live="polite"></p>

                    <div class="tazrim-gemini-wizard-actions-row">
                        <button type="button" class="shopping-recipe-secondary-btn" id="tazrim-gemini-step-back">חזרה</button>
                        <button type="button" class="btn-welcome" id="tazrim-gemini-key-save"><i class="fa-solid fa-check" aria-hidden="true"></i> שמירת מפתח</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/assets/js/gemini_key_modal.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
