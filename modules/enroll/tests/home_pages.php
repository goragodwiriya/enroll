<?php
require __DIR__.'/harness.php';
Harness::boot(__DIR__);

$admin = Harness::login(1, 'can_config,can_manage_enroll', 1);
$config = Harness::login(2, 'can_config', 0);
$member = Harness::login(5, '', 0);
$otherAdmin = Harness::login(7, '', 1);

// หน้าที่แก้ไขได้อยู่ใน datas/pages ของโปรเจ็คจริง สำรองไว้แล้วคืนตอนจบ
$pagesDir = ROOT_PATH.DATA_FOLDER.'pages/';
$hadPagesDir = is_dir($pagesDir);
$backup = [];
foreach (['dashboard_th', 'dashboard_en', 'result_th', 'result_en'] as $name) {
    if (is_file($pagesDir.$name.'.html')) {
        $backup[$name] = file_get_contents($pagesDir.$name.'.html');
        unlink($pagesDir.$name.'.html');
    }
}

echo "\n=== หน้าแรกของผู้สมัคร (ไม่ต้องเข้าระบบ) ===\n";
$r = Harness::call('\Enroll\Home\Controller', 'get', []);
$d = $r['data']['data'] ?? [];
Harness::check('เปิดได้โดยไม่ต้องเข้าระบบ', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('มีข้อมูลโรงเรียนและสถานะการรับสมัคร', isset($d['school_name'], $d['school_year']) && ($d['is_open'] ?? null) === true,
    json_encode(array_keys($d)));
Harness::check('ยังไม่มีเนื้อหาที่ผู้ดูแลเขียน → ใช้เนื้อหาเริ่มต้นของโมดูล', strpos($d['content'] ?? '', 'ขั้นตอนการสมัคร') !== false);

$cfg = \Kotchasan\Config::create();
$cfg->enroll_begin = strtotime('-10 day');
$cfg->enroll_end = strtotime('-1 day');
$d = Harness::call('\Enroll\Home\Controller', 'get', [])['data']['data'] ?? [];
Harness::check('นอกช่วงรับสมัคร → is_open = false (ซ่อนปุ่มและเมนูสมัครเรียน)', ($d['is_open'] ?? null) === false);
Harness::check('มีช่วงวันที่รับสมัครให้แสดง', ($d['period'] ?? '') !== '', $d['period'] ?? '');
$cfg->enroll_begin = 0;
$cfg->enroll_end = 0;

echo "\n=== แก้ไขรายละเอียดของหน้า: สิทธิ์ ===\n";
$r = Harness::call('\Enroll\Pages\Controller', 'get', []);
Harness::check('ไม่เข้าระบบ → 401', ($r['code'] ?? 0) === 401, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Pages\Controller', 'get', ['token' => $member]);
Harness::check('สมาชิกทั่วไป → 403', ($r['code'] ?? 0) === 403);
$r = Harness::call('\Enroll\Pages\Controller', 'get', ['token' => $config]);
Harness::check('มีแค่สิทธิ์ตั้งค่า (ไม่ใช่ผู้ดูแลสูงสุด) → 403 เหมือนระบบเดิม', ($r['code'] ?? 0) === 403);
$r = Harness::call('\Enroll\Pages\Controller', 'save', ['token' => $config, 'post' => ['src' => 'dashboard', 'language' => 'th', 'detail' => 'x']]);
Harness::check('มีแค่สิทธิ์ตั้งค่า บันทึกไม่ได้', ($r['code'] ?? 0) === 403);

$r = Harness::call('\Enroll\Pages\Controller', 'get', ['token' => $otherAdmin]);
Harness::check('ผู้ดูแลระบบ (status 1) ที่ไม่ใช่ id 1 แก้ได้ (Login::isAdmin ของระบบเดิม)', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== แก้ไขรายละเอียดของหน้า: อ่าน/บันทึก ===\n";
$r = Harness::call('\Enroll\Pages\Controller', 'get', ['token' => $admin, 'get' => ['src' => 'dashboard', 'language' => 'th']]);
$d = $r['data']['data'] ?? [];
Harness::check('ผู้ดูแลสูงสุดอ่านได้ ได้เนื้อหาเริ่มต้นมาแก้', !empty($r['success']) && strpos($d['detail'] ?? '', 'ขั้นตอนการสมัคร') !== false,
    json_encode($r, JSON_UNESCAPED_UNICODE));
$srcs = array_column($r['data']['options']['src'] ?? [], 'value');
Harness::check('เลือกได้ 3 หน้า: หน้าแรก ผลการสมัคร (เหมือนระบบเดิม) และประกาศความเป็นส่วนตัว', $srcs === ['dashboard', 'result', 'privacy'], json_encode($srcs));
Harness::check('มีลิงก์ดูหน้าจริงที่ไม่พาผู้ดูแลไปหน้ารายชื่อ', ($d['view_url'] ?? '') === '/home', $d['view_url'] ?? '');
$r = Harness::call('\Enroll\Pages\Controller', 'get', ['token' => $admin, 'get' => ['src' => '../../settings', 'language' => 'xx']]);
Harness::check('หน้า/ภาษาที่ไม่มี → ใช้หน้าแรก ภาษาที่ใช้อยู่', ($r['data']['data']['src'] ?? '') === 'dashboard' && ($r['data']['data']['language'] ?? '') === 'th',
    json_encode($r['data']['data'] ?? [], JSON_UNESCAPED_UNICODE));

$html = '<h2>ประกาศรับสมัคร</h2><p>เปิดรับ ม.1 และ ม.4 <?php echo 1; ?>{LNG_Save}</p>';
$r = Harness::call('\Enroll\Pages\Controller', 'save', ['token' => $admin, 'post' => ['src' => 'dashboard', 'language' => 'th', 'detail' => $html]]);
Harness::check('บันทึกได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$saved = (string) @file_get_contents($pagesDir.'dashboard_th.html');
Harness::check('เขียนไฟล์ datas/pages/dashboard_th.html (ชื่อเดียวกับระบบเดิม)', strpos($saved, 'ประกาศรับสมัคร') !== false);
Harness::check('ตัดโค้ด PHP และวงเล็บปีกกาออก', strpos($saved, '<?php') === false && strpos($saved, '{LNG_') === false, $saved);

$d = Harness::call('\Enroll\Home\Controller', 'get', [])['data']['data'] ?? [];
Harness::check('หน้าแรกแสดงเนื้อหาที่บันทึก', strpos($d['content'] ?? '', 'ประกาศรับสมัคร') !== false);

$r = Harness::call('\Enroll\Pages\Controller', 'save', ['token' => $admin, 'post' => ['src' => 'result', 'language' => 'th', 'detail' => '<p>ประกาศผล 1 มีนาคม</p>']]);
$d = Harness::call('\Enroll\Result\Controller', 'get', [])['data']['data'] ?? [];
Harness::check('ข้อความในหน้าผลการสมัครมาจากหน้า result', strpos($d['content'] ?? '', 'ประกาศผล 1 มีนาคม') !== false,
    json_encode($d['content'] ?? null, JSON_UNESCAPED_UNICODE));

foreach ([['../../settings/config', 'th'], ['dashboard', '../x'], ['other', 'th']] as $bad) {
    $r = Harness::call('\Enroll\Pages\Controller', 'save', ['token' => $admin, 'post' => ['src' => $bad[0], 'language' => $bad[1], 'detail' => 'x']]);
    Harness::check('ปฏิเสธหน้า/ภาษาที่ไม่อยู่ในรายการ: '.$bad[0].'_'.$bad[1], empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
}

// คืนสภาพ datas/pages
foreach (['dashboard_th', 'dashboard_en', 'result_th', 'result_en'] as $name) {
    @unlink($pagesDir.$name.'.html');
}
foreach ($backup as $name => $content) {
    file_put_contents($pagesDir.$name.'.html', $content);
}
if (!$hadPagesDir && is_dir($pagesDir) && count(array_diff(scandir($pagesDir), ['.', '..'])) === 0) {
    rmdir($pagesDir);
}

$code = Harness::summary();
Harness::cleanup();
exit($code);
