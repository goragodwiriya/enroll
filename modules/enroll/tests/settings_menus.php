<?php
require __DIR__.'/harness.php';
Harness::boot(__DIR__);

$admin = Harness::login(1, 'can_manage_enroll,can_config', 1);
$manager = Harness::login(3, 'can_manage_enroll', 0);
$member = Harness::login(5, '', 0);
$p = Harness::PREFIX;

// สำเนา config ของ tenant ไว้กู้คืน เพราะการบันทึกจะเขียนทับไฟล์จริง
$configFile = ROOT_PATH.'settings/config.php';
$backup = file_get_contents($configFile);

echo "\n=== อ่านค่าตั้งค่า ===\n";
$r = Harness::call('\Enroll\Settings\Controller', 'get', []);
Harness::check('ไม่ล็อกอิน → 401', ($r['code'] ?? 0) === 401);
$r = Harness::call('\Enroll\Settings\Controller', 'get', ['token' => $member]);
Harness::check('ไม่มีสิทธิ์ตั้งค่า → 403', ($r['code'] ?? 0) === 403);
$r = Harness::call('\Enroll\Settings\Controller', 'get', ['token' => $admin]);
$d = $r['data']['data'] ?? [];
Harness::check('อ่านค่าได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('มีค่ากำหนดครบทุกตัว',
    isset($d['school_name'], $d['school_year'], $d['enroll_study_plan_count'], $d['enroll_w'],
        $d['enroll_csv_language'], $d['enroll_country'], $d['enroll_prefix'], $d['enroll_no'],
        $d['enroll_begin'], $d['enroll_end'], $d['enroll_editable'], $d['enroll_phone_limit'], $d['enroll_notify_email'],
        $d['enroll_notify_sms'], $d['enroll_announce'], $d['enroll_announce_statuses'], $d['enroll_exam_card'],
        $d['enroll_exam_statuses'], $d['enroll_exam_date'], $d['enroll_exam_place'], $d['enroll_exam_note'],
        $d['enroll_capacity_exclude_statuses']),
    json_encode(array_keys($d), JSON_UNESCAPED_UNICODE));
// ตัวเลือกสถานะเป็น select หลายค่า: รายการอยู่ใน options ค่าที่เลือกอยู่ใน data
Harness::check('ตัวเลือกสถานะมาจาก REGISTER_STATUS ครบ 7 ข้อ', count($r['data']['options']['status_fields'] ?? []) === 7,
    json_encode($r['data']['options']['status_fields'] ?? null, JSON_UNESCAPED_UNICODE));
Harness::check('ค่าเริ่มต้นแก้ไขได้ในสถานะ 0 และ 2', $d['enroll_editable'] === [0, 2], json_encode($d['enroll_editable']));
Harness::check('ค่าเริ่มต้นของฟีเจอร์ใหม่: อีเมลเปิด SMS ปิด ประกาศผลปิด บัตรสอบปิด 5 ใบต่อเบอร์',
    $d['enroll_notify_email'] === 1 && $d['enroll_notify_sms'] === 0 && $d['enroll_announce'] === 0
    && $d['enroll_exam_card'] === 0 && $d['enroll_phone_limit'] === 5 && $d['enroll_announce_statuses'] === [1],
    json_encode(array_intersect_key($d, array_flip(['enroll_notify_email', 'enroll_notify_sms', 'enroll_announce', 'enroll_exam_card', 'enroll_phone_limit', 'enroll_announce_statuses']))));
Harness::check('ค่าเริ่มต้น: สถานะที่ไม่นับในจำนวนที่รับ = ไม่อนุมัติ (3)', $d['enroll_capacity_exclude_statuses'] === [3],
    json_encode($d['enroll_capacity_exclude_statuses']));
Harness::check('มีตัวเลือก CSV และประเทศ',
    !empty($r['data']['options']['enroll_csv_language']) && !empty($r['data']['options']['enroll_country']));

