<div id="global-info-modal" class="modal">
    <div class="modal-content" style="max-width: 400px; text-align: center; border-radius: 20px;">
        <div class="modal-header" style="justify-content: center; position: relative; border-bottom: none; padding-bottom: 0;">
            <button type="button" onclick="closeInfoModal()" class="close-modal-btn" style="position: absolute; left: 20px; top: 20px;">&times;</button>
            <div style="font-size: 2.5rem; color: var(--main); margin-bottom: 10px;">
                <i class="fa-solid fa-circle-info"></i>
            </div>
        </div>
        <div class="modal-body" style="padding: 10px 30px 30px;">
            <h3 id="global-info-title" style="margin-bottom: 15px; font-weight: 800; color: var(--text);">כותרת מידע</h3>
            <p id="global-info-content" style="color: var(--text-light); line-height: 1.6; font-size: 0.95rem; margin: 0;">
                כאן יופיע טקסט ההסבר שיוזרק דינמית.
            </p>
            <button onclick="closeInfoModal()" class="btn-primary" style="margin-top: 25px; width: 100%; border-radius: 12px;">הבנתי, תודה!</button>
        </div>
    </div>
</div>

<script>
    // פונקציה לסגירת המודל
    function closeInfoModal() {
        document.getElementById('global-info-modal').style.display = 'none';
    }

    // סגירה בלחיצה מחוץ למודל
    window.addEventListener('click', function(event) {
        const infoModal = document.getElementById('global-info-modal');
        if (event.target == infoModal) {
            closeInfoModal();
        }
    });

    // מאזין לכל הלחיצות על כפתורי המידע ברחבי הדף
    document.addEventListener('click', function(e) {
        // בודק אם לחצנו על אלמנט שיש לו את הקלאס info-trigger או על האייקון שבתוכו
        const trigger = e.target.closest('.info-trigger');
        if (trigger) {
            // שאיבת הנתונים מהכפתור הספציפי
            const title = trigger.getAttribute('data-title');
            const content = trigger.getAttribute('data-content');

            // הזרקת הנתונים למודל המרכזי
            document.getElementById('global-info-title').innerText = title;
            document.getElementById('global-info-content').innerHTML = content;

            // פתיחת המודל
            document.getElementById('global-info-modal').style.display = 'block';
        }
    });
</script>