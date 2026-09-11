<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');
require_once ROOT_PATH . '/assets/includes/user_css_href.php';
require_once ROOT_PATH . '/assets/includes/pwa_no_cache_headers.php';

$home_id = (int) $_SESSION['home_id'];
$home_data = selectOne('homes', ['id' => $home_id]);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>אמצעי תשלום | התזרים</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(tazrim_user_css_href(), ENT_QUOTES, 'UTF-8'); ?>">
    <script src="<?php echo BASE_URL; ?>assets/js/tazrim_dialogs.js" defer></script>
    <script src="<?php echo BASE_URL; ?>assets/js/global_modals.js" defer></script>
</head>
<body class="bg-gray">
<div class="sidebar-overlay" id="overlay"></div>
<div class="dashboard-container">
    <?php include(ROOT_PATH . '/assets/includes/sidebar_bavbar.php'); ?>
    <div class="content-wrapper">
        <div class="page-header-actions" style="margin-bottom:25px;">
            <div>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'pages/settings/manage_home.php', ENT_QUOTES, 'UTF-8'); ?>" style="display:inline-flex;align-items:center;gap:7px;color:var(--text-light);text-decoration:none;font-weight:600;margin-bottom:8px;">
                    <i class="fa-solid fa-arrow-right"></i> ניהול הבית
                </a>
                <h1 class="section-title" style="margin-bottom:0;">אמצעי תשלום</h1>
            </div>
        </div>
        <div class="management-grid">
            <div class="card full-width-card">
                <div id="manage-home-payment-methods-panel">
                    <?php include ROOT_PATH . '/app/includes/partials/manage_home_payment_methods_panel.php'; ?>
                </div>
            </div>
        </div>
    </div>
    </main>
</div>
<script>
let paymentMethods=[];
const pmLabels={bank_transfer:'בנק / העברה',credit_card:'כרטיס אשראי',cash:'מזומן',check:"צ'ק",bank_debit:'הוראת קבע'};
const pmIcons={bank_transfer:'fa-building-columns',credit_card:'fa-credit-card',cash:'fa-money-bill-wave',check:'fa-money-check',bank_debit:'fa-repeat'};
function loadPaymentMethods(){fetch('../../app/ajax/payment_methods.php').then(r=>r.json()).then(x=>{if(x.status!=='success')throw Error(x.message);paymentMethods=x.data;renderPaymentMethods();}).catch(e=>console.error(e));}
function paymentMethodPublicName(pm){return String(pm.name||'').trim()||'אמצעי תשלום';}
function renderPaymentMethods(){const el=document.getElementById('payment-methods-list');if(!el)return;el.innerHTML=paymentMethods.map(pm=>`<div class="transaction-item ${pm.is_active==1?'income':''}" onclick="openPaymentMethodEditor(${pm.id})" style="cursor:pointer;${pm.is_active==1?'':'opacity:.55'}"><div class="transaction-info"><div class="cat-icon-wrapper"><i class="fa-solid ${pmIcons[pm.type]||'fa-wallet'}"></i></div><div class="details"><span class="desc">${escapeHtml(paymentMethodPublicName(pm))} ${Number(pm.is_default)===1?'<span class="pm-default-badge"><i class="fa-solid fa-check"></i> ברירת מחדל</span>':''}</span><span class="date">${pmLabels[pm.type]||pm.type}${pm.issuer?' · '+escapeHtml(pm.issuer):''}${pm.is_active==1?'':' · לא פעיל'}</span></div></div><div class="transaction-actions"><div class="transaction-row-actions">${Number(pm.is_active)===1&&Number(pm.is_default)!==1?`<button type="button" class="transaction-action-pill" onclick="event.stopPropagation();paymentMethodAction('set_default',${pm.id})" title="הגדר כברירת מחדל"><i class="fa-solid fa-star"></i></button>`:''}<div class="transaction-action-pill" title="ערוך אמצעי תשלום"><i class="fa-solid fa-pen"></i></div>${pm.is_active==1?`<button type="button" class="transaction-action-pill transaction-action-pill--danger" onclick="event.stopPropagation();openPaymentMethodDelete(${pm.id})" title="הסר אמצעי תשלום"><i class="fa-solid fa-trash-can"></i></button>`:''}</div></div></div>`).join('');}
function escapeHtml(v){const d=document.createElement('div');d.textContent=v||'';return d.innerHTML;}
function openPaymentMethodEditor(id){const pm=paymentMethods.find(x=>Number(x.id)===Number(id));document.getElementById('pm-id').value=pm?pm.id:'';selectPaymentType(pm?pm.type:'bank_transfer',false);document.getElementById('pm-name').value=pm?pm.name:'';document.getElementById('pm-issuer').value=pm?.issuer||'';document.getElementById('pm-last4').value=pm?.last4||'';document.getElementById('pm-modal-title').textContent=pm?'עריכת אמצעי תשלום':'אמצעי תשלום חדש';const msg=document.getElementById('pm-msg');msg.style.display='none';msg.textContent='';toggleCardFields();document.getElementById('payment-method-modal').style.display='block';}
function closePaymentMethodEditor(){document.getElementById('payment-method-modal').style.display='none';}
function selectPaymentType(value,close=true){const input=document.getElementById('pm-type'),menu=document.getElementById('pm-type-select'),option=menu.querySelector(`[data-value="${value}"]`);if(!option)return;input.value=value;document.getElementById('pm-type-label').textContent=pmLabels[value];document.getElementById('pm-type-icon').className=`fa-solid ${pmIcons[value]||'fa-wallet'}`;menu.querySelectorAll('[data-value]').forEach(x=>x.classList.toggle('selected',x.dataset.value===value));if(close)menu.classList.remove('open');menu.querySelector('.pm-type-trigger').setAttribute('aria-expanded','false');toggleCardFields();}
function togglePaymentTypeMenu(){const menu=document.getElementById('pm-type-select'),open=!menu.classList.contains('open');menu.classList.toggle('open',open);menu.querySelector('.pm-type-trigger').setAttribute('aria-expanded',open?'true':'false');}
function toggleCardFields(){document.getElementById('pm-card-fields').style.display=document.getElementById('pm-type').value==='credit_card'?'block':'none';}
document.addEventListener('click',e=>{const menu=document.getElementById('pm-type-select');if(menu&&!menu.contains(e.target)){menu.classList.remove('open');menu.querySelector('.pm-type-trigger').setAttribute('aria-expanded','false');}});

