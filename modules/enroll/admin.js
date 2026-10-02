/**
 * modules/enroll/admin.js
 *
 * ลงทะเบียน route และ helper ของโมดูลรับสมัครนักเรียน
 *
 * หน้าของผู้สมัคร (/enroll-edit, /enroll-result) เป็น requireAuth: false
 * เพราะผู้สมัครไม่ใช่สมาชิกของระบบ — กุญแจเข้าถึงคือ link 32 ตัวอักษรใน query string
 * ที่ฝั่ง API ตรวจทุกครั้ง
 */
EventManager.on('router:initialized', () => {
  RouterManager.register('/', {
    template: 'enroll/enrolls.html',
    title: '{LNG_List of} {LNG_Enroll}',
    requireAuth: true
  });

  RouterManager.register('/enroll-list', {
    template: 'enroll/list.html',
    title: '{LNG_Enroll}',
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
    title: '{LNG_Module settings} {LNG_Enroll}',
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
 * ฟอร์มลงทะเบียน: สร้างส่วนที่จำนวนช่องไม่คงที่
 *
 * สามส่วนนี้จำนวนช่องขึ้นกับค่ากำหนดและรายการภาษาที่ผู้ดูแลแก้ไขได้
 * (enroll_study_plan_count, PARENT_LIST, ACADEMIC_RESULTS) เทมเพลตจึงเขียน
 * ไว้ล่วงหน้าไม่ได้ API ส่งรายการช่องมาให้แล้วสร้างที่นี่
 *
 * @param {HTMLElement} form ฟอร์มที่มี data-on-load
 * @param {Object} context payload จาก API — context.data และ context.options
 * @returns {Function} ฟังก์ชันถอด event ตอนออกจากหน้า
 */
function initEnrollRegister(form, context) {
  const data = (context && context.data) || {};
  const options = (context && context.options) || {};
  const escape = value => Utils.string.escape(value === null || value === undefined ? '' : String(value));

  const planOptions = html => {
    return '<option value="">' + escape(Now.translate('{LNG_Please select}')) + '</option>' + html;
  };

  const renderPlanOptions = (list, selected) => {
    return planOptions((list || []).map(item => {
      const value = escape(item.value);
      return '<option value="' + value + '"' + (String(selected) === String(item.value) ? ' selected' : '') + '>' +
        escape(item.text) + '</option>';
    }).join(''));
  };

  // ---- แผนการเรียน ----
  const plansBox = form.querySelector('[data-role="plans"]');
  if (plansBox) {
    plansBox.innerHTML = (data.plan_fields || []).map(field => {
      const id = 'enroll_plan_' + field.no;
      return '<div>' +
        '<label for="' + id + '">' + escape(field.label) + '</label>' +
        '<span class="form-control icon-menus">' +
        '<select id="' + id + '" name="plan[' + field.no + ']" data-role="plan">' +
        renderPlanOptions(options.plans, field.value) +
        '</select></span></div>';
    }).join('');
  }

  // ---- ผู้ปกครอง ----
  const parentsBox = form.querySelector('[data-role="parents"]');
  const parentFields = data.parent_fields || [];
  if (parentsBox) {
    parentsBox.innerHTML = parentFields.map(field => {
      const key = escape(field.key);
      return '<div class="form-group">' +
        '<div class="width50">' +
        '<label for="enroll_parent_' + key + '">' + escape(Now.translate('{LNG_Name}')) + ' ' + escape(field.label) + '</label>' +
        '<span class="form-control icon-customer">' +
        '<input type="text" id="enroll_parent_' + key + '" name="parent_name[' + key + ']" maxlength="150" value="' + escape(field.name) + '">' +
        '</span></div>' +
        '<div class="width50">' +
        '<label for="enroll_parent_phone_' + key + '">' + escape(Now.translate('{LNG_Phone}')) + '</label>' +
        '<span class="form-control icon-phone">' +
        '<input type="tel" id="enroll_parent_phone_' + key + '" name="parent_phone[' + key + ']" maxlength="10" value="' + escape(field.phone) + '">' +
        '</span></div>' +
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
  const academicBox = form.querySelector('[data-role="academic"]');
  if (academicBox) {
    const fields = data.academic_fields || [];
    let html = '';
    for (let i = 0; i < fields.length; i += 2) {
      html += '<div class="form-group">' + fields.slice(i, i + 2).map(field => {
        const key = escape(field.key);
        return '<div class="width50">' +
          '<label for="enroll_academic_' + key + '">' + escape(field.label) + '</label>' +
          '<span class="form-control icon-number">' +
          '<input type="number" id="enroll_academic_' + key + '" name="academic[' + key + ']" min="0" max="100" step="0.01" value="' +
          escape(field.value) + '">' +
          '</span></div>';
      }).join('') + '</div>';
    }
    academicBox.innerHTML = html;
  }

  // ---- เปลี่ยนระดับชั้น = เปลี่ยนชุดแผนการเรียน ----
  const level = form.querySelector('[data-role="level"]');
  const onLevelChange = () => {
    ApiService.get('api/enroll/register/plans', {level: level.value})
      .then(response => {
        const list = Utils.options.normalizeSource(response) || [];
        form.querySelectorAll('[data-role="plan"]').forEach(select => {
          const selected = select.value;
          select.innerHTML = renderPlanOptions(list, selected);
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

  // ปุ่มดาวน์โหลด CSV: ประกอบ URL จากตัวกรองและการเรียงลำดับที่ใช้อยู่ตอนกด
  // เปิดเป็นลิงก์ธรรมดา (ไม่ผ่าน ApiService) เพราะเป็นการดาวน์โหลดไฟล์
  // ฝั่งเซิร์ฟเวอร์ยืนยันตัวตนจาก cookie auth_token ได้อยู่แล้ว
  const tableId = table.dataset.table;
  const exportButton = document.querySelector('[data-table-filter="' + tableId + '"] [data-role="export-csv"]');
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
  const data = (context && context.data) || {};
  const escape = value => Utils.string.escape(value === null || value === undefined ? '' : String(value));

  const box = form.querySelector('[data-role="editable-statuses"]');
  if (box) {
    box.innerHTML = (data.status_fields || []).map(field => {
      const id = 'enroll_editable_' + escape(field.value);
      return '<div class="width25">' +
        '<input type="checkbox" class="switch" id="' + id + '" name="enroll_editable[]" value="' + escape(field.value) + '"' +
        (field.checked ? ' checked' : '') + '>' +
        '<label for="' + id + '">' + escape(field.text) + '</label>' +
        '</div>';
    }).join('');
  }

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
