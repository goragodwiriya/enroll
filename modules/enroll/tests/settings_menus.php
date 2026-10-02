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
        $d['enroll_begin'], $d['enroll_end'], $d['status_fields']),
    json_encode(array_keys($d), JSON_UNESCAPED_UNICODE));
Harness::check('ตัวเลือกสถานะที่แก้ไขได้ มาจาก REGISTER_STATUS ครบ 7 ข้อ', count($d['status_fields']) === 7);
$checked = array_column(array_filter($d['status_fields'], fn($f) => $f['checked']), 'value');
Harness::check('ค่าเริ่มต้นติ๊กสถานะ 0 และ 2', $checked === ['0', '2'], json_encode($checked));
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
    'enroll_end' => '2026-03-31T16:30'
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

$r = Harness::call('\Enroll\Settings\Controller', 'save', [
    'token' => $admin,
    'post' => array_merge($post, ['enroll_study_plan_count' => 0, 'enroll_w' => 10, 'enroll_editable' => [0, 99]])
]);
$saved = include $configFile;
Harness::check('จำนวนแผนน้อยกว่า 1 ถูกดันเป็น 1', $saved['enroll_study_plan_count'] === 1, var_export($saved['enroll_study_plan_count'], true));
Harness::check('ขนาดรูปน้อยกว่า 100 ถูกดันเป็น 100', $saved['enroll_w'] === 100, var_export($saved['enroll_w'], true));
Harness::check('สถานะที่ไม่มีอยู่จริงถูกตัดทิ้ง', $saved['enroll_editable'] === [0], json_encode($saved['enroll_editable']));

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

$menus = \Index\Menus\Controller::getMenus((object) ['id' => 3, 'status' => 0, 'permission' => ['can_manage_enroll']]);
$keys = [];
foreach ($menus as $menu) {
    $keys[] = $menu['title'];
}
Harness::check('ผู้จัดการเห็นเมนู Enroll', in_array('{LNG_Enroll}', $keys, true), json_encode($keys, JSON_UNESCAPED_UNICODE));
$enrollMenu = null;
foreach ($menus as $menu) {
    if ($menu['title'] === '{LNG_Enroll}') { $enrollMenu = $menu; }
}
Harness::check('เมนูย่อยครบ 3 รายการ', count($enrollMenu['children'] ?? []) === 3, json_encode($enrollMenu ?? [], JSON_UNESCAPED_UNICODE));

$menus = \Index\Menus\Controller::getMenus((object) ['id' => 5, 'status' => 0, 'permission' => []]);
$titles = array_column($menus, 'title');
Harness::check('สมาชิกทั่วไปไม่เห็นเมนู Enroll', !in_array('{LNG_Enroll}', $titles, true), json_encode($titles, JSON_UNESCAPED_UNICODE));

$menus = \Index\Menus\Controller::getMenus((object) ['id' => 1, 'status' => 1, 'permission' => ['can_config', 'can_manage_enroll']]);
$settings = null;
foreach ($menus as $menu) {
    if (($menu['title'] ?? '') === 'Settings') { $settings = $menu; }
}
$settingTitles = array_column($settings['children'] ?? [], 'title');
Harness::check('เมนูตั้งค่ามีรายการของ enroll',
    in_array('{LNG_Module settings} {LNG_Enroll}', $settingTitles, true), json_encode($settingTitles, JSON_UNESCAPED_UNICODE));

$code = Harness::summary();
Harness::cleanup();
exit($code);
