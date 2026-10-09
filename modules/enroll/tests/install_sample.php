<?php
/**
 * ผู้สมัครตัวอย่างที่ติดมากับ modules/enroll/install/database.sql
 *
 * ข้อมูลตัวอย่างเขียนเป็น SQL ตรง ๆ ไม่ผ่านตัวบันทึกของระบบ ถ้าค่าใดไม่ตรงกับที่ระบบสร้างเอง
 * (เลขบัตร link เลขผู้สมัคร แผนข้ามระดับ ที่อยู่ JSON) หน้าเว็บจะพังตั้งแต่เปิดครั้งแรกหลังติดตั้ง
 * ชุดนี้ตรวจทั้งตัวข้อมูล และเปิดทุกหน้าที่ใช้ข้อมูลนี้ผ่าน API จริง
 */
require __DIR__.'/harness.php';
Harness::boot(__DIR__, true);

$admin = Harness::login(1, 'can_manage_enroll,can_config', 1);
$member = Harness::login(5, '', 0);
$p = Harness::PREFIX;
$cfg = \Kotchasan\Config::create();
$pdo = Harness::$pdo;

$rows = $pdo->query("SELECT * FROM `{$p}_enroll` ORDER BY `id`")->fetchAll(PDO::FETCH_OBJ);
$choices = [];
foreach ($pdo->query("SELECT * FROM `{$p}_enroll_choices` ORDER BY `enroll_id`, `no`")->fetchAll(PDO::FETCH_OBJ) as $c) {
    $choices[$c->enroll_id][] = $c;
}
$planLevel = $pdo->query("SELECT `id`, `level_id` FROM `{$p}_enroll_plans`")->fetchAll(PDO::FETCH_KEY_PAIR);
$levels = $pdo->query("SELECT `id`, `topic` FROM `{$p}_enroll_levels`")->fetchAll(PDO::FETCH_KEY_PAIR);
$perLevel = [];
foreach ($rows as $row) {
    $perLevel[$row->level] = ($perLevel[$row->level] ?? 0) + 1;
}

echo "\n=== ตัวข้อมูล ===\n";
Harness::check('ติดตั้งใหม่ได้ผู้สมัครตัวอย่าง 12 คน', count($rows) === 12, count($rows).' คน');
Harness::check('มีผู้สมัครครบทุกระดับชั้นที่ติดตั้งมา', array_keys($perLevel) == array_keys($levels), json_encode($perLevel));
Harness::check('AUTO_INCREMENT ต่อจากแถวตัวอย่าง (ใบสมัครจริงใบแรกได้ id 13)',
    (int) $pdo->query("SELECT `AUTO_INCREMENT` FROM information_schema.TABLES WHERE TABLE_SCHEMA = '".Harness::DB_NAME."' AND TABLE_NAME = '{$p}_enroll'")->fetchColumn() === 13);

$bad = [];
foreach ($rows as $row) {
    if (!\Enroll\Enroll\Model::validThaiId($row->id_card)) {
        $bad[] = $row->id;
    }
}
Harness::check('เลขบัตรประชาชนผ่านหลักตรวจสอบทุกใบ (แก้ไขใบสมัครแล้วบันทึกได้)', $bad === [], json_encode($bad));
Harness::check('เลขบัตรประชาชนไม่ซ้ำกัน', count(array_unique(array_column($rows, 'id_card'))) === 12);
Harness::check('link เป็นรูปแบบที่ระบบเปิดได้ (a-z0-9 32 ตัว) และไม่ซ้ำ',
    count(preg_grep('/^[a-z0-9]{32}$/', array_column($rows, 'link'))) === 12 && count(array_unique(array_column($rows, 'link'))) === 12);
Harness::check('เบอร์โทรไม่ซ้ำกัน (ไม่ติดตัวจำกัดจำนวนใบต่อเบอร์)', count(array_unique(array_column($rows, 'phone'))) === 12);
Harness::check('อีเมลเป็นโดเมนสงวน example.com หรือว่าง (ทดลองส่งลิงก์ไม่ถึงใครจริง)',
    array_filter($rows, function ($row) {
        return $row->email !== null && !preg_match('/@example\.com$/', $row->email);
    }) === []);

// เลขผู้สมัครเท่ากับที่ makeEnrollNo() ออกให้แถวนั้นในปีการศึกษา 2569
$schoolYear = $cfg->school_year;
$cfg->school_year = 2569;
$bad = [];
foreach ($rows as $row) {
    if (\Enroll\Enroll\Model::makeEnrollNo($row->id, $row->level) !== $row->enroll_no) {
        $bad[] = $row->enroll_no;
    }
}
$cfg->school_year = $schoolYear;
Harness::check('เลขผู้สมัครตรงกับรูปแบบที่ระบบออกให้ (E + ปี + ระดับ + id)', $bad === [], json_encode($bad));

