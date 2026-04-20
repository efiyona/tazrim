## מבנה HTML חובה לקמפיין פופאפ (`popup_campaigns.body_html`)

מסמך זה מתעד את התקן הוויזואלי של גוף ההודעה (`body_html`) בקמפייני פופאפ במערכת «התזרים».
שני מקומות שצריכים להכיר אותו: יוצר התוכן ב-AI מתוך עמוד עריכת קמפיין, והסוכן החכם בפאנל ניהול כשהוא מציע פעולת `create`/`update` לטבלת `popup_campaigns`.

### ארכיטקטורה: טפסים ושמירה (תמיכה כוללת, בטוחה)

- **אין** הרשאה לשמור «לאן שירצה» מתוך HTML בלבד — זה חור אבטחה. במקום זה: **רישום פעולות (whitelist) בשרת** — `app/functions/popup_campaign_actions.php`. כל מזהה ב-`data-tazrim-popup-action` מתורגם ל-handler מאומת שקובע לאיזה טבלה/לוגיקה נכנסים הנתונים.
- **בצד המשתמש** (`assets/includes/popup_campaigns_modal.php`): לחיצה על אלמנט עם `data-tazrim-popup-action="..."` אוספת אוטומטית את כל השדות עם **`name`** באותו גוף פופאפ (או בטופס) ושולחת ל-`app/ajax/popup_campaign_action.php` יחד עם `campaign_id` ו-`action`.
- **הסוכן / עורך HTML** ממלאים רק **מבנה ויזואלי + שמות שדות (`name`) + מזהה פעולה** — לא קוד שרת ולא `fetch` ב-`<script>`.

**להוספת סוג טופס / שמירה חדשים (מפתחים):**

1. מימוש פונקציה חדשה ורישומה ב-`tazrim_popup_campaign_action_handlers()` בקובץ `popup_campaign_actions.php`.
2. עדכון מסמך זה: שם הפעולה, רשימת שדות `name` צפויים, והתנהגות (למשל סימון קמפיין כנקרא אחרי הצלחה).
3. אז ניתן לבקש מהסוכן HTML שמשתמש ב-`data-tazrim-popup-action="<המזהה>"` ובשדות המתאימים.

### כללי תוכן (לפני HTML)

- עברית ברורה וידידותית; ניסוח **נייטרלי מבחינת מגדר** — העדף לשון רבים או ניסוח כללי.
- עקביות עיצובית — סגנון עדין ומקצועי, תואם מערכת פיננסית אמינה.
- כותרת הקמפיין (`title`) — **2–4 מילים בלבד** (עד 30 תווים מקסימום). דוגמאות: «פיצ׳ר חדש זמין», «עדכון חשוב», «שדרוג המערכת». ככל שקצר יותר — עדיף.

### מבנה HTML חובה (body_html)

- עטיפה חיצונית אחת:
  ```html
  <div dir="rtl" style="direction:rtl;text-align:right;font-family:'Assistant',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1a202c;line-height:1.7;font-size:0.95rem;"> … </div>
  ```
- בתוך העטיפה: «כרטיס» ויזואלי:
  - רקע: `background: linear-gradient(135deg, #f0fdf4 0%, #f8fafc 50%, #eff6ff 100%);`
  - `border-radius: 1rem; padding: 1.25rem 1.35rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(15,23,42,0.06);`

### עיצוב ויזואלי (חשוב מאוד!)

- **כותרת פנימית בולטת** בתחילת הכרטיס: `div` עם `font-size:1.1rem; font-weight:800; color:#1e293b; margin-bottom:0.75rem;` — עם אייקון Font Awesome (`<i class="fa-solid fa-XYZ" style="color:#29b669;margin-left:0.4rem;font-size:1.1rem;"></i>`) לפני הטקסט. בחר אייקון מתאים לנושא.
- **פסקאות**: `color:#475569; font-size:0.9rem; margin-bottom:0.6rem;` — תוכן **קצר וברור**, 1–2 משפטים בכל פסקה.
- **רשימה (`ul`/`li`) אם מתאים**: עם אייקוני ✓ בצבע ירוק כנקודות, `list-style:none`, כל `li` עם `padding:0.35rem 0; border-bottom:1px solid #f1f5f9;`.
- **הפרדות ויזואליות**: קו מפריד עדין (`<hr style="border:none;border-top:1px solid #e2e8f0;margin:0.75rem 0;">`) בין סקשנים אם יש יותר מרעיון אחד.
- **הדגשות**: השתמש ב-`<strong style="color:#1e293b;">` ו-`<span style="color:#29b669;font-weight:700;">` לצבע ירוק על מילות מפתח.
- תוכן **קצר וברור** — עד 2 פסקאות קצרות או רשימת `ul` עם 2–4 פריטים. ללא חפירות.
- בלי תגי `html`/`head`/`body`; **בלי `<script>`** (הדפדפן לא מריץ סקריפטים שנכנסים דרך `innerHTML`); בלי `iframe`; בלי `javascript:` בקישורים.

