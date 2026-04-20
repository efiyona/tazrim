## מבנה HTML חובה לקמפיין פופאפ (`popup_campaigns.body_html`)

מסמך זה מתעד את התקן הוויזואלי של גוף ההודעה (`body_html`) בקמפייני פופאפ במערכת «התזרים».
שני מקומות שצריכים להכיר אותו: יוצר התוכן ב-AI מתוך עמוד עריכת קמפיין, והסוכן החכם בפאנל ניהול כשהוא מציע פעולת `create`/`update` לטבלת `popup_campaigns`.

### ארכיטקטורה: טפסים ושמירה (בטוחה)

- **אין** הרשאה לשמור «לאן שירצה» מתוך HTML בלבד — זה חור אבטחה.
- **דרך מומלצת — `form_schema` (JSON בקמפיין):** בשדה `popup_campaigns.form_schema` מגדירים `handler` מאושר בשרת + רשימת `fields` (שם, סוג, חובה, אורך מקסימלי). ב־`body_html` משתמשים ב־**`data-tazrim-popup-action="submit"`** (או טופס עם אותו מאפיין); המודל שולח את כל ה־`name` ל־`app/ajax/popup_campaign_action.php` עם `action: "submit"`, והשרת מאמת מול הסכמה ומבצע:
  - **`submission_store`** — שמירת JSON של השדות בטבלה `popup_campaign_form_submissions` (הגשות לפי קמפיין/משתמש).
  - **`bank_balance`** — עדכון יתרת בנק (כמו בעבר), עם שדה `bank_balance` (אפשר `fields: []` — אז משתמע שדה אחד `bank_balance`).
- **לוגיקה:** `app/functions/popup_campaign_form_schema.php` — אימות סכמה ו-handlers.
- **מצב ישן (ללא `form_schema`):** `data-tazrim-popup-action="save_bank_balance"` עדיין נתמך לקמפיינים ישנים.

**להוספת handler חדש בשרת (מפתחים):**

1. הרחבת `tazrim_popup_campaign_validate_form_schema_shape()` + `tazrim_popup_campaign_process_form_schema_submit()` ב־`popup_campaign_form_schema.php`.
2. עדכון מסמך זה והסוכן.

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

### רספונסיביות (מובייל)

- על העטיפה החיצונית: `width:100%;max-width:100%;box-sizing:border-box;`.
- על הכרטיס הפנימי: `width:100%;max-width:100%;box-sizing:border-box;`.
- שדות קלט: `width:100%;max-width:100%;box-sizing:border-box;`.
- שורת כפתורים (שמירה / לא מעוניין): עטיפה עם  
  `display:flex;flex-wrap:wrap;gap:0.75rem;justify-content:center;align-items:center;margin-top:1rem;`  
  כדי למרכז במסכים צרים ולשבור שורה לפי הצורך.
- כפתורים: `flex:1 1 auto;min-width:min(100%, 10rem);` או `width:100%` בתוך העטיפה אם רוצים עמודה במובייל — לפי העיצוב.

### כפתור «לא עכשיו» / סגירה בלי שמירה (אופציונלי, ניסוח פר־קמפיין)

- **מופיע רק אם מוסיפים אותו ל־`body_html`** — אין כפתור כזה בברירת מחדל; כל קמפיין יכול לכלול או לא לכלול.
- **הטקסט על הכפתור הוא מה שתבחרו** (למשל «לא כרגע», «אמשיך אחר כך», «דלג»).
- מי שלא רוצה למלא טופס — לוחץ כאן: **`type="button"`** + **`data-tazrim-popup-dismiss="ack"`** — רק סימון קריאה (כמו «קראתי»), **בלי** שמירת שדות.

```html
<button type="button" data-tazrim-popup-dismiss="ack" style="...">הטקסט שאני בוחר לקמפיין זה</button>
```

**שליחת טופס מוצלחת** (`submit` / `save_bank_balance` לפי הסכמה): אחרי שמירה מוצלחת בשרת הקמפיין מסומן כנקרא — **לא** מסומן אם הוולידציה נכשלה או השרת החזיר שגיאה.

### טופסים ושליחה (במקום סקריפט)

- **עם `form_schema`:** כפתור/טופס עם **`data-tazrim-popup-action="submit"`** — השרת קורא את `form_schema` של הקמפיין ומאמת שדות.
- **סוגי שדות נתמכים ב־`fields`:** `text`, `textarea`, `number`, `email`, `tel`, `checkbox`.
- **שמות שדות:** רק אותיות אנגליות קטנות, מספרים ו־`_` (למשל `feedback`, `rating_1`).

#### דוגמת `form_schema` — משוב לטבלת הגשות

```json
{
  "handler": "submission_store",
  "fields": [
    {"name": "feedback", "type": "textarea", "required": false, "maxLength": 2000}
  ]
}
```

#### דוגמת `form_schema` — יתרת בנק

```json
{
  "handler": "bank_balance",
  "fields": []
}
```
או עם הגדרה מפורשת: `fields: [{"name":"bank_balance","type":"text","required":true,"maxLength":40}]`

#### דוגמת HTML עם `submit` (יתרה)

```html
<input type="text" name="bank_balance" inputmode="decimal" autocomplete="off" style="width:100%;box-sizing:border-box;...">
<button type="button" data-tazrim-popup-action="submit" style="...">שמור</button>
```

#### מצב ישן — `save_bank_balance` בלי `form_schema`

כפתור עם **`data-tazrim-popup-action="save_bank_balance"`** — רק כשאין `form_schema` בקמפיין, או כשקמפיין מוגדר עם `handler` `bank_balance` (השרת גם מקבל `save_bank_balance` לתאימות).

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