$bad = [];
foreach ($rows as $row) {
    $list = $choices[$row->id] ?? [];
    if (empty($list) || $list[0]->no != 0) {
        $bad[] = $row->id.': ไม่มีแผน';
    }
    foreach ($list as $c) {
        if (($planLevel[$c->plan_id] ?? 0) != $row->level) {
            $bad[] = $row->id.': แผน '.$c->plan_id;
        }
    }
    if ($row->result_plan > 0 && (($planLevel[$row->result_plan] ?? 0) != $row->level || $row->result_status != 1)) {
        $bad[] = $row->id.': ผล '.$row->result_plan;
    }
}
Harness::check('แผนที่เลือก/แผนที่ได้ เป็นของระดับชั้นของผู้สมัคร (แผนที่ได้มีเฉพาะใบที่อนุมัติ)', $bad === [], json_encode($bad, JSON_UNESCAPED_UNICODE));

$bad = $pdo->query("SELECT E.`id` FROM `{$p}_enroll` E
    LEFT JOIN `{$p}_district` D ON D.`country` = 'TH' AND D.`id` = E.`districtID` AND D.`amphur_id` = E.`amphurID`
    LEFT JOIN `{$p}_amphur` A ON A.`country` = 'TH' AND A.`id` = E.`amphurID` AND A.`province_id` = E.`provinceID`
    WHERE D.`id` IS NULL OR A.`id` IS NULL")->fetchAll(PDO::FETCH_COLUMN);
Harness::check('ตำบล/อำเภอ/จังหวัด มีอยู่จริงและอยู่ในกันและกัน', $bad === [], json_encode($bad));

$parentKeys = array_keys(\Kotchasan\Language::get('PARENT_LIST', []));
$academicKeys = array_keys(\Kotchasan\Language::get('ACADEMIC_RESULTS', []));
$bad = [];
foreach ($rows as $row) {
    $parent = json_decode($row->parent, true);
    $academic = json_decode($row->academic_results, true);
    if (!is_array($parent) || array_keys($parent) !== $parentKeys || !is_array($academic) || array_keys($academic) !== $academicKeys
        || $academic['GPA'] <= 0 || $academic['GPA'] > 4) {
        $bad[] = $row->id;
    }
}
Harness::check('ผู้ปกครอง/ผลการเรียน เป็น JSON รูปแบบเดียวกับที่ฟอร์มบันทึก', $bad === [], json_encode($bad));

$statuses = array_unique(array_map('intval', array_column($rows, 'result_status')));
sort($statuses);
Harness::check('มีหลายสถานะให้เห็น (รอ/อนุมัติ/เอกสารไม่ครบ/ไม่อนุมัติ/สำรอง)', $statuses === [0, 1, 2, 3, 4, 5], json_encode($statuses));
Harness::check('มีใบที่ติดป้ายกันสแปมให้เห็นตัวกรอง "ต้องตรวจสอบ"', count(array_filter(array_column($rows, 'review'))) === 2);

echo "\n=== หน้าของเจ้าหน้าที่ ===\n";
foreach ($perLevel as $level => $count) {
    $r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => $level]]);
    Harness::check("ตารางผู้สมัคร ระดับ $level → $count แถว", !empty($r['success']) && count($r['data']['data'] ?? []) === $count,
        json_encode($r['data']['meta'] ?? $r, JSON_UNESCAPED_UNICODE));
}
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 1, 'review' => 1]]);
Harness::check('ตัวกรองต้องตรวจสอบ (ม.1) → 1 แถว', count($r['data']['data'] ?? []) === 1);
$r = Harness::call('\Enroll\Enrolls\Controller', 'index', ['token' => $admin, 'get' => ['level' => 4, 'status' => 4]]);
Harness::check('ตัวกรองสถานะสำรอง (ม.4) → 1 แถว', count($r['data']['data'] ?? []) === 1);

$r = Harness::call('\Index\Dashboard\Controller', 'index', ['token' => $admin]);
$cards = [];
foreach ($r['data']['cards'] ?? [] as $card) {
    if ($card['title'] === 'จำนวนผู้ลงทะเบียน' || strpos((string) $card['url'], '/enrolls?level=') === 0) {
        $cards[] = $card;
    }
}
Harness::check('การ์ดหน้าแรก: รวม 12 คน, ระดับละ 5/4/3', array_column($cards, 'value') === ['12', '5', '4', '3'],
    json_encode(array_column($cards, 'value')));

