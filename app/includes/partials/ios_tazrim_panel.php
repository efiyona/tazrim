<?php
/**
 * תוכן פנימי לאזור «התזרים באייפון» (נטען ב-AJAX).
 * משתנים: $existing_token (array|null עם מפתח token), $shortcuts (מערך שורות מ-ios_shortcut_links)
 */
if (!isset($existing_token)) {
    $existing_token = null;
}
if (!isset($shortcuts) || !is_array($shortcuts)) {
    $shortcuts = [];
}

$has_token = !empty($existing_token['token']);
?>
<div class="ios-tazrim-panel">
    <p class="ios-tazrim-lead">
        <strong>קיצורי דרך</strong> באייפון/אייפד מוסיפים הוצאות ופעולות לתזרים בלי להיכנס לדפדפן בכל פעם. צריך <strong>מפתח חיבור</strong> אחד — אותו מדביקים פעם אחת בכל קיצור חדש.
    </p>

    <?php if (!$has_token): ?>
        <div class="management-block ios-tazrim-block">
            <p class="block-help ios-tazrim-help">אחרי יצירת המפתח תוכלו להעתיק אותו ולהדביק בקיצורים (פעם אחת לכל קיצור).</p>
            <button type="button" id="btn-ios-generate-api" class="btn-primary ios-tazrim-primary-btn" onclick="iosPanelGenerateToken()">
                <i class="fa-solid fa-key"></i> יצירת מפתח חיבור
            </button>
        </div>
    <?php else: ?>
        <div class="management-block ios-tazrim-block">
            <span class="block-label">מפתח החיבור שלך</span>
            <div class="api-token-block-v2">
                <div class="api-wrapper-v2">
                    <input type="text" id="ios-api-token-display" class="ios-api-token-input" value="<?php echo htmlspecialchars($existing_token['token'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    <button type="button" onclick="iosPanelCopyToken()" class="copy-btn-v2" title="העתק מפתח">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                </div>
                <div id="ios-copy-msg" style="display: none;" class="success-text-small">
                    <i class="fa-solid fa-check"></i> המפתח הועתק!
                </div>
                <button type="button" id="btn-ios-delete-api-token" class="btn-api-token-delete" onclick="iosPanelDeleteToken()">
                    <i class="fa-solid fa-trash"></i> מחיקת המפתח מהמערכת
                </button>
            </div>
        </div>

        <div class="ios-shortcuts-section">
            <h4 class="ios-shortcuts-title"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> הוספת קיצורים למכשיר</h4>

            <div class="ios-shortcuts-guide" role="region" aria-label="הוראות הוספת קיצור דרך">
                <p class="ios-shortcuts-guide-lead">לפני הכפתורים — שלושה צעדים:</p>
                <ol class="ios-shortcuts-steps">
                    <li class="ios-shortcuts-step">
                        <span class="ios-shortcuts-step-num" aria-hidden="true">1</span>
                        <div class="ios-shortcuts-step-body">
                            <strong class="ios-shortcuts-step-title">מפתח</strong>
                            <span class="ios-shortcuts-step-text">למעלה לחצו «העתק מפתח» — תדביקו את הערך בשלב 3.</span>
                        </div>
                    </li>
                    <li class="ios-shortcuts-step">
                        <span class="ios-shortcuts-step-num" aria-hidden="true">2</span>
                        <div class="ios-shortcuts-step-body">
                            <strong class="ios-shortcuts-step-title">הוספה לאפליקציה</strong>
                            <span class="ios-shortcuts-step-text">לחצו על אחד הכפתורים למטה. בדף שנפתח — «הוסף קיצור דרך» / Get Shortcut.</span>
                        </div>
                    </li>
                    <li class="ios-shortcuts-step">
                        <span class="ios-shortcuts-step-num" aria-hidden="true">3</span>
                        <div class="ios-shortcuts-step-body">
                            <strong class="ios-shortcuts-step-title">הרצה ראשונה</strong>
                            <span class="ios-shortcuts-step-text">בהפעלה הראשונה של הקיצור, כשמבקשים מפתח — הדביקו. לכל קיצור חדש רק פעם אחת.</span>
                        </div>
                    </li>
                </ol>
            </div>

            <?php if (count($shortcuts) > 0): ?>
                <p class="ios-shortcuts-links-label">בחרו קיצור להוספה:</p>
                <ul class="ios-shortcut-links">
                    <?php foreach ($shortcuts as $row): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="ios-shortcut-add-btn">
                                <span class="ios-shortcut-add-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                                <span class="ios-shortcut-add-text">
                                    <span class="ios-shortcut-add-title"><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="ios-shortcut-add-hint">פתיחה ב־iCloud · הוספה לקיצורי דרך</span>
                                </span>
                                <span class="ios-shortcut-add-chevron" aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="ios-shortcuts-empty block-help">טרם הוגדרו קישורי קיצורי דרך במערכת. כשיופיעו — הם יוצגו כאן.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
