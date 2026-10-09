/**
 * modules/enroll/admin.js
 *
 * ลงทะเบียน route และ helper ของโมดูลรับสมัครนักเรียน
 *
 * หน้าของผู้สมัคร (/, /home, /enroll-edit, /enroll-result, /enroll-announce, /enroll-privacy)
 * เป็น requireAuth: false เพราะผู้สมัครไม่ใช่สมาชิกของระบบ — กุญแจเข้าถึงใบสมัครคือ link
 * 32 ตัวอักษรใน query string ที่ฝั่ง API ตรวจทุกครั้ง
 *
 * หน้าของเจ้าหน้าที่แยกกันทั้งหน้าและ API (ชื่อตามกติกา: พหูพจน์ = ตาราง เอกพจน์ = ฟอร์ม)
 *   /dashboard          หน้าแรกของระบบ (การ์ดจาก hook initDashboard)
 *   /enrolls            ตารางผู้สมัคร            api/enroll/enrolls
 *   /enroll             ดู/แก้ไขใบสมัคร (ฟอร์ม)   api/enroll/enroll
 *   /enroll-applicants  รายงานผู้สมัคร           api/enroll/applicants
 */
EventManager.on('router:initialized', () => {
  // หน้าแรกของเว็บ = รายละเอียดการรับสมัคร เปิดได้โดยไม่ต้องเข้าระบบ (หน้า Home ของระบบเดิม)
  // เจ้าหน้าที่ที่เข้าระบบแล้วไปหน้าแรกของระบบ (/dashboard) แทน หลังเข้าระบบ (afterLogin = '/')
  // จึงมาถึงหน้าทำงานเหมือนเดิม
  RouterManager.register('/', {
    template: 'enroll/home.html',
    title: '{LNG_Enroll}',
    requireAuth: false,
    beforeEnter: (params, current, authManager) => {
      if (authManager && typeof authManager.isAuthenticated === 'function' && authManager.isAuthenticated()) {
        return '/dashboard';
      }
    }
  });

  // หน้าแรกของระบบ (เทมเพลตกลาง index.html) — '/' เป็นของผู้สมัคร จึงย้ายมาอยู่ที่นี่
  // menuPath '/' ให้เมนูหน้าแรกของระบบสว่างเหมือนเดิม
  RouterManager.register('/dashboard', {
    template: 'index.html',
    title: '{LNG_Dashboard}',
    menuPath: '/',
    requireAuth: true
  });

  // หน้าเดียวกับ '/' แต่ไม่พาผู้ที่เข้าระบบไปที่อื่น ใช้ดูหน้าจริงหลังแก้ไขเนื้อหา
  RouterManager.register('/home', {
    template: 'enroll/home.html',
    title: '{LNG_Enroll}',
    requireAuth: false
  });

  RouterManager.register('/enrolls', {
    template: 'enroll/enrolls.html',
    title: '{LNG_List of} {LNG_Enroll}',
    requireAuth: true
  });

  // ใบสมัครของเจ้าหน้าที่ (เปิดจากตาราง) — แยกจากหน้าที่ผู้สมัครกรอก (/enroll-edit)
  RouterManager.register('/enroll', {
    template: 'enroll/enroll.html',
    title: '{LNG_Registration form}',
    menuPath: '/enrolls',
    requireAuth: true
  });

  RouterManager.register('/enroll-announce', {
    template: 'enroll/announce.html',
    title: '{LNG_Result announcement}',
    requireAuth: false
  });

  RouterManager.register('/enroll-privacy', {
    template: 'enroll/privacy.html',
    title: '{LNG_Privacy Policy}',
    requireAuth: false
  });

  RouterManager.register('/enroll-pages', {
    template: 'enroll/pages.html',
    title: '{LNG_Page details}',
    requireAuth: true
  });

  RouterManager.register('/enroll-applicants', {
    template: 'enroll/applicants.html',
    title: '{LNG_Report} {LNG_Enroll}',
    requireAuth: true
  });

  RouterManager.register('/enroll-edit', {
    template: 'enroll/register.html',
    title: '{LNG_Registration form}',
    requireAuth: false
  });

  RouterManager.register('/enroll-result', {
    template: 'enroll/result.html',
    title: '{LNG_Result} & {LNG_Print}',
    requireAuth: false
  });

  RouterManager.register('/enroll-levels', {
    template: 'enroll/levels.html',
    title: '{LNG_List of} {LNG_Education level}',
    requireAuth: true
  });

  RouterManager.register('/enroll-plans', {
    template: 'enroll/plans.html',
    title: '{LNG_List of} {LNG_Study plan}',
    requireAuth: true
  });

  RouterManager.register('/enroll-settings', {
    template: 'enroll/settings.html',
    title: '{LNG_Module Settings} {LNG_Enroll}',
    requireAuth: true
  });
});

/**
 * หน้าแผนการเรียน: เปลี่ยนระดับชั้นแล้วโหลดแผนของระดับนั้น
 *
 * แผนการเรียนผูกกับระดับชั้น ตารางจึงแสดงได้ทีละระดับ การเปลี่ยน select
 * จึงเป็นการเปลี่ยนหน้า (?level=) ไม่ใช่การกรองในตาราง
 *
 * @param {HTMLElement} form ฟอร์มที่มี data-on-load
 * @returns {Function} ฟังก์ชันถอด event ตอนออกจากหน้า
 */
function initEnrollPlans(form) {
  const select = form.querySelector('[data-role="level"]');
  if (!select) {
    return;
  }
  const onChange = () => {
    RouterManager.navigate('/enroll-plans?level=' + encodeURIComponent(select.value));
  };
  select.addEventListener('change', onChange);

  return () => select.removeEventListener('change', onChange);
}