$bad = [];
$editable = array_map('intval', $cfg->enroll_editable);
foreach ($rows as $row) {
    $d = Harness::call('\Enroll\Enroll\Controller', 'get', ['token' => $admin, 'get' => ['id' => $row->link]])['data']['data'] ?? [];
    $parent = json_decode($row->parent, true);
    if (($d['name'] ?? '') !== $row->name || ($d['district'] ?? '') === '' || ($d['amphur'] ?? '') === '' || ($d['province'] ?? '') === ''
        || ($d['parent_fields'][0]['name'] ?? null) !== $parent['father']['name']
        || (string) ($d['academic_fields'][0]['value'] ?? '') !== (string) json_decode($row->academic_results, true)['GPA']
        || ($d['can_save'] ?? null) !== in_array((int) $row->result_status, $editable, true)) {
        $bad[] = $row->id;
    }
}
Harness::check('เปิดใบสมัครของเจ้าหน้าที่ได้ทุกใบ ข้อมูลและที่อยู่ครบ แก้ได้ตามสถานะ', $bad === [], json_encode($bad));

$r = Harness::call('\Enroll\Applicants\Controller', 'index', ['token' => $member, 'get' => ['level' => 5]]);
Harness::check('รายชื่อสำหรับสมาชิก (ปวช.) → 3 แถว', count($r['data']['data'] ?? []) === 3);

echo "\n=== หน้าของผู้สมัคร ===\n";
$approved = $rows[0];
$d = Harness::call('\Enroll\Result\Controller', 'get', ['get' => ['id' => $approved->link]])['data']['data'] ?? [];
Harness::check('หน้าผลการสมัคร: ใบที่อนุมัติแสดงแผนที่ได้', ($d['found'] ?? null) === true && ($d['result_status_text'] ?? '') === 'อนุมัติ'
    && ($d['plan'] ?? '') === 'คณิต-วิทย์' && array_key_exists('picture', $d) && $d['picture'] === null,
    json_encode(array_intersect_key($d, array_flip(['found', 'result_status_text', 'plan', 'picture'])), JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Result\Controller', 'lookup', ['post' => ['id_card' => $approved->id_card, 'birthday' => $approved->birthday]]);
$redirect = '';
foreach ($r['data']['actions'] ?? [] as $a) {
    if (($a['type'] ?? '') === 'redirect') {
        $redirect = $a['url'];
    }
}
Harness::check('ค้นผลด้วยเลขบัตร + วันเกิดของผู้สมัครตัวอย่างได้', $redirect === '/enroll-result?id='.$approved->link, $redirect);
$html = Harness::call('\Enroll\Printform\Controller', 'index', ['get' => ['id' => $approved->link]])['__raw'] ?? '';
Harness::check('พิมพ์ใบสมัครได้ (ไม่มีรูปก็ไม่พัง)', strpos($html, $approved->name) !== false && strpos($html, $approved->enroll_no) !== false);
$pending = $rows[2];
$d = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $pending->link]])['data']['data'] ?? [];
Harness::check('ผู้สมัครเปิดแก้ใบที่ยังรอดำเนินการได้', ($d['is_new'] ?? null) === false && ($d['name'] ?? '') === $pending->name,
    json_encode($d['name'] ?? $d, JSON_UNESCAPED_UNICODE));

$cfg->enroll_announce = 1;
$cfg->enroll_announce_statuses = [1];
$d = Harness::call('\Enroll\Announce\Controller', 'get', [])['data']['data'] ?? [];
Harness::check('ประกาศผล (อนุมัติ) → 4 คน', count($d['rows'] ?? []) === 4, json_encode($d['rows'] ?? $d, JSON_UNESCAPED_UNICODE));
$cfg->enroll_announce = 0;

echo "\n=== ลบข้อมูลตัวอย่างก่อนเปิดรับสมัครจริง ===\n";
$r = Harness::call('\Enroll\Settings\Controller', 'reset', ['token' => $admin, 'post' => [], 'method' => 'POST']);
Harness::check('Reset database ลบผู้สมัครตัวอย่างทั้งหมด', !empty($r['success'])
    && (int) $pdo->query("SELECT COUNT(*) FROM `{$p}_enroll`")->fetchColumn() === 0
    && (int) $pdo->query("SELECT COUNT(*) FROM `{$p}_enroll_choices`")->fetchColumn() === 0, json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ระดับชั้น/แผนการเรียนที่ติดตั้งมายังอยู่',
    (int) $pdo->query("SELECT COUNT(*) FROM `{$p}_enroll_levels`")->fetchColumn() === 3
    && (int) $pdo->query("SELECT COUNT(*) FROM `{$p}_enroll_plans`")->fetchColumn() === 8);

$code = Harness::summary();
Harness::cleanup();
exit($code);
