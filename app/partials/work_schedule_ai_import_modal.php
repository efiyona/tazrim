<?php
/**
 * מודל ייבוא משמרות מצילום — משותף לדף סידור ולצ'אט המשתמש (כשיש הרשאה).
 *
 * משתמש ב־ID קבועים שמנוהלים ב־work_schedule_ai_import.js
 */
declare(strict_types=1);
?>
<div id="work-schedule-ai-modal" class="modal shopping-recipe-modal work-schedule-ai-modal" style="display:none;" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="work-schedule-ai-modal-title">
    <div class="modal-content shopping-recipe-modal__content work-schedule-ai-modal__content">
        <div class="modal-header shopping-recipe-modal__header">
            <h3 id="work-schedule-ai-modal-title">מתמונה לסידור</h3>
            <button type="button" class="close-modal-btn" id="work-schedule-ai-close-btn" aria-label="סגור" title="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="modal-body shopping-recipe-modal__body">
            <div class="shopping-recipe-step work-schedule-ai-step" id="work-schedule-ai-step-input">
                <label class="work-field-label"><span class="work-req">*</span> עבודה</label>
                <div id="work-schedule-ai-job-chips" class="work-store-chip-wrap work-schedule-ai-job-chips" role="group" aria-label="בחירת עבודה"></div>

                <div class="work-schedule-ai-target-period" role="group" aria-labelledby="work-schedule-ai-period-label">
                    <label id="work-schedule-ai-period-label" class="work-field-label" for="work-schedule-ai-import-period"><span class="work-req">*</span> חודש יעד לייבוא (לוח)</label>
                    <p class="work-field-hint work-field-hint--subtle" style="margin-top: 2px; margin-bottom: 6px">בחירה לפי חודש ושנה בלבד — ללא יום. תאריכי המשמרות יושבו על חודש זה.</p>
                    <select id="work-schedule-ai-import-period" class="work-input work-schedule-ai-import-period" aria-describedby="work-schedule-ai-file-hint"></select>
                </div>

                <input type="file" id="work-schedule-ai-images" class="shopping-recipe-file-input" accept="image/png,image/jpeg,image/webp" multiple aria-describedby="work-schedule-ai-file-hint">
                <button type="button" class="shopping-recipe-upload-btn" id="work-schedule-ai-upload-btn">
                    <i class="fa-regular fa-images" aria-hidden="true"></i>
                    צירוף תמונות
                </button>
                <p id="work-schedule-ai-file-hint" class="shopping-recipe-file-help">ניתן צילום מסך מהסידור. עד 8 קבצים. כל קובץ עד 6 מגה־בייט. פורמטים מותרים: ‎JPEG‎, ‎PNG‎, ‎WEBP‎ בלבד.</p>
                <div id="work-schedule-ai-files-list" class="shopping-recipe-files-list work-schedule-ai-files-list"></div>
                <div id="work-schedule-ai-msg" class="shopping-modal-msg" style="display: none;"></div>
                <div id="work-schedule-ai-upload-live" class="visually-hidden" aria-live="assertive" aria-atomic="true"></div>
                <div class="shopping-recipe-status work-schedule-ai-status" id="work-schedule-ai-status" style="display: none;" aria-live="polite"></div>
                <div class="shopping-recipe-actions">
                    <button type="button" class="btn-primary shopping-modal-submit" id="work-schedule-ai-extract-btn">חילוץ משמרות</button>
                </div>
            </div>

            <div class="shopping-recipe-step work-schedule-ai-step" id="work-schedule-ai-step-review" style="display: none;">
                <div id="work-schedule-ai-review-summary" class="work-schedule-ai-review-summary" style="display: none;"></div>
                <div id="work-schedule-ai-review-thumbs" class="work-schedule-ai-review-thumbs" style="display: none;"></div>
                <div id="work-schedule-ai-dup-banner" class="work-schedule-ai-notice work-schedule-ai-notice--dup" style="display: none;" role="region" aria-labelledby="work-schedule-ai-dup-banner-title"></div>
                <div id="work-schedule-ai-conflict-banner" class="work-schedule-ai-notice work-schedule-ai-notice--conflict" style="display: none;" role="region" aria-labelledby="work-schedule-ai-conflict-banner-title"></div>
                <div id="work-schedule-ai-dup-actions" class="work-schedule-ai-dup-actions" style="display: none;">
                    <button type="button" class="shopping-recipe-secondary-btn" id="work-schedule-ai-dedupe-btn">הסר כפילויות (שמירת משמרת ראשונה בכל קבוצה)</button>
                </div>
                <div id="work-schedule-ai-warnings" class="work-schedule-ai-warnings" style="display: none;"></div>
                <p class="work-field-hint work-field-hint--subtle" style="margin-top:0">ניתן לערוך תאריכים ושעות ולבחור סוג משמרת. משמרות שעות תואמות לברירות המחדל שמוגדרות בעבודה יסומכו אוטומטית. תאריכים נבדקים מול לוח השנה כשידוע יום בשבוע מהתמונה.</p>
                <div id="work-schedule-ai-shifts-head" class="work-schedule-ai-shifts-head">
                    <span>תאריך</span>
                    <span>התחלה</span>
                    <span>סיום</span>
                    <span>סוג</span>
                    <span class="work-schedule-ai-shifts-head__note">הערה</span>
                    <span class="work-schedule-ai-shifts-head__act">&nbsp;</span>
                </div>
                <div id="work-schedule-ai-shifts-list" class="work-schedule-ai-shifts-list"></div>
                <div class="work-schedule-ai-review-toolbar">
                    <button type="button" class="shopping-recipe-secondary-btn work-schedule-ai-add-row-btn" id="work-schedule-ai-add-shift-btn"><i class="fa-solid fa-plus" aria-hidden="true"></i> הוספת משמרת לרשימה</button>
                </div>
                <div id="work-schedule-ai-review-msg" class="shopping-modal-msg" style="display: none;"></div>
                <div class="shopping-recipe-actions shopping-recipe-actions--review">
                    <button type="button" class="shopping-recipe-secondary-btn" id="work-schedule-ai-back-btn">חזרה</button>
                    <button type="button" class="btn-primary shopping-modal-submit" id="work-schedule-ai-save-btn">שמירה ללוח</button>
                </div>
            </div>
        </div>
    </div>
</div>