/**
 * เลขประจำตัวประชาชนไทย: หลักสุดท้ายต้องตรงกับหลักตรวจสอบ (ใช้กับ data-validate="thaiIdCard")
 *
 * ฝั่ง API ตรวจซ้ำอีกครั้งเสมอ ที่นี่แค่บอกผู้สมัครตั้งแต่กรอกผิด
 * ปิดได้ด้วย enabled = false (โรงเรียนนอกประเทศไทย ใช้เลขบัตรแบบอื่น)
 * เลขเดิมของใบที่โหลดมา (original) ไม่ตรวจ เหมือน API — ใบที่ย้ายมาจากระบบเดิมอาจมีเลขที่ไม่ผ่าน
 * หลักตรวจสอบ ถ้าตรวจตรงนี้ ใบเหล่านั้นจะบันทึกไม่ได้เลยทั้งที่ API ยอม
 *
 * @param {string} value
 * @returns {boolean}
 */
window.validators = window.validators || {};
window.validators.thaiIdCard = function(value) {
  const id = String(value || '');
  if (window.validators.thaiIdCard.enabled === false || !/^[0-9]{13}$/.test(id) || id === window.validators.thaiIdCard.original) {
    // รูปแบบ 13 หลักตรวจด้วย pattern ของช่องอยู่แล้ว
    return true;
  }
  let sum = 0;
  for (let i = 0; i < 12; i++) {
    sum += Number(id[i]) * (13 - i);
  }
  return (11 - sum % 11) % 10 === Number(id[12]);
};

/**
 * หน้าต่างพิมพ์ใบสมัครที่เปิดจากหน้านี้ ปิดให้ตอนกด "เสร็จสิ้น"
 * @type {Window|null}
 */
let enrollPrintWindow = null;

/**
 * หน้าของผู้สมัครที่มีลิงก์ใบสมัคร (ผลการสมัคร / แก้ไข): ปลอดภัยบนเครื่องที่ใช้ร่วมกัน
 *
 * ผู้สมัครมักกรอกที่เครื่องของโรงเรียน ลิงก์ใบสมัคร (?id=) คือกุญแจแก้ไขใบสมัคร
 * จึงให้มีหน้าที่มีลิงก์อยู่ในประวัติย้อนกลับได้ไม่เกินหนึ่งรายการ
 * - data-history="replace" : ไปหน้าผล/แก้ไขแบบแทนที่รายการเดิม ไม่เพิ่มประวัติ
 * - data-role="print"      : เปิดหน้าพิมพ์ในหน้าต่างชื่อเดียว เพื่อปิดให้ตอนเสร็จสิ้นได้
 * - data-role="finish"     : ปิดหน้าพิมพ์ แล้วแทนที่หน้านี้ด้วยหน้าแรก
 *   คนถัดไปกดย้อนกลับจึงไม่เห็นใบสมัครของคนก่อน
 *
 * ผูกที่ฟอร์ม (ลึกกว่า document) จึงทำงานก่อนตัวจัดการลิงก์ของ RouterManager
 * ซึ่งข้ามคลิกที่ถูก preventDefault แล้ว
 *
 * @param {HTMLElement} form
 * @returns {Function|undefined} ฟังก์ชันถอด event
 */
function initEnrollPublic(form) {
  if (!form || form._enrollPublicBound) {
    return;
  }
  form._enrollPublicBound = true;

  const onClick = event => {
    const replaceLink = event.target.closest('a[data-history="replace"]');
    if (replaceLink && replaceLink.getAttribute('href')) {
      event.preventDefault();
      RouterManager.navigate(replaceLink.getAttribute('href'), {}, {replace: true});
      return;
    }
    const printLink = event.target.closest('a[data-role="print"]');
    if (printLink && printLink.href) {
      event.preventDefault();
      enrollPrintWindow = window.open(printLink.href, 'enroll-print');
      return;
    }
    if (event.target.closest('[data-role="finish"]')) {
      event.preventDefault();
      if (enrollPrintWindow && !enrollPrintWindow.closed) {
        enrollPrintWindow.close();
      }
      enrollPrintWindow = null;
      RouterManager.navigate('/', {}, {replace: true});
    }
  };
  form.addEventListener('click', onClick);

  return () => {
    form.removeEventListener('click', onClick);
    form._enrollPublicBound = false;
  };
}

/**
 * หน้าประกาศผล: เลือกระดับชั้นหรือค้นหา = เปลี่ยนหน้าด้วย ?level=&search=
 * (ไม่ใช้ submit เพราะไม่มีอะไรต้องส่ง และลิงก์ที่ได้ส่งต่อให้คนอื่นเปิดได้)
 *
 * @param {HTMLElement} form
 * @returns {Function} ฟังก์ชันถอด event
 */
function initEnrollAnnounce(form) {
  const level = form.querySelector('[data-role="level"]');
  const search = form.querySelector('[data-role="search"]');
  const button = form.querySelector('[data-role="go"]');
  const go = () => {
    const params = new URLSearchParams();
    if (level && level.value) {
      params.set('level', level.value);
    }
    if (search && search.value.trim()) {
      params.set('search', search.value.trim());
    }
    const query = params.toString();
    RouterManager.navigate('/enroll-announce' + (query ? '?' + query : ''));
  };
  const onKey = event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      go();
    }
  };
  if (level) {
    level.addEventListener('change', go);
  }
  if (search) {
    search.addEventListener('keydown', onKey);
  }
  if (button) {
    button.addEventListener('click', go);
  }

  return () => {
    if (level) {
      level.removeEventListener('change', go);
    }
    if (search) {
      search.removeEventListener('keydown', onKey);
    }
    if (button) {
      button.removeEventListener('click', go);
    }
  };
}

