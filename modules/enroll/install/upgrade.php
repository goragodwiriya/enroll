<?php
/**
 * modules/enroll/install/upgrade.php — พาฐานเดิมมาถึงสคีมาของโมดูล enroll
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เอง ตัวแปรที่ใช้ได้คือชุดเดียวกับที่
 * upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 *
 * ⚠️ ก่อนมีไฟล์นี้ **ไม่มีอะไรแตะตารางของโมดูลนี้เลย** install/upgrade2.php
 * ของโปรเจ็คเป็นแม่แบบเปล่า ๆ ที่เรียกแต่ upgrade_core ส่วนตารางทั้ง 7 ตาราง
 * ถูกประกาศไว้ใน install/database.sql เท่านั้น ไซต์ที่ติดตั้งใหม่จึงได้สคีมาถูก
 * แต่ไซต์ที่กดปรับรุ่นได้สคีมาเก่าค้างไว้ทั้งชุด — อาการเดียวกับที่ booking
 * เคยพังด้วย "Unknown column 'R.is_active'"
 *
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 */
if (!defined('ROOT_PATH')) {
    exit;
}

$_tables = [
    'province', 'amphur', 'district',
    'enroll_levels', 'enroll_plans', 'enroll', 'enroll_choices'
];
foreach ($_tables as $_name) {
    $_table = $prefix.'_'.$_name;
    // นิยามตารางอยู่ที่ modules/enroll/install/database.sql ที่เดียว
    if (ensureTable($db, $prefix, $_table)) {
        $content[] = '<li class="correct">enroll: สร้างตาราง '.$_name.'</li>';
    }
    // ⚠️ ต้องแปลงก่อนปรับคอลัมน์เสมอ — CONVERT TO CHARACTER SET เลื่อนชนิด TEXT
    // เป็น MEDIUMTEXT ถ้าแปลงทีหลังชนิดจะไม่ตรงกับที่ติดตั้งใหม่
    // (ฐานจริงของ enroll มี enroll.parent / academic_results เป็น mediumtext
    // อยู่แล้วเพราะเคยถูกแปลง charset มาก่อนโดยไม่ได้บังคับชนิดกลับ)
    if (convertToInnoDB($db, $_table)) {
        $content[] = '<li class="correct">'.$_name.': แปลงเป็น InnoDB</li>';
    }
    if (convertToUtf8mb4($db, $_table)) {
        $content[] = '<li class="correct">'.$_name.': แปลงเป็น utf8mb4</li>';
    }
}