echo "\n=== บันทึกค่าตั้งค่า ===\n";
$post = [
    'school_name' => 'โรงเรียนทดสอบระบบ',
    'school_year' => 2569,
    'enroll_study_plan_count' => 3,
    'enroll_w' => 800,
    'enroll_csv_language' => 'TIS-620',
    'enroll_country' => 'LA',
    'enroll_prefix' => 'X%s',
    'enroll_no' => '%05d',
    'enroll_editable' => [0, 2, 4],
    'enroll_begin' => '2026-01-01T08:00',
    'enroll_end' => '2026-03-31T16:30',
    'enroll_phone_limit' => 3,
    'enroll_capacity_exclude_statuses' => [3, 2, 99],
    'enroll_notify_email' => 1,
    'enroll_notify_sms' => 1,
    'enroll_announce' => 1,
    'enroll_announce_statuses' => [1, 4],
    'enroll_exam_card' => 1,
    'enroll_exam_statuses' => [0, 1],
    'enroll_exam_date' => 'เสาร์ 14 มีนาคม 2570 เวลา 08:30 น.',
    'enroll_exam_place' => 'อาคาร 2 ชั้น 3',
    'enroll_exam_note' => "แต่งชุดนักเรียน\nนำบัตรประชาชนมาด้วย"
];
$r = Harness::call('\Enroll\Settings\Controller', 'save', ['token' => $member, 'post' => $post]);
Harness::check('ไม่มีสิทธิ์ → บันทึกไม่ได้', empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Settings\Controller', 'save', ['token' => $admin, 'post' => $post]);
Harness::check('บันทึกสำเร็จ', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));

$saved = include $configFile;
Harness::check('เขียนลง settings/config.php จริง', ($saved['school_name'] ?? '') === 'โรงเรียนทดสอบระบบ', json_encode($saved['school_name'] ?? null, JSON_UNESCAPED_UNICODE));
Harness::check('แปลงวันเวลาเป็น timestamp', $saved['enroll_begin'] === strtotime('2026-01-01 08:00'), var_export($saved['enroll_begin'], true));
Harness::check('เก็บสถานะที่แก้ไขได้เป็นตัวเลข', $saved['enroll_editable'] === [0, 2, 4], json_encode($saved['enroll_editable']));
Harness::check('จำนวนแผน/ขนาดรูป มีค่าต่ำสุดคุมไว้', $saved['enroll_study_plan_count'] === 3 && $saved['enroll_w'] === 800);
Harness::check('บันทึกการแจ้งเตือนและจำนวนต่อเบอร์', $saved['enroll_notify_email'] === 1 && $saved['enroll_notify_sms'] === 1 && $saved['enroll_phone_limit'] === 3);
Harness::check('บันทึกสถานะที่ไม่นับในจำนวนที่รับ (ตัดสถานะที่ไม่มีจริงทิ้ง)', $saved['enroll_capacity_exclude_statuses'] === [3, 2],
    json_encode($saved['enroll_capacity_exclude_statuses'] ?? null));
Harness::check('บันทึกประกาศผลและสถานะที่ประกาศ', $saved['enroll_announce'] === 1 && $saved['enroll_announce_statuses'] === [1, 4],
    json_encode($saved['enroll_announce_statuses'] ?? null));
Harness::check('บันทึกบัตรประจำตัวผู้สอบ', $saved['enroll_exam_card'] === 1 && $saved['enroll_exam_statuses'] === [0, 1]
    && $saved['enroll_exam_place'] === 'อาคาร 2 ชั้น 3' && strpos($saved['enroll_exam_note'], "\n") !== false,
    json_encode([$saved['enroll_exam_statuses'] ?? null, $saved['enroll_exam_note'] ?? null], JSON_UNESCAPED_UNICODE));

$r = Harness::call('\Enroll\Settings\Controller', 'save', [
    'token' => $admin,
    'post' => array_merge($post, ['enroll_study_plan_count' => 0, 'enroll_w' => 10, 'enroll_editable' => [0, 99],
        'enroll_phone_limit' => -5, 'enroll_announce_statuses' => [99], 'enroll_notify_sms' => 0])
]);
$saved = include $configFile;
Harness::check('จำนวนแผนน้อยกว่า 1 ถูกดันเป็น 1', $saved['enroll_study_plan_count'] === 1, var_export($saved['enroll_study_plan_count'], true));
Harness::check('ขนาดรูปน้อยกว่า 100 ถูกดันเป็น 100', $saved['enroll_w'] === 100, var_export($saved['enroll_w'], true));
Harness::check('สถานะที่ไม่มีอยู่จริงถูกตัดทิ้ง', $saved['enroll_editable'] === [0] && $saved['enroll_announce_statuses'] === [],
    json_encode([$saved['enroll_editable'], $saved['enroll_announce_statuses']]));