/**
 * ภาษาของข้อมูลจาก API ให้ตรงกับภาษาที่ผู้เข้าชมเลือก
 *
 * ปุ่มเปลี่ยนภาษาเปลี่ยนแค่ข้อความในหน้าเว็บ ส่วนข้อความที่ API ส่งมา (สถานะ เนื้อหาหน้าแรก
 * ข้อความแจ้ง) ฝั่ง PHP เลือกภาษาจากคุกกี้ my_lang จึงตั้งคุกกี้ให้ตรงกับภาษาที่เลือก
 * ตั้งแต่โหลดสคริปต์ (ก่อนเรียก API ครั้งแรก) และทุกครั้งที่เปลี่ยนภาษา
 * หน้าผู้สมัครที่แสดงข้อมูลจาก API โหลดใหม่ทันที ยกเว้นแบบฟอร์มสมัคร (ข้อมูลที่กรอกไว้จะหาย)
 */
(function syncEnrollLanguage() {
  const setCookie = locale => {
    if (/^[a-z]{2}$/.test(locale || '')) {
      document.cookie = 'my_lang=' + locale + '; path=/; max-age=2592000; SameSite=Lax';
    }
  };
  let current = '';
  try {
    current = localStorage.getItem('app_lang') || '';
    setCookie(current);
  } catch (e) {}

  EventManager.on('locale:changed', context => {
    const i18n = window.Now && Now.getManager ? Now.getManager('i18n') : null;
    // EventManager ส่ง context มา ข้อมูลของ event ({locale, forced}) อยู่ที่ context.data
    const data = context && context.data ? context.data : context;
    const locale = (data && data.locale) ||
      (i18n && i18n.getCurrentLocale ? i18n.getCurrentLocale() : '');
    // RouterManager ส่ง locale:changed (forced) ภาษาเดิมซ้ำทุกครั้งที่แสดงหน้า
    // โหลดหน้าใหม่เฉพาะเมื่อภาษาเปลี่ยนจริง ไม่งั้นวนโหลดหน้าไม่รู้จบ
    if (!locale || locale === current) {
      return;
    }
    current = locale;
    setCookie(locale);
    const path = (RouterManager.state && RouterManager.state.current && RouterManager.state.current.path) || '';
    if (['/', '/home', '/enroll-result', '/enroll-announce', '/enroll-privacy'].includes(path)) {
      const base = (RouterManager.config && RouterManager.config.base) || '/';
      const route = window.location.pathname.startsWith(base) ? '/' + window.location.pathname.slice(base.length) : path;
      RouterManager.navigate(route + window.location.search, {}, {replace: true, force: true});
    }
  });
})();

/**
 * ปุ่ม data-scroll="<id>" ในหน้าผู้สมัคร: เลื่อนไปยังส่วนนั้นของหน้า
 * (ไม่ใช้ href="#id" เพราะ router จะตีความเป็นการเปลี่ยนหน้า)
 */
(function bindEnrollScroll() {
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-scroll]');
    if (!trigger) {
      return;
    }
    const target = document.getElementById(trigger.getAttribute('data-scroll'));
    if (target) {
      event.preventDefault();
      target.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
  });
})();

/**
 * หน้าแก้ไขรายละเอียดของหน้า: โหลดตัวแก้ไขข้อความ และเปลี่ยนหน้า/ภาษาที่แก้ไข
 *
 * ตัวแก้ไข (richtext-editor) ไม่ได้โหลดมากับทุกหน้า โหลดเมื่อเปิดหน้านี้เท่านั้น
 * ช่อง data-element="richtext" รอจนตัวแก้ไขพร้อมเอง
 * เปลี่ยนหน้าหรือภาษา = โหลดเนื้อหาของหน้านั้นใหม่ (?src=&language=)
 *
 * @param {HTMLElement} form
 * @returns {Function} ฟังก์ชันถอด event
 */
function initEnrollPages(form) {
  Utils.dom.loadResources(['Now/dist/richtext-editor.min.css', 'Now/dist/richtext-editor.min.js'].map(versionedUrl))
    .catch(() => NotificationManager.error(Now.translate('{LNG_Unable to complete the transaction}')));

  const selects = Array.from(form.querySelectorAll('[data-role="src"], [data-role="language"]'));
  const onChange = () => {
    const src = form.querySelector('[data-role="src"]');
    const language = form.querySelector('[data-role="language"]');
    RouterManager.navigate('/enroll-pages?src=' + encodeURIComponent(src ? src.value : '') +
      '&language=' + encodeURIComponent(language ? language.value : ''));
  };
  selects.forEach(select => select.addEventListener('change', onChange));

  return () => selects.forEach(select => select.removeEventListener('change', onChange));
}

/**
 * ใบสมัครของผู้สมัคร (/enroll-edit): ช่องที่จำนวนไม่คงที่ + ทีละขั้น + ปุ่มของเครื่องที่ใช้ร่วมกัน
 *
 * @param {HTMLElement} form ฟอร์มที่มี data-on-load
 * @param {Object} context payload จาก api/enroll/register/get — context.data และ context.options
 * @returns {Function} ฟังก์ชันถอด event ตอนออกจากหน้า
 */
