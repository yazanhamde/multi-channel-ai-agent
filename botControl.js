"use strict";
/* ============================================================
   قسم: التحكم بالبوت العام — تشغيل / إيقاف كلياً
   ============================================================ */

VIEW_MODULES.botControl = {
  title: "التحكم بالبوت",
  crumb: "تشغيل أو إيقاف البوت كلياً",
  render(container){
    container.innerHTML = `<div id="botControlContent"></div>`;
    loadBotStatus();
  },
  onRefresh(){ loadBotStatus(); }
};

async function loadBotStatus(){
  const content = $("botControlContent");
  content.innerHTML = `<div class="center-state"><div class="spinner"></div><p>جارٍ التحقق من حالة البوت</p></div>`;
  try{
    const res = await fetch(CFG.API_BASE + "/toggle_global_bot.php", {
      headers: { "Authorization": "Bearer " + state.token }
    });
    const body = await res.json();
    const isActive = !!body.bot_is_active;
    state.botActive = isActive;
    renderBotControl(isActive);
  }catch(err){
    renderCenterState(content, { icon:"alert", title:"صار خطأ", text:"تعذر التحقق من حالة البوت" });
  }
}

function renderBotControl(isActive){
  const content = $("botControlContent");
  content.innerHTML = `
    <div class="row-card" style="align-items:center;text-align:center;padding:32px 20px;gap:18px;">
      <div style="width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;
        background:${isActive ? 'rgba(95,174,126,0.15)' : 'rgba(194,86,74,0.15)'};color:${isActive?'var(--ok)':'var(--danger-lt)'};">
        ${ICONS.power}
      </div>
      <div>
        <h3 style="margin:0 0 4px;font-size:1.05rem;">البوت حالياً ${isActive ? 'شغّال 🟢' : 'موقوف 🔴'}</h3>
        <p style="margin:0;color:var(--ink-faint);font-size:0.84rem;max-width:320px;">
          ${isActive ? 'البوت بيرد تلقائياً على كل رسائل الزباين على واتساب.' : 'البوت موقوف كلياً — ما في ردود تلقائية لحد ما تشغله من جديد.'}
        </p>
      </div>
      <button class="btn ${isActive ? 'btn-danger' : 'btn-primary'}" id="toggleGlobalBotBtn" style="padding:13px 26px;">
        ${isActive ? ICONS.pause + ' إيقاف البوت كلياً' : ICONS.play + ' تشغيل البوت'}
      </button>
    </div>
    <div class="row-card" style="margin-top:14px;">
      <div class="row-card-body" style="display:flex;gap:10px;align-items:flex-start;">
        <span style="color:var(--copper-lt);flex-shrink:0;margin-top:2px;">${ICONS.alert}</span>
        <span>إيقاف البوت كلياً بوقف الردود التلقائية لكل الزباين على واتساب — استخدمه بس لما يكون في داعي فعلي (صيانة، طوارئ...).</span>
      </div>
    </div>
    <div class="toolbar" style="margin-top:14px;">
      ${diagnosisButtonHtml("diagBotBtn")}
    </div>
  `;

  wireDiagnosisButton("diagBotBtn", "/toggle_global_bot.php");

  $("toggleGlobalBotBtn").addEventListener("click", () => {
    if(isActive){
      confirmModal({
        title: "إيقاف البوت كلياً",
        text: "متأكد إنك بدك توقف البوت عن كل الزباين؟ ما حدا رح ياخد رد تلقائي لحد ما تشغله من جديد.",
        confirmLabel: "إيقاف البوت",
        onConfirm: () => toggleGlobalBot('off')
      });
    } else {
      toggleGlobalBot('on');
    }
  });
}

async function toggleGlobalBot(status){
  try{
    const res = await fetch(CFG.API_BASE + "/toggle_global_bot.php", {
      method: "POST",
      headers: { "Authorization": "Bearer " + state.token, "Content-Type": "application/json" },
      body: JSON.stringify({ status })
    });
    const body = await res.json();
    if(body.status !== 'success'){ throw new Error(body.message || 'صار خطأ'); }
    showToast(status === 'on' ? 'تم تشغيل البوت' : 'تم إيقاف البوت', 'ok');
    loadBotStatus();
  }catch(err){
    showToast(err.message || 'تعذر تحديث حالة البوت', 'error');
  }
}