// =============================================================================
// ข้อมูลที่อยู่ของไทย — คอลัมน์ country ของเดิมไม่มี DEFAULT
//
// Enroll\Address\Model กรองด้วย country ทุกคิวรี ถ้าแถวที่เพิ่มใหม่ได้ค่าว่าง
// แทน 'TH' รายการจังหวัด/อำเภอ/ตำบลจะหายไปจากฟอร์มโดยไม่มีอะไรฟ้อง
// =============================================================================
foreach ([
    'province' => [
        'country' => ['varchar(2)', false, 'TH', ''],
        'id' => ['int(3)', false, null, 'country'],
        'province' => ['varchar(50)', false, null, 'id']
    ],
    'amphur' => [
        'country' => ['varchar(2)', false, 'TH', ''],
        'id' => ['int(5)', false, null, 'country'],
        'province_id' => ['int(3)', true, null, 'id'],
        'amphur' => ['varchar(50)', true, null, 'province_id']
    ],
    'district' => [
        'country' => ['varchar(2)', false, 'TH', ''],
        'id' => ['int(10)', false, null, 'country'],
        'amphur_id' => ['int(5)', false, null, 'id'],
        'district' => ['varchar(50)', true, null, 'amphur_id']
    ]
] as $_name => $_columns) {
    $_table = $prefix.'_'.$_name;
    foreach ($_columns as $_col => $_def) {
        if (ensureColumn($db, $_table, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
            $content[] = '<li class="correct">'.$_name.': ปรับคอลัมน์ '.$_col.'</li>';
        }
    }
}

// =============================================================================
// ระดับชั้นและแผนการเรียน
// =============================================================================
foreach ([
    'enroll_levels' => [
        'topic' => ['varchar(150)', false, null, 'id'],
        'sort' => ['int(11)', false, '0', 'topic'],
        'is_active' => ['tinyint(1)', false, '1', 'sort']
    ],
    'enroll_plans' => [
        'level_id' => ['int(11)', false, null, 'id'],
        'topic' => ['varchar(150)', false, null, 'level_id'],
        'sort' => ['int(11)', false, '0', 'topic'],
        'is_active' => ['tinyint(1)', false, '1', 'sort']
    ],
    'enroll_choices' => [
        'no' => ['tinyint(4)', false, '0', 'enroll_id'],
        'plan_id' => ['int(11)', false, null, 'no']
    ]
] as $_name => $_columns) {
    $_table = $prefix.'_'.$_name;
    foreach ($_columns as $_col => $_def) {
        if (ensureColumn($db, $_table, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
            $content[] = '<li class="correct">'.$_name.': ปรับคอลัมน์ '.$_col.'</li>';
        }
    }
}

// =============================================================================
// ใบสมัคร
//
// ⚠️ parent / academic_results ต้องเป็น text ไม่ใช่ mediumtext — ฐานที่เคยถูก
// แปลง charset มาก่อนจะถูกเลื่อนชนิดขึ้นไปเอง ต้องบังคับกลับให้ตรงกับติดตั้งใหม่
// =============================================================================
$_t_enroll = $prefix.'_enroll';
foreach ([
    'level' => ['int(11)', false, '0', 'id'],
    'enroll_no' => ['varchar(20)', false, '', 'level'],
    'title' => ['tinyint(2)', false, '1', 'enroll_no'],
    'name' => ['varchar(150)', false, null, 'title'],
    'id_card' => ['varchar(13)', true, null, 'name'],
    'birthday' => ['date', true, null, 'id_card'],
    'phone' => ['varchar(10)', true, null, 'birthday'],
    'email' => ['varchar(255)', true, null, 'phone'],
    'nationality' => ['varchar(20)', true, null, 'email'],
    'religion' => ['varchar(20)', true, null, 'nationality'],
    'address' => ['varchar(150)', true, null, 'religion'],
    'districtID' => ['int(10)', true, null, 'address'],
    'amphurID' => ['int(5)', true, null, 'districtID'],
    'provinceID' => ['int(3)', true, null, 'amphurID'],
    'zipcode' => ['varchar(10)', true, null, 'provinceID'],
    'parent' => ['text', true, null, 'zipcode'],
    'original_school' => ['varchar(150)', true, null, 'parent'],
    'academic_results' => ['text', true, null, 'original_school'],
    'created_at' => ['datetime', true, null, 'academic_results'],
    'link' => ['varchar(32)', true, null, 'created_at'],
    'result_plan' => ['int(11)', false, '0', 'link'],
    'result_status' => ['tinyint(2)', false, '0', 'result_plan'],
    // กันสแปมฟอร์มสมัคร: เหตุที่ต้องตรวจสอบ (ว่าง = ปกติ) และรหัสแบบฟอร์มที่ใช้ส่ง (ใช้ได้ครั้งเดียว)
    'review' => ['varchar(50)', false, '', 'result_status'],
    'form_nonce' => ['varchar(32)', true, null, 'review'],
    // เวลาที่ผู้สมัครยินยอมให้เก็บข้อมูลส่วนบุคคล (PDPA) ใบเดิมก่อนมีช่องนี้ = NULL
    'consent_at' => ['datetime', true, null, 'form_nonce']
] as $_col => $_def) {
    if (ensureColumn($db, $_t_enroll, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">enroll: ปรับคอลัมน์ '.$_col.'</li>';
    }
}

// =============================================================================
// ดัชนีที่ query ใช้จริง — ต้องทำหลังปรับคอลัมน์เสร็จ
// =============================================================================
foreach ([
    'province' => ['province' => '`province`'],
    'amphur' => ['province_id' => '`country`, `province_id`', 'amphur' => '`amphur`'],
    'district' => ['amphur_id' => '`country`, `amphur_id`', 'district' => '`district`'],
    'enroll_levels' => ['idx_active_sort' => '`is_active`, `sort`'],
    'enroll_plans' => ['idx_level_sort' => '`level_id`, `is_active`, `sort`'],
    'enroll' => [
        'id_card' => '`id_card`',
        'enroll_no' => '`enroll_no`',
        'idx_result' => '`level`, `result_status`, `result_plan`'
    ],
    'enroll_choices' => ['plan_id' => '`plan_id`']
] as $_name => $_indexes) {
    $_table = $prefix.'_'.$_name;
    if ($db->tableExists($_table) && ensureIndexes($db, $_table, $_indexes)) {
        $content[] = '<li class="correct">'.$_name.': ปรับดัชนี</li>';
    }
}

// จำนวนที่รับของแผนการเรียน (0 = ไม่จำกัด)
$_t_plans = $prefix.'_enroll_plans';
if ($db->tableExists($_t_plans) && ensureColumn($db, $_t_plans, 'capacity', 'int(11)', false, '0', '', 'is_active')) {
    $content[] = '<li class="correct">enroll_plans: ปรับคอลัมน์ capacity</li>';
}

// รหัสแบบฟอร์มต้องไม่ซ้ำ (NULL ซ้ำได้) ส่งแบบฟอร์มเดียวกันพร้อมกันสองครั้งจึงบันทึกได้ใบเดียว
// ensureIndexes() สร้างได้แต่ดัชนีธรรมดา จึงต้องจัดการ UNIQUE เอง
if ($db->tableExists($_t_enroll)) {
    dropMismatchedIndex($db, $_t_enroll, 'form_nonce', '`form_nonce`', true);
    if (empty(indexColumns($db, $_t_enroll, 'form_nonce'))) {
        $db->query("ALTER TABLE `$_t_enroll` ADD UNIQUE INDEX `form_nonce` (`form_nonce`)");
        $content[] = '<li class="correct">enroll: ปรับดัชนี form_nonce</li>';
    }
}

// =============================================================================
// enroll_plan (เอกพจน์) — ตารางรุ่นแรกของ enroll_choices
//
// ⚠️ ไม่ใช่รุ่นแรกของ enroll_plans อย่างที่ชื่อชวนให้เข้าใจ คอลัมน์ของมันคือ
// (enroll_id, value, no) ซึ่งก็คือ (enroll_id, plan_id, no) ของ enroll_choices
// นี่คือ "อันดับแผนการเรียนที่ผู้สมัครเลือก" ซึ่งเป็นข้อมูลที่ผู้สมัครกรอกเอง
// จะทิ้งไม่ได้เด็ดขาด ต้องย้ายเข้าตารางใหม่ให้ครบก่อน แล้วจึงเก็บของเดิมไว้
//
// ไม่มีโค้ดไหนอ้างตารางนี้แล้ว ไซต์ที่อัปเกรดแล้วไม่ย้ายข้อมูล = อันดับที่
// ผู้สมัครเลือกหายทั้งระบบโดยหน้าจอไม่ฟ้องอะไรเลย
// =============================================================================
$_old_plan = $prefix.'_enroll_plan';
$_t_choices = $prefix.'_enroll_choices';
if ($db->tableExists($_old_plan)) {
    $_rows = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$_old_plan`");
    $_count = empty($_rows) ? 0 : (int) $_rows[0]->c;
    if ($_count === 0) {
        $db->query("DROP TABLE `$_old_plan`");
        noteTableDropped($_old_plan);
        $content[] = '<li class="correct">enroll_plan: ลบตารางรุ่นแรกที่เลิกใช้แล้ว (ว่าง)</li>';
    } else {
        // INSERT IGNORE — รันซ้ำได้ แถวที่ย้ายไปแล้วจะไม่ซ้ำ (PK คือ enroll_id + no)
        $db->query("INSERT IGNORE INTO `$_t_choices` (`enroll_id`, `no`, `plan_id`)
            SELECT `enroll_id`, `no`, `value` FROM `$_old_plan`");
        noteRowsMoved($_old_plan, $_t_choices, $_count);
        $content[] = '<li class="correct">enroll_plan: ย้ายอันดับแผนการเรียน '
            .number_format($_count).' แถว ไป '.$_t_choices.'</li>';
        if (!$db->tableExists($_old_plan.'_bak')) {
            $db->query("RENAME TABLE `$_old_plan` TO `".$_old_plan."_bak`");
            $content[] = '<li class="correct">enroll_plan: เก็บตารางเดิมไว้เป็น '
                .$_old_plan.'_bak (ย้ายข้อมูลครบแล้ว ลบเองได้เมื่อแน่ใจ)</li>';
        }
    }
}

$content[] = '<li class="correct">enroll อัปเกรดสำเร็จ</li>';