function initEnrollRegister(form, context) {
  const data = (context && context.data) || {};

  window.validators.thaiIdCard.enabled = data.id_card_checksum !== false;
  window.validators.thaiIdCard.original = String(data.id_card || '');
  const cleanupPublic = initEnrollPublic(form);
  const cleanupFields = buildEnrollFields(form, context, 'api/enroll/register/plans');
  // ทีละขั้น (สร้างหลังช่องแผน/ผู้ปกครอง/ผลการเรียนแล้ว สรุปข้อมูลจึงเห็นครบ)
  const cleanupWizard = initEnrollWizard(form, data);

  return () => {
    cleanupFields();
    if (cleanupWizard) {
      cleanupWizard();
    }
    if (cleanupPublic) {
      cleanupPublic();
    }
  };
}

/**
 * ใบสมัครของเจ้าหน้าที่ (/enroll เปิดจากตารางผู้สมัคร): ฟอร์มหน้าเดียวแบบเดิมของระบบ
 * ใช้ช่องชุดเดียวกับหน้าผู้สมัคร แต่ API แยกกัน (api/enroll/enroll) และใช้กติกาของเจ้าหน้าที่
 *
 * @param {HTMLElement} form
 * @param {Object} context payload จาก api/enroll/enroll/get
 * @returns {Function} ฟังก์ชันถอด event
 */
function initEnrollForm(form, context) {
  const data = (context && context.data) || {};

  window.validators.thaiIdCard.enabled = data.id_card_checksum !== false;
  window.validators.thaiIdCard.original = String(data.id_card || '');

  return buildEnrollFields(form, context, 'api/enroll/enroll/plans');
}

/**
 * ช่องของใบสมัครที่จำนวนไม่คงที่ (ใช้ร่วมกันทั้งหน้าผู้สมัครและหน้าเจ้าหน้าที่)
 *
 * แผนการเรียน ผู้ปกครอง และผลการเรียน มีกี่ช่องขึ้นกับค่ากำหนดและรายการภาษาที่ผู้ดูแลแก้ไขได้
 * (enroll_study_plan_count, PARENT_LIST, ACADEMIC_RESULTS) เทมเพลตจึงเขียนไว้ล่วงหน้าไม่ได้
 * API ส่งรายการช่องมาให้แล้วสร้างที่นี่ เปลี่ยนระดับชั้นแล้วโหลดแผนของระดับนั้นจาก plansApi
 *
 * @param {HTMLElement} form
 * @param {Object} context payload จาก API — context.data และ context.options
 * @param {string} plansApi endpoint ตัวเลือกแผนการเรียนของระดับชั้น
 * @returns {Function} ฟังก์ชันถอด event
 */