### פעולות מובנות במערכת (במקום סקריפט)

אינטראקציה (שמירת יתרה, וכו׳) מחויבת דרך **תכונות `data-*`** שמזהות את לוגיקת השרת — לא דרך JavaScript מוטמע ב-HTML.

#### עדכון יתרת עו״ש (שדה + כפתור)

- שדה קלט: `input` או `textarea` עם **`name="bank_balance"`** (ערך כמו בהגדרות הבית: מספר, אפשר עם פסיקי אלפים).
- כפתור אחד מסוג **`type="button"`** (או טופס — ראו למטה) עם **`data-tazrim-popup-action="save_bank_balance"`** — המערכת תשלח לשרת את הערך, תעדכן את יתרת הבית, ותסמן את הקמפיין כנקרא (כמו לחיצה על «קראתי»).

דוגמה:

```html
<label for="bank_balance_input" style="display:block;color:#475569;font-size:0.9rem;margin-bottom:0.3rem;">יתרה נוכחית בבנק (₪):</label>
<input type="text" id="bank_balance_input" name="bank_balance" inputmode="decimal" autocomplete="off" placeholder="12,299.77" style="width:100%;padding:10px;border-radius:6px;border:1px solid #ccc;box-sizing:border-box;font-size:0.9rem;">
<button type="button" data-tazrim-popup-action="save_bank_balance" style="display:inline-flex;align-items:center;gap:0.4rem;justify-content:center;margin-top:1rem;padding:12px 28px;border-radius:999px;background:linear-gradient(135deg,#29b669 0%,#22a55b 100%);color:#ffffff;font-weight:700;font-size:0.95rem;border:none;cursor:pointer;font-family:inherit;">
  שמור <i class="fa-solid fa-arrow-left" style="font-size:0.85rem;"></i>
</button>
```

חלופה: `<form data-tazrim-popup-action="save_bank_balance">` עם `input name="bank_balance"` ו-`<button type="submit">` — אותה פעולה.

**פעולות רשומות כרגע בשרת:** `save_bank_balance` (יתרת עו״ש). רשימה מעודכנת: `tazrim_popup_campaign_action_handlers()` ב-`app/functions/popup_campaign_actions.php`. פעולות נוספות יתווספו שם — אותו מפתח `data-tazrim-popup-action` ב-HTML.

### כפתור CTA (אופציונלי, רק אם יש לינק יעד)

יש לכלול **בדיוק קישור אחד** ככפתור CTA בסוף הכרטיס: תג `<a>` עם ה-`href` המדויק שסופק (אסור לשנות, לקצר או להמציא כתובת).

```html
<a href="<HREF>" style="display:inline-flex;align-items:center;gap:0.4rem;justify-content:center;margin-top:1rem;padding:12px 28px;border-radius:999px;background:linear-gradient(135deg,#29b669 0%,#22a55b 100%);color:#ffffff;font-weight:700;font-size:0.95rem;text-decoration:none;font-family:inherit;box-shadow:0 4px 14px rgba(41,182,105,0.3);transition:transform 0.15s;">
  טקסט קצר 2-4 מילים <i class="fa-solid fa-arrow-left" style="font-size:0.85rem;"></i>
</a>
```

הערה ל-RTL: האייקון `fa-arrow-left` מופיע **אחרי** הטקסט ומצביע קדימה (שמאלה).

### דוגמה מינימלית מלאה

```html
<div dir="rtl" style="direction:rtl;text-align:right;font-family:'Assistant',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1a202c;line-height:1.7;font-size:0.95rem;">
  <div style="background:linear-gradient(135deg,#f0fdf4 0%,#f8fafc 50%,#eff6ff 100%);border-radius:1rem;padding:1.25rem 1.35rem;border:1px solid #e2e8f0;box-shadow:0 4px 16px rgba(15,23,42,0.06);">
    <div style="font-size:1.1rem;font-weight:800;color:#1e293b;margin-bottom:0.75rem;">
      <i class="fa-solid fa-sparkles" style="color:#29b669;margin-left:0.4rem;font-size:1.1rem;"></i>
      כותרת פנימית בולטת
    </div>
    <p style="color:#475569;font-size:0.9rem;margin-bottom:0.6rem;">פסקה קצרה שמסבירה את הנושא בכמה מילים בלבד.</p>
    <ul style="list-style:none;padding:0;margin:0.5rem 0;">
      <li style="padding:0.35rem 0;border-bottom:1px solid #f1f5f9;color:#475569;font-size:0.9rem;">✓ נקודה ראשונה</li>
      <li style="padding:0.35rem 0;color:#475569;font-size:0.9rem;">✓ נקודה שנייה</li>
    </ul>
  </div>
</div>
```
