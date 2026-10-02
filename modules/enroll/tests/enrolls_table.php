<?php
require __DIR__.'/harness.php';
Harness::boot(__DIR__);

$admin = Harness::login(1, 'can_manage_enroll,can_config', 1);
$member = Harness::login(5, '', 0);
$p = Harness::PREFIX;

// ผู้สมัคร 5 คน: ระดับ 1 จำนวน 3, ระดับ 4 จำนวน 2
$rows = [
    [1, 1, 'E690001', 'กานดา ใจงาม', '{"GPA":3.90}', 1, 0, '2026-01-01 09:00:00'],
    [2, 1, 'E690002', 'ขจร ยอดเยี่ยม', '{"GPA":2.50}', 2, 1, '2026-01-02 09:00:00'],
    [3, 1, 'E690003', 'คมสัน ตั้งใจ', '{"GPA":3.10}', 0, 2, '2026-01-03 09:00:00'],
    [4, 4, 'E690004', 'งามพิศ เรียนดี', '{"GPA":3.50}', 4, 0, '2026-01-04 09:00:00'],
    [5, 4, 'E690005', 'จรัส แสงทอง', '{"GPA":2.80}', 0, 3, '2026-01-05 09:00:00']
];
foreach ($rows as $r) {
    $stmt = Harness::$pdo->prepare("INSERT INTO `{$p}_enroll`
        (`id`,`level`,`enroll_no`,`title`,`name`,`id_card`,`birthday`,`phone`,`email`,`nationality`,`religion`,
         `address`,`districtID`,`amphurID`,`provinceID`,`zipcode`,`parent`,`original_school`,`academic_results`,
         `created_at`,`link`,`result_plan`,`result_status`)
        VALUES (?, ?, ?, 1, ?, ?, '2012-01-01', '0800000000', 'a@b.c', 'ไทย', 'พุทธ',
         '1/1', 1010101, 10101, 101, '81000', ?, 'โรงเรียนเดิม', ?, ?, ?, ?, ?)");
    $stmt->execute([$r[0], $r[1], $r[2], $r[3], '110000000000'.$r[0],
        '{"father":{"name":"พ่อ '.$r[3].'","phone":"0811111111"},"mother":{"name":"","phone":""},"parent":{"name":"","phone":""}}',
        $r[4], $r[7], str_pad((string) $r[0], 32, 'z'), $r[5], $r[6]]);
}
Harness::$pdo->exec("INSERT INTO `{$p}_enroll_choices` (`enroll_id`,`no`,`plan_id`) VALUES
    (1,0,1),(1,1,2), (2,0,2), (3,0,3), (4,0,4),(4,1,5), (5,0,6)");

echo "\n=== สิทธิ์ ===\n";
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', []);
Harness::check('ไม่ส่ง token → 401', ($r['code'] ?? 0) === 401);
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $member]);
Harness::check('สมาชิกทั่วไป → 403', ($r['code'] ?? 0) === 403);

echo "\n=== ตารางผู้สมัคร ===\n";
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin]);
$d = $r['data'] ?? [];
Harness::check('อ่านตารางได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ไม่ระบุระดับ → ใช้ระดับแรก และได้ 3 แถว', count($d['data'] ?? []) === 3, count($d['data'] ?? []).' แถว');
Harness::check('meta บอกจำนวนรวมถูกต้อง', ($d['meta']['total'] ?? 0) === 3, json_encode($d['meta'] ?? []));
$row = $d['data'][0] ?? [];
Harness::check('มีบาร์โค้ดในแถว', strpos($row['barcode'] ?? '', 'data:image/png;base64,') === 0);
Harness::check('มีวันที่จัดรูปแบบแล้ว', !empty($row['created_at_text']), $row['created_at_text'] ?? '-');
Harness::check('มีลิงก์พิมพ์และลิงก์แก้ไข', !empty($row['print_url']) && !empty($row['edit_url']));
Harness::check('ไม่ส่ง academic_results ดิบออกไป', !array_key_exists('academic_results', $row));

echo "\n=== ตัวกรอง ===\n";
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 4]]);
Harness::check('กรองระดับ 4 → 2 แถว', count($r['data']['data'] ?? []) === 2);
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 1, 'status' => 1]]);
Harness::check('กรองสถานะ = อนุมัติ → 1 แถว', count($r['data']['data'] ?? []) === 1);
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 1, 'status' => '']]);
Harness::check('สถานะว่าง = ทั้งหมด → 3 แถว', count($r['data']['data'] ?? []) === 3);
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 1, 'plan' => 2]]);
Harness::check('กรองแผนที่ได้รับ = 2 → 1 แถว', count($r['data']['data'] ?? []) === 1);
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 1, 'search' => 'ขจร']]);
Harness::check('ค้นหาชื่อได้', count($r['data']['data'] ?? []) === 1, json_encode($r['data']['meta'] ?? []));
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 1, 'search' => 'E690002']]);
Harness::check('ค้นหาเลขผู้สมัครได้', count($r['data']['data'] ?? []) === 1);