function buildEnrollFields(form, context, plansApi) {
  const data = (context && context.data) || {};
  const options = (context && context.options) || {};
  const escape = value => Utils.string.escape(value === null || value === undefined ? '' : String(value));

  const planOptions = html => {
    return '<option value="">' + escape(Now.translate('{LNG_Please select}')) + '</option>' + html;
  };

  // แผนที่รับครบแล้ว (item.full) เลือกเป็นอันดับแรกไม่ได้ อันดับอื่นยังเลือกสำรองไว้ได้
  // เจ้าหน้าที่บันทึกเกินจำนวนได้ (API ไม่ตรวจ) จึงเห็นป้าย (เต็ม) แต่ยังเลือกได้
  const lockFull = !data.can_manage;
  const renderPlanOptions = (list, selected, isFirst) => {
    return planOptions((list || []).map(item => {
      const value = escape(item.value);
      return '<option value="' + value + '"' + (String(selected) === String(item.value) ? ' selected' : '') +
        (isFirst && item.full && lockFull ? ' disabled' : '') + '>' + escape(item.text) + '</option>';
    }).join(''));
  };

  // ---- แผนการเรียน ----
  // อันดับแรกต้องเลือกเสมอ (ฝั่ง API ตรวจ plan[0] อยู่แล้ว) จึงใส่ required ให้ช่องแรก
  const plansBox = form.querySelector('[data-role="plans"]');
  if (plansBox) {
    plansBox.innerHTML = (data.plan_fields || []).map(field => {
      const id = 'enroll_plan_' + field.no;
      return '<div>' +
        '<label for="' + id + '">' + escape(field.label) + '</label>' +
        '<span class="form-control icon-menus">' +
        '<select id="' + id + '" name="plan[' + field.no + ']" data-role="plan"' + (field.no === 0 ? ' required' : '') + '>' +
        renderPlanOptions(options.plans, field.value, field.no === 0) +
        '</select></span></div>';
    }).join('');
  }

  // ---- ผู้ปกครอง ----
  const parentsBox = form.querySelector('[data-role="parents"]');
  const parentFields = data.parent_fields || [];
  if (parentsBox) {
    parentsBox.innerHTML = parentFields.map(field => {
      const key = escape(field.key);
      return '<div class="enroll-parent">' +
        '<div class="enroll-parent-title icon-customer">' + escape(field.label) + '</div>' +
        '<div class="enroll-grid enroll-grid-parent">' +
        '<div>' +
        '<label for="enroll_parent_' + key + '">' + escape(Now.translate('{LNG_Name}')) + ' ' + escape(field.label) + '</label>' +
        '<span class="form-control icon-customer">' +
        '<input type="text" id="enroll_parent_' + key + '" name="parent_name[' + key + ']" maxlength="150" value="' + escape(field.name) + '">' +
        '</span></div>' +
        '<div>' +
        '<label for="enroll_parent_phone_' + key + '">' + escape(Now.translate('{LNG_Phone}')) + '</label>' +
        '<span class="form-control icon-phone">' +
        '<input type="tel" id="enroll_parent_phone_' + key + '" name="parent_phone[' + key + ']" maxlength="12" value="' + escape(field.phone) + '">' +
        '</span></div>' +
        '</div>' +
        (field.comment ? '<div class="comment">' + escape(field.comment) + '</div>' : '') +
        '</div>';
    }).join('');
  }
  // ไม่มีรายการผู้ปกครอง = ไม่ต้องแสดงกรอบนี้เลย
  const parentsGroup = form.querySelector('[data-role="parents-group"]');
  if (parentsGroup) {
    parentsGroup.hidden = parentFields.length === 0;
  }

  // ---- ผลการเรียน ----
  // ช่องเรียงด้วย CSS grid ของ .enroll-grid จึงไม่ต้องจับคู่ทีละสองช่องเอง
  const academicBox = form.querySelector('[data-role="academic"]');
  if (academicBox) {
    academicBox.innerHTML = (data.academic_fields || []).map(field => {
      const key = escape(field.key);
      return '<div>' +
        '<label for="enroll_academic_' + key + '">' + escape(field.label) + '</label>' +
        '<span class="form-control icon-number">' +
        '<input type="number" id="enroll_academic_' + key + '" name="academic[' + key + ']" min="0" max="100" step="0.01" value="' +
        escape(field.value) + '">' +
        '</span></div>';
    }).join('');
  }

  // ---- คำแนะนำใต้ช่องอัปโหลด ----
  // ข้อความในเทมเพลตมี :type ที่ต้องแทนด้วยชนิดไฟล์จากค่ากำหนด ถอด data-i18n ออก
  // ด้วย ไม่งั้น FormError จะคืนข้อความต้นฉบับ (ที่ยังมี :type) ตอนล้างข้อผิดพลาด
  const setHint = (id, text) => {
    const hint = form.querySelector('#' + id);
    if (hint && text) {
      hint.removeAttribute('data-i18n');
      hint.textContent = text;
    }
  };
  if (data.img_types) {
    setHint('result_thumbnail', Now.translate('{LNG_Straight face photos Wearing a uniform, not wearing a hat and glasses, taken within 6 months, :type type only}')
      .replace(':type', data.img_types));
  }
  if (data.attach_accept) {
    setHint('result_enroll', Now.translate('{LNG_Upload :type files}').replace(':type', data.attach_accept.split(',').join(', ')));
  }
  // รูปนักเรียนบังคับเฉพาะใบสมัครใหม่ ช่องไฟล์จึงใส่ required ไม่ได้ ทำเครื่องหมายที่ป้ายแทน
  const photoLabel = form.querySelector('[data-role="photo-label"]');
  if (photoLabel) {
    photoLabel.classList.toggle('required', !!data.is_new);
  }

  // ---- เปลี่ยนระดับชั้น = เปลี่ยนชุดแผนการเรียน ----
  const level = form.querySelector('[data-role="level"]');
  const onLevelChange = () => {
    ApiService.get(plansApi, {level: level.value})
      .then(response => {
        const list = Utils.options.normalizeSource(response) || [];
        form.querySelectorAll('[data-role="plan"]').forEach(select => {
          const selected = select.value;
          select.innerHTML = renderPlanOptions(list, selected, select.name === 'plan[0]');
        });
      })
      .catch(() => {});
  };
  if (level) {
    level.addEventListener('change', onLevelChange);
  }

  return () => {
    if (level) {
      level.removeEventListener('change', onLevelChange);
    }
  };
}

/**
 * ใบสมัครแบบทีละขั้น: แสดงทีละ [data-step] ตรวจช่องของขั้นนั้นก่อนไปขั้นถัดไป
 * ขั้นสุดท้ายสรุปข้อมูลให้ตรวจก่อนส่ง แล้วส่งครั้งเดียวด้วย FormManager ตามปกติ
 * ช่องที่ผิด (ตรวจตอนส่ง หรือ API ตอบกลับ) อยู่ขั้นไหน พากลับไปขั้นนั้นเอง
 *
 * @param {HTMLFormElement} form
 * @param {Object} data ข้อมูลจาก api/enroll/register/get
 * @returns {Function|null} ฟังก์ชันถอด event
 */