let paymentMethodDeleteId=0;
function openPaymentMethodDelete(id){paymentMethodDeleteId=Number(id);document.getElementById('payment-method-delete-modal').style.display='block';}
function closePaymentMethodDelete(){document.getElementById('payment-method-delete-modal').style.display='none';paymentMethodDeleteId=0;}
function confirmPaymentMethodDelete(mode){const id=paymentMethodDeleteId;if(!id)return;const f=new FormData();f.append('action','delete');f.append('id',id);f.append('delete_mode',mode);fetch('../../app/ajax/payment_methods.php',{method:'POST',body:f}).then(r=>r.json()).then(x=>{if(x.status!=='success')throw Error(x.message);paymentMethods=x.data.map(pm=>({...pm,is_default:Number(pm.is_default),is_active:Number(pm.is_active)}));renderPaymentMethods();closePaymentMethodDelete();}).catch(e=>tazrimAlert({title:'לא ניתן למחוק',message:e.message}));}
function paymentMethodAction(action,id){const run=()=>{if(action==='set_default'){paymentMethods=paymentMethods.map(pm=>({...pm,is_default:Number(pm.id)===Number(id)?1:0}));renderPaymentMethods();}const f=new FormData();f.append('action',action);f.append('id',id);return fetch('../../app/ajax/payment_methods.php',{method:'POST',body:f}).then(r=>r.json()).then(x=>{if(x.status!=='success')throw Error(x.message);paymentMethods=x.data.map(pm=>({...pm,is_default:Number(pm.is_default),is_active:Number(pm.is_active)}));renderPaymentMethods();});};if(action==='deactivate'&&typeof tazrimConfirm==='function'){tazrimConfirm({title:'הסרת אמצעי תשלום',message:'האם להסיר את אמצעי התשלום? פעולות עבר יישמרו.',confirmText:'הסר',cancelText:'ביטול',danger:true}).then(ok=>{if(ok)run().catch(e=>tazrimAlert({title:'לא ניתן להסיר',message:e.message}));});}else run().catch(e=>typeof tazrimAlert==='function'?tazrimAlert({title:'שגיאה',message:e.message}):alert(e.message));}
document.getElementById('payment-method-form')?.addEventListener('submit',e=>{e.preventDefault();const btn=document.getElementById('btn-save-payment-method'),msg=document.getElementById('pm-msg');btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> שומר...';const f=new FormData(e.currentTarget);f.append('action','save');fetch('../../app/ajax/payment_methods.php',{method:'POST',body:f}).then(r=>r.json()).then(x=>{if(x.status!=='success')throw Error(x.message);paymentMethods=x.data;renderPaymentMethods();closePaymentMethodEditor();}).catch(err=>{msg.style.display='block';msg.style.background='#fee2e2';msg.style.color='var(--error)';msg.textContent=err.message;}).finally(()=>{btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-save"></i> שמור אמצעי תשלום';});});
document.addEventListener('DOMContentLoaded',loadPaymentMethods);
window.addEventListener('click',e=>{if(e.target===document.getElementById('payment-method-modal'))closePaymentMethodEditor();});
</script>
</body>
</html>
