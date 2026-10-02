<?php
require __DIR__.'/harness.php';
Harness::boot(__DIR__);

$admin = Harness::login(1, 'can_manage_enroll,can_config', 1);
$plain = Harness::login(5, '', 0);

echo "\n=== สิทธิ์ ===\n";
$r = Harness::call('\Enroll\Levels\Controller', 'get', []);
Harness::check('ไม่ส่ง token → 401', ($r['code'] ?? 0) === 401, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Levels\Controller', 'get', ['token' => $plain]);
Harness::check('ไม่มีสิทธิ์ → 403', ($r['code'] ?? 0) === 403, json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== ระดับชั้น: อ่าน ===\n";
$r = Harness::call('\Enroll\Levels\Controller', 'get', ['token' => $admin]);
Harness::check('อ่านสำเร็จ', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$table = $r['data']['data']['table'] ?? null;
Harness::check('มี columns/data', isset($table['columns'], $table['data']));
Harness::check('ได้ระดับชั้นตั้งต้น 3 รายการ', count($table['data'] ?? []) === 3, count($table['data'] ?? []).' รายการ');
Harness::check('แถวมี __rowKey เท่ากับ id', ($table['data'][0]['__rowKey'] ?? null) === (string) ($table['data'][0]['id'] ?? -1));

echo "\n=== ระดับชั้น: บันทึก ===\n";
$r = Harness::call('\Enroll\Levels\Controller', 'save', [
    'token' => $admin,
    'post' => [
        'id' => ['1' => '1', '4' => '4', '5' => '5', 'row_1' => '7'],
        'topic' => ['1' => 'มัธยมศึกษาปีที่ 1', '4' => 'มัธยมศึกษาปีที่ 4', '5' => 'ปวช.', 'row_1' => 'ปวส.'],
        'is_active' => ['1' => '1', '4' => '1', '5' => '1', 'row_1' => '1']
    ]
]);
Harness::check('เพิ่มระดับชั้นใหม่ได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$levels = \Enroll\Level\Model::toArray();
Harness::check('มี 4 ระดับ และมี ปวส. (id 7)', count($levels) === 4 && ($levels[7] ?? '') === 'ปวส.', json_encode($levels, JSON_UNESCAPED_UNICODE));

$r = Harness::call('\Enroll\Levels\Controller', 'save', [
    'token' => $admin,
    'post' => ['id' => ['1' => '1', '4' => '1'], 'topic' => ['1' => 'ก', '4' => 'ข']]
]);
Harness::check('ID ซ้ำ → ผิดพลาด', empty($r['success']) && isset($r['errors']['id[4]']), json_encode($r, JSON_UNESCAPED_UNICODE));

$r = Harness::call('\Enroll\Levels\Controller', 'save', [
    'token' => $admin,
    'post' => ['id' => ['1' => '1'], 'topic' => ['1' => '']]
]);
Harness::check('ไม่กรอกชื่อ → ผิดพลาดที่ topic[1]', isset($r['errors']['topic[1]']), json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== แผนการเรียน ===\n";
$r = Harness::call('\Enroll\Plans\Controller', 'get', ['token' => $admin, 'get' => ['level' => 1]]);
$plans = $r['data']['data']['table']['data'] ?? [];
Harness::check('อ่านแผนของระดับ 1 ได้ 3 รายการ', count($plans) === 3, json_encode($plans, JSON_UNESCAPED_UNICODE));
Harness::check('มีตัวเลือกระดับชั้นใน options.levels', !empty($r['data']['options']['levels']), json_encode($r['data']['options'] ?? [], JSON_UNESCAPED_UNICODE));
Harness::check('ไม่มีคอลัมน์ id (ระบบออกให้)',
    !in_array('id', array_column($r['data']['data']['table']['columns'] ?? [], 'field'), true));
Harness::check('__rowKey ของแผนคือ id', ($plans[0]['__rowKey'] ?? '') === '1');

$r = Harness::call('\Enroll\Plans\Controller', 'get', ['token' => $admin, 'get' => ['level' => 999]]);
Harness::check('ระดับที่ไม่มีอยู่ → ใช้ระดับแรกแทน', ($r['data']['data']['level'] ?? '') === '1', json_encode($r['data']['data']['level'] ?? null));

$r = Harness::call('\Enroll\Plans\Controller', 'save', [
    'token' => $admin,
    'post' => [
        'level' => 1,
        'topic' => ['1' => 'คณิต-อังกฤษ', '2' => 'คณิต-วิทย์', 'row_1' => 'ศิลป์-ภาษา'],
        'is_active' => ['1' => '1', '2' => '1', 'row_1' => '1']
    ]
]);
Harness::check('บันทึกแผน (ลบ 1 เพิ่ม 1) สำเร็จ', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$after = \Enroll\Plan\Model::toArray(1);
Harness::check('เหลือ 3 แผน และมีศิลป์-ภาษา', count($after) === 3 && in_array('ศิลป์-ภาษา', $after, true), json_encode($after, JSON_UNESCAPED_UNICODE));
Harness::check('แผนเดิม id 1,2 ยังคง id เดิม', isset($after[1], $after[2]), json_encode(array_keys($after)));

echo "\n=== ห้ามลบข้อมูลที่ถูกใช้อยู่ ===\n";
$p = Harness::PREFIX;
Harness::$pdo->exec("INSERT INTO `{$p}_enroll` (`id`,`level`,`enroll_no`,`title`,`name`,`link`,`result_plan`,`created_at`)
    VALUES (1, 1, 'E0001', 1, 'ทดสอบ', 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz1', 0, NOW())");
Harness::$pdo->exec("INSERT INTO `{$p}_enroll_choices` (`enroll_id`,`no`,`plan_id`) VALUES (1, 0, 1)");

$r = Harness::call('\Enroll\Plans\Controller', 'save', [
    'token' => $admin,
    'post' => ['level' => 1, 'topic' => ['2' => 'คณิต-วิทย์'], 'is_active' => ['2' => '1']]
]);
Harness::check('ลบแผนที่มีผู้สมัครเลือก → ปฏิเสธ', empty($r['success']) && strpos($r['message'] ?? '', 'คณิต-อังกฤษ') !== false,
    json_encode($r, JSON_UNESCAPED_UNICODE));

$r = Harness::call('\Enroll\Levels\Controller', 'save', [
    'token' => $admin,
    'post' => ['id' => ['4' => '4'], 'topic' => ['4' => 'มัธยมศึกษาปีที่ 4']]
]);
Harness::check('ลบระดับที่มีผู้สมัคร → ปฏิเสธ', empty($r['success']) && ($r['code'] ?? 0) === 400, json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== autocomplete ที่อยู่ (ไม่ต้องเข้าระบบ) ===\n";
$r = Harness::call('\Enroll\Address\Controller', 'district', ['get' => ['q' => 'เขาคราม']]);
$items = $r['data'] ?? [];
Harness::check('ค้นตำบลได้', !empty($items), json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('คืนเป็น array ตรง ๆ ไม่ห่อ key เพิ่ม', isset($items[0]['value'], $items[0]['text']), json_encode($items[0] ?? null, JSON_UNESCAPED_UNICODE));
Harness::check('มีฟิลด์สำหรับเติมอัตโนมัติครบ',
    isset($items[0]['districtID'], $items[0]['amphur'], $items[0]['amphurID'], $items[0]['province'], $items[0]['provinceID']),
    json_encode($items[0] ?? null, JSON_UNESCAPED_UNICODE));
Harness::check('ตำบลเขาคราม อยู่อำเภอเมืองกระบี่ จังหวัดกระบี่',
    ($items[0]['amphur'] ?? '') === 'เมืองกระบี่' && ($items[0]['province'] ?? '') === 'กระบี่',
    json_encode($items[0] ?? null, JSON_UNESCAPED_UNICODE));

$r = Harness::call('\Enroll\Address\Controller', 'province', ['get' => ['q' => 'เชียง']]);
Harness::check('ค้นจังหวัดขึ้นต้นด้วย "เชียง" ได้', !empty($r['data']), json_encode(array_slice($r['data'] ?? [], 0, 2), JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Address\Controller', 'amphur', ['get' => ['q' => 'เมือง', 'nodistrict' => 1]]);
Harness::check('nodistrict=1 ไม่มีตำบลในผลลัพธ์', !empty($r['data']) && !isset($r['data'][0]['district']),
    json_encode($r['data'][0] ?? null, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Address\Controller', 'district', ['get' => ['q' => '']]);
Harness::check('ไม่ส่งคำค้น → array ว่าง', ($r['data'] ?? null) === [], json_encode($r, JSON_UNESCAPED_UNICODE));

$code = Harness::summary();
Harness::cleanup();
exit($code);