function initEnrollWizard(form, data) {
  const steps = Array.from(form.querySelectorAll('[data-step]'));
  if (steps.length === 0) {
    // ปิดรับสมัคร / แก้ไขไม่ได้: ไม่มีฟอร์ม
    return null;
  }
  const wizard = form.querySelector('.enroll-wizard');
  const tabs = Array.from(form.querySelectorAll('[data-step-tab]'));
  const prev = form.querySelector('[data-role="prev"]');
  const next = form.querySelector('[data-role="next"]');
  const submit = form.querySelector('[data-role="submit"]');
  const summary = form.querySelector('[data-role="summary"]');
  const photo = form.querySelector('#enroll_thumbnail');
  const last = steps.length - 1;
  // ใบใหม่ไปได้ถึงขั้นที่ผ่านมาแล้ว ใบเดิมกรอกครบแล้วกดไปขั้นไหนก็ได้
  let reached = data.is_new ? 0 : last;
  let current = 0;

  const stepOf = field => steps.indexOf(field.closest('[data-step]'));
  // ช่องของขั้น (ไม่รวมช่องในกลุ่มที่ซ่อนอยู่ภายในขั้น เช่น ไม่มีรายการผู้ปกครอง)
  const fieldsOf = step => Array.from(step.querySelectorAll('input, select, textarea')).filter(field => {
    if (!field.name || field.disabled || ['hidden', 'checkbox', 'radio', 'submit', 'button'].includes(field.type)) {
      return false;
    }
    const hiddenParent = field.closest('[hidden]');
    return !hiddenParent || hiddenParent === step;
  });
  const tabLabel = index => (tabs[index] && tabs[index].querySelector('span') ? tabs[index].querySelector('span').textContent.trim() : '');

  const focusField = field => {
    window.setTimeout(() => {
      try {
        field.focus({preventScroll: true});
        field.scrollIntoView({behavior: 'smooth', block: 'center'});
      } catch (e) {}
    }, 60);
  };

  const show = (index, scroll = true) => {
    current = Math.max(0, Math.min(index, last));
    reached = Math.max(reached, current);
    steps.forEach((step, i) => {
      step.hidden = i !== current;
    });
    tabs.forEach((tab, i) => {
      tab.classList.toggle('is-active', i === current);
      tab.classList.toggle('is-done', i !== current && i <= reached);
      tab.disabled = i > reached;
      if (i === current) {
        tab.setAttribute('aria-current', 'step');
      } else {
        tab.removeAttribute('aria-current');
      }
    });
    if (prev) prev.hidden = current === 0;
    if (next) next.hidden = current === last;
    if (submit) submit.hidden = current !== last;
    if (current === last) {
      renderSummary();
    }
    if (scroll && wizard) {
      wizard.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
  };

  // ตรวจทุกช่องของขั้นด้วยตัวตรวจของ FormManager (ข้อความผิดพลาดแสดงที่ช่องเหมือนตอนส่ง)
  const validateStep = async index => {
    const instance = FormManager.getInstanceByElement(form);
    let first = null;
    for (const field of fieldsOf(steps[index])) {
      let valid = true;
      try {
        valid = instance ? await FormManager.validateField(instance, field, true) : field.checkValidity();
      } catch (e) {
        valid = field.checkValidity();
      }
      if (!valid && !first) {
        first = field;
      }
    }
    // รูปนักเรียนบังคับเฉพาะใบใหม่ (ช่องไฟล์ใส่ required ไม่ได้ API ตรวจซ้ำอีกชั้น)
    if (photo && data.is_new && stepOf(photo) === index && !(photo.files && photo.files.length)) {
      FormError.showFieldError(photo, Now.translate('{LNG_Please upload pictures of students}'), form);
      first = first || photo;
    }
    if (first) {
      focusField(first);
      return false;
    }
    return true;
  };

  // ---- สรุปข้อมูลก่อนส่ง (ข้อความผู้ใช้ใส่ด้วย textContent เท่านั้น) ----
  const labelOf = field => {
    const label = field.id ? form.querySelector('label[for="' + CSS.escape(field.id) + '"]') : null;
    let text = label ? label.textContent.trim() : field.name;
    // ช่องของผู้ปกครองแต่ละคนชื่อซ้ำกัน (โทรศัพท์) ต่อท้ายด้วยชื่อกลุ่มให้รู้ว่าเป็นของใคร
    const parent = field.closest('.enroll-parent');
    const title = parent && parent.querySelector('.enroll-parent-title') ? parent.querySelector('.enroll-parent-title').textContent.trim() : '';
    if (title && !text.includes(title)) {
      text += ' (' + title + ')';
    }
    return text;
  };
  const valueOf = field => {
    if (field.tagName === 'SELECT') {
      const option = field.selectedOptions[0];
      return option && option.value !== '' ? option.textContent.trim() : '';
    }
    if (field.type === 'file') {
      const box = field.closest('.form-control') ? field.closest('.form-control').parentElement : null;
      const previews = box ? box.querySelectorAll('.preview-item').length : 0;
      const count = Math.max(field.files ? field.files.length : 0, previews);
      return count > 0 ? Now.translate('{LNG_Attached}') + ' ' + count + ' ' + Now.translate('{LNG_File}') : '';
    }
    if (field.type === 'date' && field.value) {
      const date = new Date(field.value + 'T00:00:00');
      const locale = (document.documentElement.getAttribute('lang') || 'th') === 'th' ? 'th-TH' : 'en-GB';
      return isNaN(date.getTime()) ? field.value : date.toLocaleDateString(locale, {day: 'numeric', month: 'long', year: 'numeric'});
    }
    return String(field.value || '').trim();
  };
  const renderSummary = () => {
    if (!summary) {
      return;
    }
    summary.replaceChildren();
    steps.slice(0, last).forEach((step, index) => {
      const group = document.createElement('section');
      group.className = 'enroll-summary-group';
      const head = document.createElement('div');
      head.className = 'enroll-summary-head';
      const title = document.createElement('span');
      title.textContent = tabLabel(index);
      const edit = document.createElement('button');
      edit.type = 'button';
      edit.dataset.gotoStep = String(index);
      edit.textContent = Now.translate('{LNG_Edit}');
      head.append(title, edit);
      const list = document.createElement('dl');
      fieldsOf(step).forEach(field => {
        const row = document.createElement('div');
        const term = document.createElement('dt');
        const detail = document.createElement('dd');
        const value = valueOf(field);
        term.textContent = labelOf(field);
        detail.textContent = value || '-';
        detail.classList.toggle('is-empty', value === '');
        row.append(term, detail);
        list.append(row);
      });
      group.append(head, list);
      summary.append(group);
    });
  };

  // ---- ปุ่มและแท็บ ----
  const onClick = async event => {
    const tab = event.target.closest('[data-step-tab]');
    const goto = event.target.closest('[data-goto-step]');
    if (event.target.closest('[data-role="next"]')) {
      if (await validateStep(current)) {
        show(current + 1);
      }
    } else if (event.target.closest('[data-role="prev"]')) {
      show(current - 1);
    } else if (tab || goto) {
      const index = Number((tab || goto).dataset.stepTab ?? (tab || goto).dataset.gotoStep);
      if (index > reached || index === current) {
        return;
      }
      // ข้ามไปข้างหน้าได้เมื่อขั้นปัจจุบันผ่านแล้ว ย้อนกลับได้เสมอ
      if (index > current && !(await validateStep(current))) {
        return;
      }
      show(index);
    }
  };
  // Enter ในช่องกรอก = ไปขั้นถัดไป (ไม่ส่งฟอร์มก่อนถึงขั้นสุดท้าย)
  const onKeydown = event => {
    if (event.key !== 'Enter' || event.defaultPrevented || current === last) {
      return;
    }
    const target = event.target;
    if (!target.matches('input') || ['checkbox', 'radio', 'file', 'submit', 'button'].includes(target.type)) {
      return;
    }
    event.preventDefault();
    if (next) {
      next.click();
    }
  };
  // เบราว์เซอร์ตรวจ required เองก่อนส่ง (ก่อน FormManager) ช่องที่ผิดในขั้นที่ซ่อนอยู่ focus ไม่ได้
  // แล้วการส่งหยุดเงียบ ๆ — event invalid เกิดก่อนเบราว์เซอร์ focus จึงสลับไปขั้นนั้นตรงนี้
  let invalidHandled = false;
  const onInvalid = event => {
    if (invalidHandled) {
      return;
    }
    const index = stepOf(event.target);
    if (index >= 0 && index !== current) {
      invalidHandled = true;
      window.setTimeout(() => {
        invalidHandled = false;
      }, 0);
      reached = Math.max(reached, index);
      show(index, false);
    }
  };
  form.addEventListener('click', onClick);
  form.addEventListener('keydown', onKeydown);
  form.addEventListener('invalid', onInvalid, true);

  // ---- ช่องที่ผิดตอนส่ง (ตรวจในเบราว์เซอร์ / API ตอบกลับ) อยู่ขั้นไหน พาไปขั้นนั้น ----
  const isThisForm = payload => {
    const instance = FormManager.getInstanceByElement(form);
    return payload && instance && payload.formId === instance.id;
  };
  const goToField = field => {
    const index = stepOf(field);
    if (index >= 0 && index !== current) {
      reached = Math.max(reached, index);
      show(index, false);
    }
    focusField(field);
  };
  const firstByStep = fields => fields
    .filter(field => field && typeof field.closest === 'function' && stepOf(field) >= 0)
    .sort((a, b) => stepOf(a) - stepOf(b))[0];
  const offValidate = EventManager.on('form:validate', raw => {
    // EventManager ส่ง context มา ข้อมูลของ event อยู่ที่ context.data
    const payload = raw && raw.data ? raw.data : raw;
    if (!isThisForm(payload) || payload.isValid) {
      return;
    }
    const field = firstByStep(Array.from(payload.invalidFields || []));
    if (field) {
      goToField(field);
    }
  });
  const offError = EventManager.on('form:error', raw => {
    // EventManager ส่ง context มา ข้อมูลของ event อยู่ที่ context.data
    const payload = raw && raw.data ? raw.data : raw;
    if (!isThisForm(payload) || !payload.errors || typeof payload.errors !== 'object') {
      return;
    }
    const fields = Object.keys(payload.errors).map(name => form.querySelector('[name="' + CSS.escape(name) + '"]') ||
      form.querySelector('[name="' + CSS.escape(name) + '[]"]'));
    const field = firstByStep(fields);
    if (field) {
      goToField(field);
    }
  });

  show(0, false);

  return () => {
    form.removeEventListener('click', onClick);
    form.removeEventListener('keydown', onKeydown);
    form.removeEventListener('invalid', onInvalid, true);
    if (typeof offValidate === 'function') offValidate();
    if (typeof offError === 'function') offError();
  };
}

/**
 * คอลัมน์ปุ่มของแถวผู้สมัคร: พิมพ์ใบสมัคร, บัตรประจำตัวผู้สอบ (เมื่อเปิดใช้), แก้ไข
 *
 * @param {HTMLElement} cell
 * @param {*} rawValue
 * @param {Object} rowData
 */
function formatEnrollActions(cell, rawValue, rowData) {
  const escape = value => Utils.string.escape(value === null || value === undefined ? '' : String(value));
  const row = rowData || {};
  let html = '<a class="btn btn-primary icon-print" target="_blank" href="' + escape(row.print_url) + '" title="' +
    escape(Now.translate('{LNG_Print}')) + '"></a>';
  if (row.card_url) {
    html += ' <a class="btn btn-warning icon-barcode" target="_blank" href="' + escape(row.card_url) + '" title="' +
      escape(Now.translate('{LNG_Admission card}')) + '"></a>';
  }
  html += ' <a class="btn btn-success icon-edit" href="' + escape(row.edit_url) + '" title="' +
    escape(Now.translate('{LNG_Edit}')) + '"></a>';
  cell.innerHTML = html;
}

/**
 * คอลัมน์ชื่อผู้สมัคร ใบที่ระบบกันสแปมติดป้ายไว้แสดง "ต้องตรวจสอบ" พร้อมเหตุผล
 *
 * @param {HTMLElement} cell
 * @param {*} rawValue
 * @param {Object} rowData
 */
function formatEnrollName(cell, rawValue, rowData) {
  const escape = value => Utils.string.escape(value === null || value === undefined ? '' : String(value));
  let html = escape(rawValue);
  if (rowData && rowData.review_text) {
    html += ' <span class="enroll-review icon-warning" title="' + escape(rowData.review_text) + '">' +
      escape(Now.translate('{LNG_Needs review}')) + '</span>';
  }
  cell.innerHTML = html;
}

/**
 * คอลัมน์เลขประจำตัวผู้สมัคร แสดงบาร์โค้ดคู่กับเลขที่
 *
 * @param {HTMLElement} cell
 * @param {*} rawValue
 * @param {Object} rowData
 */
function formatEnrollBarcode(cell, rawValue, rowData) {
  const escape = value => Utils.string.escape(value === null || value === undefined ? '' : String(value));
  const enrollNo = escape(rawValue);

  if (!enrollNo) {
    cell.innerHTML = '';
    return;
  }

  const barcode = rowData && rowData.barcode ? escape(rowData.barcode) : '';
  cell.innerHTML = '<span class="enroll-barcode-cell">' +
    (barcode ? '<img src="' + barcode + '" alt="' + enrollNo + '">' : '') +
    '<span>' + enrollNo + '</span></span>';
}

/**
 * ตารางผู้สมัคร: เปลี่ยนแผนการเรียนหรือผลการสมัครในแถวแล้วบันทึกทันที
 *
 * ผูก event ไว้ที่ตาราง (delegation) ครั้งเดียว เพราะ TableManager สร้าง
 * เซลล์ใหม่ทุกครั้งที่เปลี่ยนหน้าหรือเรียงลำดับ ตัว handler จึงต้องไม่ผูกกับ
 * element ของแถว
 *
 * @param {HTMLElement} table
 * @returns {Function|undefined} ฟังก์ชันถอด event
 */
function initEnrollTable(table) {
  if (table._enrollChangeBound) {
    return;
  }
  table._enrollChangeBound = true;

  const actionUrl = table.dataset.actionUrl;
  const fields = {result_plan: 'plan', result_status: 'status'};

  const onChange = event => {
    const select = event.target;
    if (!select || select.tagName !== 'SELECT') {
      return;
    }
    // ชื่อ input ที่ตารางสร้างคือ <field>[<id ของแถว>]
    const matched = /^([a-z_]+)\[([0-9]+)\]$/.exec(select.name || '');
    if (!matched || !fields[matched[1]]) {
      return;
    }
    ApiService.post(actionUrl, {
      action: fields[matched[1]],
      id: matched[2],
      value: select.value
    }).catch(() => {
      NotificationManager.error(Now.translate('{LNG_Unable to complete the transaction}'));
    });
  };

  table.addEventListener('change', onChange);

  // ปุ่มดาวน์โหลด CSV (หัวการ์ด data-export-table): ประกอบ URL จากตัวกรอง คำค้น และการเรียงลำดับ
  // ที่ใช้อยู่ตอนกด เปิดเป็นลิงก์ธรรมดา (ไม่ผ่าน ApiService) เพราะเป็นการดาวน์โหลดไฟล์
  // ฝั่งเซิร์ฟเวอร์ยืนยันตัวตนจาก cookie auth_token ได้อยู่แล้ว
  const tableId = table.dataset.table;
  const exportButton = document.querySelector('[data-role="export-csv"][data-export-table="' + tableId + '"]');
  const onExport = () => {
    const instance = TableManager.state.tables.get(tableId);
    const params = new URLSearchParams({type: 'csv'});
    const skip = ['page', 'pageSize', 'total', 'totalPages', 'totalRecords', 'loading', 'error'];
    Object.entries((instance && instance.config && instance.config.params) || {}).forEach(([key, value]) => {
      if (!skip.includes(key) && value !== undefined && value !== null && value !== '') {
        params.set(key, value);
      }
    });
    const sortState = (instance && instance.sortState) || {};
    const sortPairs = Object.entries(sortState).map(([field, direction]) => field + ' ' + direction);
    if (sortPairs.length) {
      params.set('sort', sortPairs.join(','));
    }
    window.open(table.dataset.source + '/export?' + params.toString(), 'export');
  };
  if (exportButton) {
    exportButton.addEventListener('click', onExport);
  }

  table._enrollChangeCleanup = () => {
    table.removeEventListener('change', onChange);
    if (exportButton) {
      exportButton.removeEventListener('click', onExport);
    }
    table._enrollChangeBound = false;
  };

  return table._enrollChangeCleanup;
}

/**
 * หน้าตั้งค่า: สร้างตัวเลือกสถานะที่แก้ไขได้ และปุ่มล้างฐานข้อมูล
 *
 * ตัวเลือกมีกี่ข้อขึ้นกับรายการภาษา REGISTER_STATUS ที่ผู้ดูแลแก้ไขได้
 * เทมเพลตจึงเขียนไว้ล่วงหน้าไม่ได้
 *
 * @param {HTMLElement} form
 * @param {Object} context
 * @returns {Function} ฟังก์ชันถอด event
 */
function initEnrollSettings(form, context) {
  const resetButton = form.querySelector('[data-role="reset-database"]');
  const onReset = () => {
    const question = Now.translate('{LNG_You want to XXX ?}').replace('XXX', Now.translate('{LNG_Reset database}'));
    if (!window.confirm(question)) {
      return;
    }
    ApiService.post('api/enroll/settings/reset', {})
      .then(response => ResponseHandler.process(response))
      .catch(() => {
        NotificationManager.error(Now.translate('{LNG_Unable to complete the transaction}'));
      });
  };
  if (resetButton) {
    resetButton.addEventListener('click', onReset);
  }

  return () => {
    if (resetButton) {
      resetButton.removeEventListener('click', onReset);
    }
  };
}
