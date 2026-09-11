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
                    <input type="hidden" name="type" id="pm-type" value="bank_transfer">
                    <div class="pm-type-select" id="pm-type-select">
                        <button type="button" class="pm-type-trigger" onclick="togglePaymentTypeMenu()" aria-haspopup="listbox" aria-expanded="false"><span><i class="fa-solid fa-building-columns" id="pm-type-icon"></i> <span id="pm-type-label">בנק / העברה</span></span><i class="fa-solid fa-chevron-down"></i></button>
                        <div class="pm-type-options" role="listbox">
                            <button type="button" data-value="bank_transfer" onclick="selectPaymentType('bank_transfer')"><i class="fa-solid fa-building-columns"></i><span>בנק / העברה</span><i class="fa-solid fa-check"></i></button>
                            <button type="button" data-value="credit_card" onclick="selectPaymentType('credit_card')"><i class="fa-solid fa-credit-card"></i><span>כרטיס אשראי</span><i class="fa-solid fa-check"></i></button>
                            <button type="button" data-value="cash" onclick="selectPaymentType('cash')"><i class="fa-solid fa-money-bill-wave"></i><span>מזומן</span><i class="fa-solid fa-check"></i></button>
                            <button type="button" data-value="check" onclick="selectPaymentType('check')"><i class="fa-solid fa-money-check"></i><span>צ'ק</span><i class="fa-solid fa-check"></i></button>
                            <button type="button" data-value="bank_debit" onclick="selectPaymentType('bank_debit')"><i class="fa-solid fa-repeat"></i><span>הוראת קבע</span><i class="fa-solid fa-check"></i></button>
                        </div>
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
.pm-type-select{position:relative}.pm-type-trigger{width:100%;border:1px solid #e5e7eb;background:#fff;border-radius:999px;padding:12px 17px;display:flex;align-items:center;justify-content:space-between;font:inherit;color:var(--text);cursor:pointer}.pm-type-trigger span{display:flex;align-items:center;gap:9px}.pm-type-trigger span>i{color:var(--main)}.pm-type-options{display:none;position:absolute;z-index:20;top:calc(100% + 7px);right:0;left:0;padding:7px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 14px 30px rgba(0,0,0,.13)}.pm-type-select.open .pm-type-options{display:grid}.pm-type-options button{border:0;background:transparent;border-radius:11px;padding:11px 12px;display:grid;grid-template-columns:24px 1fr 20px;align-items:center;text-align:right;gap:8px;font:inherit;cursor:pointer;color:var(--text)}.pm-type-options button:hover,.pm-type-options button.selected{background:var(--sub_main-light);color:var(--main)}.pm-type-options button>i:first-child{color:var(--main)}.pm-type-options button>i:last-child{visibility:hidden}.pm-type-options button.selected>i:last-child{visibility:visible}
</style>
