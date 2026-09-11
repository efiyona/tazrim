<div class="manage-categories-toolbar">
    <div>
        <h2 class="section-subtitle" style="margin:0;">אמצעי תשלום</h2>
        <p class="hint-text" style="margin:4px 0 0;">הוסיפו כרטיסים ואמצעים לבית. נשמרות רק 4 ספרות אחרונות.</p>
    </div>
    <button type="button" class="btn-primary" style="width:max-content;margin:0;padding:8px 20px;font-size:.95rem;box-shadow:0 4px 10px rgba(35,114,39,.2)" onclick="openPaymentMethodEditor()">
        הוספה <i class="fa-solid fa-plus"></i>
    </button>
</div>
<div id="payment-methods-list"></div>

<div id="payment-method-modal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <h3 id="pm-modal-title">אמצעי תשלום חדש</h3>
            <button type="button" class="close-modal-btn" onclick="closePaymentMethodEditor()" aria-label="סגור" title="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="modal-body">
            <form id="payment-method-form" class="form-fields-pill">
                <input type="hidden" name="id" id="pm-id">
                <div class="input-group">
                    <label>סוג אמצעי התשלום</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-wallet"></i>
                        <select name="type" id="pm-type" onchange="toggleCardFields()">
                            <option value="bank_transfer">בנק / העברה</option><option value="credit_card">כרטיס אשראי</option><option value="cash">מזומן</option><option value="check">צ'ק</option><option value="bank_debit">הוראת קבע</option>
                        </select>
                    </div>
                </div>
                <div class="input-group">
                    <label>שם שיופיע במערכת</label>
                    <div class="input-with-icon"><i class="fa-solid fa-pen"></i><input name="name" id="pm-name" required placeholder="למשל: אשראי 3403"></div>
                </div>
                <div id="pm-card-fields" class="pm-card-fields">
                    <div class="input-group"><label>חברת אשראי / מנפיק</label><div class="input-with-icon"><i class="fa-solid fa-building"></i><input name="issuer" id="pm-issuer" placeholder="למשל: max"></div></div>
                    <div class="input-group"><label>4 ספרות אחרונות</label><div class="input-with-icon"><i class="fa-solid fa-credit-card"></i><input name="last4" id="pm-last4" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="3403"></div></div>
                    <p class="hint-text"><i class="fa-solid fa-shield-halved"></i> לא נשמרים מספר כרטיס מלא, תוקף או קוד אבטחה.</p>
                </div>
                <div id="pm-msg" style="display:none;margin-bottom:15px;font-weight:700;text-align:center;padding:10px;border-radius:8px;"></div>
                <button class="btn-primary" id="btn-save-payment-method" type="submit" style="margin-top:15px;"><i class="fa-solid fa-save"></i> שמור אמצעי תשלום</button>
            </form>
        </div>
    </div>
</div>
<style>
.pm-default-badge{display:inline-flex;align-items:center;gap:4px;margin-right:6px;padding:2px 8px;border-radius:999px;background:var(--sub_main-light);color:var(--main);font-size:.72rem;font-weight:700}.pm-card-fields{display:none;padding-top:2px}.payment-method-card-logo{font-size:.72rem;font-weight:800;letter-spacing:.03em;color:var(--text-light)}
</style>