Harness::check('จำนวนต่อเบอร์ติดลบ → 0, ปิดสวิตช์ (ไม่ส่งค่า) → 0', $saved['enroll_phone_limit'] === 0 && $saved['enroll_notify_sms'] === 0);

file_put_contents($configFile, $backup);

echo "\n=== ล้างฐานข้อมูล ===\n";
Harness::$pdo->exec("INSERT INTO `{$p}_enroll` (`id`,`level`,`enroll_no`,`title`,`name`,`link`,`created_at`)
    VALUES (1,1,'E0001',1,'ทดสอบ','".str_repeat('q', 32)."',NOW())");
Harness::$pdo->exec("INSERT INTO `{$p}_enroll_choices` (`enroll_id`,`no`,`plan_id`) VALUES (1,0,1)");
Harness::$pdo->exec("INSERT INTO `{$p}_number` (`type`,`prefix`,`auto_increment`,`updated_at`) VALUES ('%04d','E691',5,CURDATE())");

$r = Harness::call('\Enroll\Settings\Controller', 'reset', ['token' => $member, 'post' => [], 'method' => 'POST']);
Harness::check('สมาชิกทั่วไปล้างฐานข้อมูลไม่ได้', empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ข้อมูลยังอยู่', Harness::$pdo->query("SELECT COUNT(*) FROM `{$p}_enroll`")->fetchColumn() == 1);

$r = Harness::call('\Enroll\Settings\Controller', 'reset', ['token' => $manager, 'post' => [], 'method' => 'POST']);
Harness::check('ผู้จัดการการรับสมัครล้างได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ใบสมัครถูกล้าง', Harness::$pdo->query("SELECT COUNT(*) FROM `{$p}_enroll`")->fetchColumn() == 0);
Harness::check('แผนที่เลือกถูกล้าง', Harness::$pdo->query("SELECT COUNT(*) FROM `{$p}_enroll_choices`")->fetchColumn() == 0);
Harness::check('เลขรันนิ่งถูกล้าง', Harness::$pdo->query("SELECT COUNT(*) FROM `{$p}_number`")->fetchColumn() == 0);
Harness::check('ระดับชั้น/แผนการเรียนยังอยู่',
    Harness::$pdo->query("SELECT COUNT(*) FROM `{$p}_enroll_levels`")->fetchColumn() == 3);

echo "\n=== เมนูและสิทธิ์ ===\n";
$permissions = \Gcms\Controller::getPermissionOptions((object) ['status' => 0, 'permission' => []]);
Harness::check('สิทธิ์ can_manage_enroll ถูกเพิ่มเข้าระบบ',
    in_array('can_manage_enroll', array_column($permissions, 'value'), true), json_encode(array_column($permissions, 'value')));

// หาเมนู Enroll ทั้งระดับบนและใต้ Settings
$findEnroll = function ($menus) {
    foreach ($menus as $menu) {
        if (($menu['title'] ?? '') === '{LNG_Enroll}') {
            return ['top', $menu];
        }
        foreach ($menu['children'] ?? [] as $child) {
            if (($child['title'] ?? '') === '{LNG_Enroll}') {
                return ['settings', $child];
            }
        }
    }
    return [null, null];
};
$urls = function ($menu) {
    return array_column($menu['children'] ?? [], 'url');
};

// ผู้จัดการรับสมัครที่ไม่มีสิทธิ์ตั้งค่า: ไม่มีเมนู Settings ให้แทรก ต้องขึ้นเป็นเมนูหลัก
$menus = \Index\Menus\Controller::getMenus((object) ['id' => 3, 'status' => 0, 'permission' => ['can_manage_enroll']]);
list($where, $enrollMenu) = $findEnroll($menus);
Harness::check('ผู้จัดการเห็นเมนู Enroll เป็นเมนูหลัก', $where === 'top', json_encode(array_column($menus, 'title'), JSON_UNESCAPED_UNICODE));
Harness::check('ผู้จัดการเห็นเฉพาะระดับชั้นและแผนการเรียน (หน้าที่เปิดได้)',
    $urls($enrollMenu) === ['/enroll-levels', '/enroll-plans'], json_encode($urls($enrollMenu)));

$menus = \Index\Menus\Controller::getMenus((object) ['id' => 5, 'status' => 0, 'permission' => []]);
list($where) = $findEnroll($menus);
Harness::check('สมาชิกทั่วไปไม่เห็นเมนู Enroll', $where === null, json_encode(array_column($menus, 'title'), JSON_UNESCAPED_UNICODE));

// ผู้ดูแลที่มีทั้งสองสิทธิ์: อยู่ใต้ Settings ครบ 5 รายการ
$menus = \Index\Menus\Controller::getMenus((object) ['id' => 1, 'status' => 1, 'permission' => ['can_config', 'can_manage_enroll']]);
list($where, $enrollMenu) = $findEnroll($menus);
Harness::check('ผู้ดูแลเห็นเมนู Enroll ใต้ Settings', $where === 'settings', (string) $where);
Harness::check('ใต้ Settings มีครบ 6 รายการ (รวมรายละเอียดของหน้า สำหรับผู้ดูแลระบบ)',
    $urls($enrollMenu) === ['/enroll-settings', '/enroll-pages', '/enroll-levels', '/enroll-plans', '/language?key=ACADEMIC_RESULTS', '/language?key=PARENT_LIST'],
    json_encode($urls($enrollMenu)));

// มีแค่สิทธิ์ตั้งค่า: เห็นตั้งค่าโมดูลและรายการภาษา ไม่เห็นหน้าที่ต้องใช้ can_manage_enroll
$menus = \Index\Menus\Controller::getMenus((object) ['id' => 2, 'status' => 0, 'permission' => ['can_config']]);
list($where, $enrollMenu) = $findEnroll($menus);
Harness::check('มีแค่ can_config → เห็นตั้งค่าโมดูลและรายการภาษาเท่านั้น',
    $where === 'settings' && $urls($enrollMenu) === ['/enroll-settings', '/language?key=ACADEMIC_RESULTS', '/language?key=PARENT_LIST'],
    json_encode([$where, $urls($enrollMenu)]));

// เมนูระดับบน: ตารางผู้สมัคร (/enrolls) เฉพาะผู้จัดการ, รายงานผู้สมัคร (/enroll-applicants) สมาชิกทุกคน
// แสดงตามสิทธิ์เดียวกับ API ของหน้านั้น (กดแล้วต้องเปิดได้จริง)
$allUrls = function ($menus) use (&$allUrls) {
    $result = [];
    foreach ($menus as $menu) {
        if (!empty($menu['url'])) {
            $result[] = $menu['url'];
        }
        $result = array_merge($result, $allUrls($menu['children'] ?? []));
    }
    return $result;
};
$urlsOf = function ($login) use ($allUrls) {
    return array_values(array_intersect($allUrls(\Index\Menus\Controller::getMenus($login)), ['/enrolls', '/enroll-applicants']));
};
Harness::check('ผู้จัดการเห็นตารางผู้สมัครและรายงาน', $urlsOf((object) ['id' => 3, 'status' => 0, 'permission' => ['can_manage_enroll']]) === ['/enrolls', '/enroll-applicants'],
    json_encode($urlsOf((object) ['id' => 3, 'status' => 0, 'permission' => ['can_manage_enroll']])));
Harness::check('สมาชิกทั่วไปเห็นเฉพาะรายงาน (ตารางผู้สมัครตอบ 403 จึงไม่แสดง)', $urlsOf((object) ['id' => 5, 'status' => 0, 'permission' => []]) === ['/enroll-applicants'],
    json_encode($urlsOf((object) ['id' => 5, 'status' => 0, 'permission' => []])));
Harness::check('ไม่ได้เข้าระบบ ไม่มีเมนูทั้งสอง', $urlsOf(null) === [], json_encode($urlsOf(null)));

$code = Harness::summary();
Harness::cleanup();
exit($code);
