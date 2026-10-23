<?php
/**
 * ฟีเจอร์เพิ่มเติมของหน้าผู้สมัคร: จำนวนที่รับต่อแผน, ยินยอม PDPA, ส่งลิงก์ใบสมัคร,
 * ประกาศผล, บัตรประจำตัวผู้สอบ, ภาษา, ประกาศความเป็นส่วนตัว
 */
require __DIR__.'/harness.php';
Harness::boot(__DIR__);

$admin = Harness::login(1, 'can_manage_enroll,can_config', 1);
$p = Harness::PREFIX;
$cfg = \Kotchasan\Config::create();

$tmp = sys_get_temp_dir().'/enroll_test_features';
@mkdir($tmp, 0777, true);
$photo = $tmp.'/photo.jpg';
$im = imagecreatetruecolor(300, 400);
imagefill($im, 0, 0, imagecolorallocate($im, 220, 230, 250));
imagejpeg($im, $photo, 80);

function thaiId($base12)
{
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $base12[$i] * (13 - $i);
    }
    return $base12.((11 - $sum % 11) % 10);
}
/** ใบสมัครใหม่ที่ผ่านทุกเงื่อนไข เลขบัตร/เบอร์ไม่ซ้ำตาม $n */
function post($n, $overrides = [])
{
    return array_merge([
        'link' => '', 'level' => 1, 'title' => 1, 'name' => 'ผู้สมัคร ทดสอบ'.$n,
        'id_card' => thaiId('4100'.sprintf('%08d', $n)), 'birthday' => '2012-05-01',
        'phone' => '07'.sprintf('%08d', $n), 'email' => '', 'nationality' => 'ไทย', 'religion' => 'พุทธ',
        'address' => '1 หมู่ 1', 'district' => 'เขาคราม', 'districtID' => 1010101, 'amphur' => 'เมืองกระบี่', 'amphurID' => 10101,
        'province' => 'กระบี่', 'provinceID' => 101, 'zipcode' => '81000', 'original_school' => 'โรงเรียนเดิม',
        'plan' => [0 => 1], 'academic' => ['GPA' => '3.00'],
        'form_token' => Harness::formToken(60), 'consent' => 1
    ], $overrides);
}
function save($n, $overrides = [], $token = null)
{
    global $photo;
    $options = ['post' => post($n, $overrides), 'files' => [
        'thumbnail' => new \Kotchasan\Http\UploadedFile($photo, filesize($photo), UPLOAD_ERR_OK, 'p.jpg', 'image/jpeg')
    ]];
    if ($token) {
        $options['token'] = $token;
    }
    return Harness::call('\Enroll\Register\Controller', 'save', $options);
}
/** บันทึกผ่านหน้าเจ้าหน้าที่ (api/enroll/enroll) คืนแถวที่บันทึก (กลับไปตาราง ไม่มี link ให้ตาม จึงหาจากเลขบัตร) */
function staffSave($n, $overrides = [])
{
    global $photo, $admin;
    $post = post($n, $overrides);
    $r = Harness::call('\Enroll\Enroll\Controller', 'save', ['post' => $post, 'token' => $admin, 'files' => [
        'thumbnail' => new \Kotchasan\Http\UploadedFile($photo, filesize($photo), UPLOAD_ERR_OK, 'p.jpg', 'image/jpeg')
    ]]);
    if (empty($r['success'])) {
        return null;
    }
    $p = Harness::PREFIX;
    $stmt = Harness::$pdo->prepare("SELECT * FROM `{$p}_enroll` WHERE `id_card` = ? ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([$post['id_card']]);
    return $stmt->fetchObject() ?: null;
}
function savedRow($r)
{
    foreach ($r['data']['actions'] ?? [] as $a) {
        if (($a['type'] ?? '') === 'redirect' && preg_match('/id=([a-z0-9]{32})/', $a['url'], $m)) {
            return \Enroll\Enroll\Model::get($m[1]);
        }
    }
    return null;
}
function message($r)
{
    foreach ($r['data']['actions'] ?? [] as $a) {
        if (($a['type'] ?? '') === 'notification') {
            return $a['message'] ?? '';
        }
    }
    return $r['message'] ?? '';
}

echo "\n=== จำนวนที่รับต่อแผนการเรียน ===\n";
// ระดับ 1 มีแผน 1,2,3 — แผน 1 รับ 1 คน
Harness::$pdo->exec("UPDATE `{$p}_enroll_plans` SET capacity = 1 WHERE id = 1");
\Index\Cache\Controller::clearCache(['query']);
$first = savedRow(save(1));
Harness::check('ใบแรกเลือกแผนที่รับ 1 คนได้', $first !== null);
$r = save(2);
Harness::check('ใบที่สองเลือกแผนเดียวกันเป็นอันดับแรก → แผนนี้รับครบแล้ว', ($r['errors']['plan[0]'] ?? '') === 'แผนการเรียนนี้รับครบแล้ว',
    json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
Harness::check('เลือกแผนที่เต็มเป็นอันดับสำรองได้ (ตรวจแค่อันดับแรก)', \Enroll\Plan\Model::fullPlans(1) === [1 => true]);
$d = Harness::call('\Enroll\Register\Controller', 'get', [])['data'] ?? [];
$plans = array_column($d['options']['plans'] ?? [], null, 'value');
Harness::check('ฟอร์มใหม่: แผนที่เต็มมีป้าย (เต็ม) และเลือกเป็นอันดับแรกไม่ได้', !empty($plans['1']['full']) && strpos($plans['1']['text'], '(เต็ม)') !== false
    && empty($plans['2']['full']), json_encode($plans, JSON_UNESCAPED_UNICODE));
$d = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $first->link]])['data'] ?? [];
$plans = array_column($d['options']['plans'] ?? [], null, 'value');
Harness::check('ใบเดิมที่เลือกแผนนี้ไว้แล้ว ยังเห็นแผนของตัวเองเป็นปกติ', empty($plans['1']['full']), json_encode($plans['1'] ?? null, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => post(1, ['link' => $first->link, 'id_card' => $first->id_card, 'phone' => $first->phone, 'name' => 'แก้ชื่อ'])]);
Harness::check('ใบเดิมแก้ไขได้โดยคงแผนที่เต็มไว้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = save(13, ['form_token' => Harness::formToken(60)], $admin);
Harness::check('หน้าผู้สมัคร: เจ้าหน้าที่ที่เข้าระบบไว้ก็ติดแผนที่เต็ม', ($r['errors']['plan[0]'] ?? '') === 'แผนการเรียนนี้รับครบแล้ว',
    json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
$third = staffSave(3, ['form_token' => '']);
Harness::check('หน้าเจ้าหน้าที่เพิ่มใบสมัครในแผนที่เต็มได้', $third !== null);
$plans = \Enroll\Plan\Model::summary();
$level1 = $plans[0]['plans'] ?? [];
Harness::check('หน้าแรก: แผนที่รับจำกัดแสดง สมัครแล้ว/ที่รับ และสถานะเต็ม', ($level1[0]['seats'] ?? '') === '2/1' && ($level1[0]['full'] ?? null) === true
    && ($level1[1]['seats'] ?? null) === '', json_encode($level1, JSON_UNESCAPED_UNICODE));

// ใบที่ไม่อนุมัติและใบที่ต้องตรวจสอบ (สงสัยว่าเป็นสแปม) ไม่กินที่นั่ง
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET result_status = 3 WHERE id = ".(int) $third->id);
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET review = 'honeypot' WHERE id = ".(int) $first->id);
$level1 = \Enroll\Plan\Model::summary()[0]['plans'] ?? [];
Harness::check('ใบไม่อนุมัติและใบที่ต้องตรวจสอบไม่นับ → แผนกลับมาว่าง', \Enroll\Plan\Model::fullPlans(1) === []
    && ($level1[0]['seats'] ?? '') === '0/1', json_encode($level1[0] ?? null, JSON_UNESCAPED_UNICODE));
$r = save(4);
Harness::check('ผู้สมัครใหม่เลือกแผนนี้ได้อีกครั้ง', savedRow($r) !== null, json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
Harness::check('ใบใหม่นับแล้ว → แผนเต็มอีกครั้ง', \Enroll\Plan\Model::fullPlans(1) === [1 => true]);
$fourth = Harness::$pdo->query("SELECT id FROM `{$p}_enroll` ORDER BY id DESC LIMIT 1")->fetchColumn();
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET result_status = 3 WHERE id = ".(int) $fourth);
$cfg->enroll_capacity_exclude_statuses = [];
Harness::check('ค่าตั้งไม่ยกเว้นสถานะใด → ใบไม่อนุมัติกลับมานับ', \Enroll\Plan\Model::fullPlans(1) === [1 => true]);
$cfg->enroll_capacity_exclude_statuses = [3];
Harness::check('ค่าตั้งยกเว้นไม่อนุมัติ → ไม่นับ', \Enroll\Plan\Model::fullPlans(1) === []);
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET review = '' WHERE id = ".(int) $first->id);
Harness::check('เจ้าหน้าที่ตรวจแล้ว (ล้างป้าย) → นับ', \Enroll\Plan\Model::fullPlans(1) === [1 => true]);
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET result_status = 0 WHERE id IN (".(int) $third->id.', '.(int) $fourth.')');

// ทุกแผนที่เปิดรับเต็ม → ปิดรับสมัครใหม่อัตโนมัติ
Harness::$pdo->exec("UPDATE `{$p}_enroll_plans` SET capacity = 1");
// ใบสมัครจริงหนึ่งใบต่อแผน (การนับนับเฉพาะการเลือกที่มีใบสมัครอยู่จริง)
Harness::$pdo->exec("INSERT INTO `{$p}_enroll` (id, name, level) SELECT 900 + id, CONCAT('เต็ม ', id), level_id FROM `{$p}_enroll_plans` WHERE id <> 1");
Harness::$pdo->exec("INSERT INTO `{$p}_enroll_choices` (enroll_id, no, plan_id) SELECT 900 + id, 0, id FROM `{$p}_enroll_plans` WHERE id <> 1");
\Index\Cache\Controller::clearCache(['query']);
Harness::check('รับครบทุกแผน → allFull', \Enroll\Plan\Model::allFull());
$d = Harness::call('\Enroll\Register\Controller', 'get', [])['data']['data'] ?? [];
Harness::check('ฟอร์มใหม่แสดงข้อความ "รับสมัครครบทุกแผนการเรียนแล้ว"', ($d['closed'] ?? null) === true && ($d['closed_message'] ?? '') === 'รับสมัครครบทุกแผนการเรียนแล้ว',
    json_encode($d, JSON_UNESCAPED_UNICODE));
$d = Harness::call('\Enroll\Home\Controller', 'get', [])['data']['data'] ?? [];
Harness::check('หน้าแรกซ่อนปุ่มสมัคร (is_open = false) พร้อมเหตุผล', ($d['is_open'] ?? null) === false && ($d['closed_message'] ?? '') === 'รับสมัครครบทุกแผนการเรียนแล้ว');
$d = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $first->link]])['data']['data'] ?? [];
Harness::check('ใบเดิมยังเปิดแก้ไขได้ (ปิดเฉพาะใบใหม่)', ($d['closed'] ?? null) === false, json_encode($d['closed_message'] ?? null, JSON_UNESCAPED_UNICODE));

echo "\n=== ระดับชั้นที่รับครบทุกแผน ===\n";
// เต็มเฉพาะระดับชั้นของแผน 1 ระดับอื่นไม่จำกัด
$fullLevel = (int) Harness::$pdo->query("SELECT level_id FROM `{$p}_enroll_plans` WHERE id = 1")->fetchColumn();
Harness::$pdo->exec("UPDATE `{$p}_enroll_plans` SET capacity = 0 WHERE level_id <> $fullLevel");
\Index\Cache\Controller::clearCache(['query']);
Harness::check('ระดับชั้นที่ทุกแผนรับครบ → fullLevels', \Enroll\Plan\Model::fullLevels() === [$fullLevel => true],
    json_encode(\Enroll\Plan\Model::fullLevels()));
Harness::check('ยังมีระดับชั้นอื่นรับอยู่ → ไม่ปิดรับทั้งระบบ', !\Enroll\Plan\Model::allFull());
$r = Harness::call('\Enroll\Register\Controller', 'get', [])['data'] ?? [];
$levels = array_column($r['options']['levels'] ?? [], null, 'value');
Harness::check('ฟอร์มใหม่: ระดับชั้นที่เต็มมีป้าย (เต็ม) และเลือกไม่ได้', !empty($levels[(string) $fullLevel]['full'])
    && !empty($levels[(string) $fullLevel]['disabled']) && strpos($levels[(string) $fullLevel]['text'], '(เต็ม)') !== false,
    json_encode($levels, JSON_UNESCAPED_UNICODE));
Harness::check('ฟอร์มใหม่เริ่มที่ระดับชั้นที่ยังรับ และแสดงคำอธิบาย', ($r['data']['level'] ?? '') !== (string) $fullLevel
    && isset($levels[$r['data']['level'] ?? '']) && ($r['data']['has_full_level'] ?? null) === true,
    json_encode([$r['data']['level'] ?? null, $r['data']['has_full_level'] ?? null]));
$r = Harness::call('\Enroll\Register\Controller', 'get', ['token' => $admin])['data'] ?? [];
$levels = array_column($r['options']['levels'] ?? [], null, 'value');
Harness::check('หน้าผู้สมัคร: เจ้าหน้าที่ที่เข้าระบบไว้ก็เลือกระดับชั้นที่เต็มไม่ได้', !empty($levels[(string) $fullLevel]['disabled']),
    json_encode($levels[(string) $fullLevel] ?? null, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enroll\Controller', 'get', ['token' => $admin])['data'] ?? [];
$levels = array_column($r['options']['levels'] ?? [], null, 'value');
Harness::check('หน้าเจ้าหน้าที่เห็นป้าย (เต็ม) แต่ยังเลือกได้', !empty($levels[(string) $fullLevel]['full']) && empty($levels[(string) $fullLevel]['disabled']),
    json_encode($levels[(string) $fullLevel] ?? null, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $first->link]])['data'] ?? [];
$levels = array_column($r['options']['levels'] ?? [], null, 'value');
Harness::check('ใบเดิมในระดับชั้นนี้เห็นระดับชั้นของตัวเองเป็นปกติ', empty($levels[(string) $fullLevel]['full']) && ($r['data']['level'] ?? '') === (string) $fullLevel,
    json_encode($levels[(string) $fullLevel] ?? null, JSON_UNESCAPED_UNICODE));
$r = save(5);
Harness::check('ผู้สมัครใหม่บันทึกระดับชั้นที่เต็ม → แจ้งที่ระดับชั้น (ไม่แจ้งซ้ำที่แผน)', ($r['errors']['level'] ?? '') === 'ระดับชั้นนี้รับครบแล้ว'
    && !isset($r['errors']['plan[0]']), json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => post(1, ['link' => $first->link, 'id_card' => $first->id_card, 'phone' => $first->phone, 'name' => 'แก้ชื่อ'])]);
Harness::check('ใบเดิมในระดับชั้นที่เต็มยังแก้ไขได้', !empty($r['success']), json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
Harness::check('หน้าเจ้าหน้าที่เพิ่มใบสมัครในระดับชั้นที่เต็มได้', staffSave(6, ['form_token' => '']) !== null);
$summary = array_column(\Enroll\Plan\Model::summary(), 'full');
Harness::check('หน้าแรก: ระดับชั้นที่เต็มมีสถานะเต็ม ระดับอื่นไม่', in_array(true, $summary, true) && in_array(false, $summary, true), json_encode($summary));

Harness::$pdo->exec("UPDATE `{$p}_enroll_plans` SET capacity = 0 WHERE id <> 1");
\Index\Cache\Controller::clearCache(['query']);
Harness::check('มีแผนที่ไม่จำกัดจำนวน → ไม่ปิดรับ', !\Enroll\Plan\Model::allFull());
Harness::$pdo->exec("DELETE FROM `{$p}_enroll_choices` WHERE enroll_id >= 900");
Harness::$pdo->exec("DELETE FROM `{$p}_enroll` WHERE id >= 900");
Harness::$pdo->exec("UPDATE `{$p}_enroll_plans` SET capacity = 0");
\Index\Cache\Controller::clearCache(['query']);

echo "\n=== จำนวนที่รับในหน้าแผนการเรียน ===\n";
$r = Harness::call('\Enroll\Plans\Controller', 'get', ['token' => $admin, 'get' => ['level' => 1]]);
$columns = array_column($r['data']['data']['table']['columns'] ?? [], 'field');
Harness::check('ตารางแผนการเรียนมีคอลัมน์จำนวนที่รับ', in_array('capacity', $columns, true), json_encode($columns));
$ids = array_column($r['data']['data']['table']['data'] ?? [], '__rowKey');
$topics = array_column($r['data']['data']['table']['data'] ?? [], 'topic');
$post = ['level' => 1, 'topic' => [], 'is_active' => [], 'capacity' => []];
foreach ($ids as $i => $id) {
    $post['topic'][$id] = $topics[$i];
    $post['is_active'][$id] = 1;
    $post['capacity'][$id] = $i === 0 ? 40 : -3;
}
$r = Harness::call('\Enroll\Plans\Controller', 'save', ['token' => $admin, 'post' => $post]);
$caps = Harness::$pdo->query("SELECT capacity FROM `{$p}_enroll_plans` WHERE level_id = 1 ORDER BY sort")->fetchAll(PDO::FETCH_COLUMN);
Harness::check('บันทึกจำนวนที่รับได้ ค่าติดลบเป็น 0', !empty($r['success']) && array_map('intval', $caps) === [40, 0, 0], json_encode($caps));
Harness::$pdo->exec("UPDATE `{$p}_enroll_plans` SET capacity = 0");
\Index\Cache\Controller::clearCache(['query']);

echo "\n=== ยินยอมตามประกาศความเป็นส่วนตัว (PDPA) ===\n";
$r = save(10, ['consent' => '']);
Harness::check('ไม่ติ๊กยินยอม → แจ้งที่ช่องยินยอม', ($r['errors']['consent'] ?? '') === 'กรุณายินยอมตามประกาศความเป็นส่วนตัว', json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
$row = savedRow(save(11));
Harness::check('ยินยอมแล้ว → บันทึกเวลาที่ยินยอม', $row && !empty($row->consent_at) && strtotime($row->consent_at) > time() - 60, $row->consent_at ?? '-');
$row = staffSave(12, ['consent' => '', 'form_token' => '']);
Harness::check('เจ้าหน้าที่กรอกแทน (ยินยอมในเอกสาร) ไม่ต้องติ๊ก และไม่บันทึกเวลา', $row && $row->consent_at === null);
$d = Harness::call('\Enroll\Home\Controller', 'privacy', [])['data']['data'] ?? [];
Harness::check('หน้าประกาศความเป็นส่วนตัวมีเนื้อหาเริ่มต้น', strpos($d['content'] ?? '', 'ประกาศความเป็นส่วนตัว') !== false);

echo "\n=== ส่งลิงก์ใบสมัคร ===\n";
// SMTP ปลายทางที่ปิดอยู่: ส่งไม่สำเร็จแน่นอน ทดสอบได้โดยไม่มีอีเมลออกไปจริง
$cfg->email_use_phpMailer = 1;
$cfg->email_Host = '127.0.0.1';
$cfg->email_Port = 1;
$cfg->enroll_notify_email = 1;
$r = save(20, ['email' => 'applicant@example.com']);
$row = savedRow($r);
Harness::check('ส่งอีเมลไม่สำเร็จ → การสมัครยังสำเร็จ', $row !== null, json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ข้อความแจ้งไม่อ้างว่าส่งแล้ว', strpos(message($r), 'ส่งลิงก์') === false, message($r));
$logged = Harness::$pdo->query("SELECT COUNT(*) FROM `{$p}_logs` WHERE src_id = ".(int) $row->id." AND action = 'Notify'")->fetchColumn();
Harness::check('บันทึกความผิดพลาดของการส่งไว้ในประวัติ', (int) $logged === 1, (string) $logged);
$sent = \Enroll\Notify\Model::applicationSaved((object) ['id' => 0, 'link' => str_repeat('a', 32), 'email' => '', 'phone' => '', 'enroll_no' => 'E1', 'title' => 1, 'name' => 'x', 'level' => 1]);
Harness::check('ไม่มีอีเมล → ไม่ส่ง', $sent['email'] === null && $sent['sms'] === null);
$cfg->enroll_notify_sms = 1;
$sent = \Enroll\Notify\Model::applicationSaved($row);
Harness::check('เปิด SMS แต่ยังไม่ได้ตั้งบัญชี SMS → ไม่รายงานว่าส่งแล้ว', $sent['sms'] === 'SMS is not configured', var_export($sent['sms'], true));
$cfg->enroll_notify_sms = 0;
$vars = \Enroll\Notify\Model::variables($row);
Harness::check('ข้อความมีเลขผู้สมัคร ชื่อ และลิงก์ผลการสมัคร', $vars['ENROLL_NO'] === $row->enroll_no && strpos($vars['NAME'], $row->name) !== false
    && strpos($vars['RESULT_URL'], 'enroll-result?id='.$row->link) !== false, json_encode($vars, JSON_UNESCAPED_UNICODE));
$r = save(21, ['email' => 'victim@example.com', 'website' => 'http://spam']);
$logged = Harness::$pdo->query("SELECT COUNT(*) FROM `{$p}_logs` WHERE src_id = ".(int) savedRow($r)->id." AND action = 'Notify'")->fetchColumn();
Harness::check('ใบที่ติดป้ายสแปมไม่ส่งอีเมล (กันใช้ฟอร์มส่งหาผู้อื่น)', (int) $logged === 0);

echo "\n=== ประกาศผล ===\n";
$cfg->enroll_announce = 0;
$d = Harness::call('\Enroll\Announce\Controller', 'get', [])['data']['data'] ?? [];
Harness::check('ยังไม่เปิดประกาศ → ไม่มีรายชื่อ', ($d['enabled'] ?? null) === false && ($d['rows'] ?? null) === [] && ($d['is_announce'] ?? null) === false);
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET result_status = 1, result_plan = 2 WHERE id = ".(int) $first->id);
$cfg->enroll_announce = 1;
$cfg->enroll_announce_statuses = [1];
$r = Harness::call('\Enroll\Announce\Controller', 'get', []);
$d = $r['data']['data'] ?? [];
Harness::check('เปิดประกาศ → แสดงเฉพาะสถานะที่เลือก', ($d['enabled'] ?? null) === true && count($d['rows'] ?? []) === 1, json_encode($d['rows'] ?? null, JSON_UNESCAPED_UNICODE));
$row = $d['rows'][0] ?? [];
Harness::check('ชื่อแสดงแค่อักษรแรกของนามสกุล', ($row['name'] ?? '') === 'เด็กชายแก้ชื่อ' || ($row['name'] ?? '') === \Enroll\Announce\Controller::maskName('เด็กชายแก้ชื่อ'),
    $row['name'] ?? '');
Harness::check('ตัวช่วยปิดนามสกุล', \Enroll\Announce\Controller::maskName('เด็กชายธนกร ใจดี') === 'เด็กชายธนกร ใ.'
    && \Enroll\Announce\Controller::maskName('นายสมชาย  ใจดี มีสุข') === 'นายสมชาย ใ. ม.');
Harness::check('ไม่มีเลขบัตร เบอร์โทร หรือลิงก์ใบสมัคร', !isset($row['id_card'], $row['phone'], $row['link']) && count(array_intersect(array_keys($row), ['id_card', 'phone', 'link'])) === 0,
    json_encode(array_keys($row)));
Harness::check('แสดงแผนที่ได้และผล', ($row['plan'] ?? '') === 'คณิต-วิทย์' && ($row['status'] ?? '') === 'อนุมัติ', json_encode($row, JSON_UNESCAPED_UNICODE));
$d = Harness::call('\Enroll\Announce\Controller', 'get', ['get' => ['level' => 4]])['data']['data'] ?? [];
Harness::check('กรองตามระดับชั้น', ($d['rows'] ?? null) === [], json_encode($d['rows'] ?? null));
$d = Harness::call('\Enroll\Announce\Controller', 'get', ['get' => ['search' => $first->enroll_no]])['data']['data'] ?? [];
Harness::check('ค้นหาด้วยเลขประจำตัวผู้สมัคร', count($d['rows'] ?? []) === 1);
$d = Harness::call('\Enroll\Home\Controller', 'get', [])['data']['data'] ?? [];
Harness::check('เมนูประกาศผลแสดงเมื่อเปิด (is_announce)', ($d['is_announce'] ?? null) === true);
$cfg->enroll_announce = 0;

echo "\n=== บัตรประจำตัวผู้สอบ ===\n";
$cfg->enroll_exam_card = 0;
$r = Harness::call('\Enroll\Examcard\Controller', 'index', ['get' => ['id' => $first->link]]);
Harness::check('ยังไม่เปิด → พิมพ์ไม่ได้ (403)', ($r['code'] ?? 0) === 403, json_encode($r, JSON_UNESCAPED_UNICODE));
$d = Harness::call('\Enroll\Result\Controller', 'get', ['get' => ['id' => $first->link]])['data']['data'] ?? [];
Harness::check('หน้าผลไม่มีปุ่มบัตรสอบ', ($d['card_url'] ?? null) === '');
$cfg->enroll_exam_card = 1;
$cfg->enroll_exam_statuses = [];
$cfg->enroll_exam_date = 'เสาร์ 14 มีนาคม 2570 <08:30>';
$cfg->enroll_exam_place = 'อาคาร 2';
$cfg->enroll_exam_note = "แต่งชุดนักเรียน\nนำบัตรมาด้วย";
$html = Harness::call('\Enroll\Examcard\Controller', 'index', ['get' => ['id' => $first->link]])['__raw'] ?? '';
Harness::check('เปิดแล้ว → ได้หน้าบัตร', strpos($html, 'บัตรประจำตัวผู้สอบ') !== false && strpos($html, $first->enroll_no) !== false, substr(strip_tags($html), 0, 200));
Harness::check('มีวันเวลา/สถานที่สอบ (escape แล้ว) และข้อปฏิบัติขึ้นบรรทัดใหม่',
    strpos($html, '&lt;08:30&gt;') !== false && strpos($html, 'อาคาร 2') !== false && strpos($html, 'แต่งชุดนักเรียน<br') !== false);
Harness::check('แผนการเรียน = แผนที่ได้ (ถ้ามี)', strpos($html, 'คณิต-วิทย์') !== false);
Harness::check('โหลด print.css และ examcard.css', strpos($html, 'templates/enroll/examcard.css') !== false && strpos($html, 'templates/enroll/print.css') !== false);
$d = Harness::call('\Enroll\Result\Controller', 'get', ['get' => ['id' => $first->link]])['data']['data'] ?? [];
Harness::check('หน้าผลมีปุ่มบัตรสอบ', strpos($d['card_url'] ?? '', 'api/enroll/examcard?id='.$first->link) !== false);
$cfg->enroll_exam_statuses = [0];
$r = Harness::call('\Enroll\Examcard\Controller', 'index', ['get' => ['id' => $first->link]]);
Harness::check('สถานะไม่อยู่ในรายการที่พิมพ์ได้ → 403', ($r['code'] ?? 0) === 403);
$r = Harness::call('\Enroll\Examcard\Controller', 'index', ['get' => ['id' => str_repeat('c', 32)]]);
Harness::check('link ไม่ถูกต้อง → 404', ($r['code'] ?? 0) === 404);
$cfg->enroll_exam_card = 0;

echo "\n=== ภาษาที่ผู้เข้าชมเลือก ===\n";
$request = (new \Kotchasan\Http\Request())->withCookieParams(['my_lang' => 'en']);
Harness::check('อ่านภาษาจากคุกกี้ my_lang', \Enroll\Base\Controller::resolveLanguage($request) === 'en');
$request = (new \Kotchasan\Http\Request())->withQueryParams(['lang' => 'th'])->withCookieParams(['my_lang' => 'en']);
Harness::check('พารามิเตอร์ lang มาก่อนคุกกี้', \Enroll\Base\Controller::resolveLanguage($request) === 'th');
\Kotchasan\Language::setName('th');

$code = Harness::summary();
Harness::cleanup();
exit($code);