echo "\n=== เรียงลำดับตามผลการเรียน (ดึงค่าออกจาก JSON) ===\n";
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 1, 'sort' => 'academic_value desc']]);
$values = array_column($r['data']['data'] ?? [], 'academic_value');
Harness::check('เรียงจากมากไปน้อยถูกต้อง', $values === [3.9, 3.1, 2.5], json_encode($values));
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 1, 'sort' => 'academic_value asc']]);
Harness::check('เรียงจากน้อยไปมากถูกต้อง', array_column($r['data']['data'] ?? [], 'academic_value') === [2.5, 3.1, 3.9]);

echo "\n=== ตัวเลือกและหัวคอลัมน์ ===\n";
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 1]]);
$f = $r['data']['filters'] ?? [];
Harness::check('มีตัวเลือกครบ 4 ตัวกรอง', isset($f['level'], $f['plan'], $f['status'], $f['result']), json_encode(array_keys($f)));
Harness::check('ตัวเลือกแผนเป็นของระดับที่เลือก', count($f['plan']) === 3, json_encode($f['plan'], JSON_UNESCAPED_UNICODE));
Harness::check('PHP ไม่เติม "ทั้งหมด" มาเอง', !in_array('ทั้งหมด', array_column($f['status'], 'text'), true));
$o = $r['data']['options'] ?? [];
Harness::check('มีตัวเลือกของ select ในแถว', isset($o['result_plan'], $o['result_status']));
Harness::check('ชื่อคอลัมน์ผลการเรียนตามที่เลือก', ($o['academic_label'] ?? '') === 'เกรดเฉลี่ยสะสม (GPA)', $o['academic_label'] ?? '');

echo "\n=== เปลี่ยนแผน/สถานะรายแถว ===\n";
$r = Harness::call('\Enroll\Enrolls\Controller', 'action', ['token' => $admin, 'post' => ['action' => 'status', 'id' => 3, 'value' => 1]]);
Harness::check('เปลี่ยนผลการสมัครได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('บันทึกลงฐานข้อมูลจริง',
    Harness::$pdo->query("SELECT result_status FROM `{$p}_enroll` WHERE id=3")->fetchColumn() == 1);
$r = Harness::call('\Enroll\Enrolls\Controller', 'action', ['token' => $admin, 'post' => ['action' => 'plan', 'id' => 3, 'value' => 3]]);
Harness::check('เปลี่ยนแผนที่ได้รับได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enrolls\Controller', 'action', ['token' => $admin, 'post' => ['action' => 'plan', 'id' => 3, 'value' => 7]]);
Harness::check('กำหนดแผนของระดับอื่นไม่ได้', empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enrolls\Controller', 'action', ['token' => $admin, 'post' => ['action' => 'status', 'id' => 3, 'value' => 99]]);
Harness::check('สถานะที่ไม่มีอยู่จริงไม่ได้', empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enrolls\Controller', 'action', ['token' => $member, 'post' => ['action' => 'status', 'id' => 3, 'value' => 1]]);
Harness::check('สมาชิกทั่วไปเปลี่ยนไม่ได้', empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== ลบใบสมัคร ===\n";
$r = Harness::call('\Enroll\Enrolls\Controller', 'action', ['token' => $admin, 'post' => ['action' => 'delete', 'ids' => [5]]]);
Harness::check('ลบได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('แถวหายจากฐานข้อมูล', Harness::$pdo->query("SELECT COUNT(*) FROM `{$p}_enroll` WHERE id=5")->fetchColumn() == 0);
Harness::check('แผนที่เลือกของแถวนั้นถูกลบด้วย',
    Harness::$pdo->query("SELECT COUNT(*) FROM `{$p}_enroll_choices` WHERE enroll_id=5")->fetchColumn() == 0);

echo "\n=== การ์ดสรุปจำนวนผู้สมัคร ===\n";
$r = Harness::call('\Enroll\Enrolls\Controller', 'summary', ['token' => $admin]);
$levels = $r['data']['data']['levels'] ?? [];
Harness::check('มีการ์ดครบทุกระดับชั้น', count($levels) === 3, json_encode($levels, JSON_UNESCAPED_UNICODE));
Harness::check('นับจำนวนถูกต้อง (ระดับ 1 = 3, ระดับ 4 = 1)',
    $levels[0]['count'] === 3 && $levels[1]['count'] === 1, json_encode(array_column($levels, 'count')));
Harness::check('รวมทั้งหมด 4 คน', ($r['data']['data']['total'] ?? 0) === 4);
Harness::check('การ์ดลิงก์ไปตารางของระดับนั้น', ($levels[0]['url'] ?? '') === '/enroll?level=1');

echo "\n=== รายชื่อสำหรับสมาชิก (อ่านอย่างเดียว) ===\n";
$r = Harness::call('\Enroll\Applicants\Controller', 'index', ['token' => $member, 'get' => ['level' => 1]]);
Harness::check('สมาชิกทั่วไปดูได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$row = $r['data']['data'][0] ?? [];
Harness::check('ไม่มีเลขบัตร/เบอร์โทร/ลิงก์แก้ไข',
    !isset($row['id_card'], $row['phone'], $row['edit_url'], $row['link']), json_encode(array_keys($row)));
Harness::check('มีชื่อ แผนการเรียน และสถานะ', isset($row['name'], $row['plan_text'], $row['result_status_text']));
$r = Harness::call('\Enroll\Applicants\Controller', 'index', []);
Harness::check('ไม่ล็อกอิน → 401', ($r['code'] ?? 0) === 401);

$code = Harness::summary();
Harness::cleanup();
exit($code);
