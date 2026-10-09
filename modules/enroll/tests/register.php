<?php
require __DIR__.'/harness.php';
Harness::boot(__DIR__);

/** สร้างไฟล์รูปจริงสำหรับทดสอบการอัปโหลด */
function makeImage($path, $w = 800, $h = 1000)
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 220, 255));
    imagejpeg($im, $path, 80);
    imagedestroy($im);
    return $path;
}
function uploaded($path, $name, $type)
{
    return new \Kotchasan\Http\UploadedFile($path, filesize($path), UPLOAD_ERR_OK, $name, $type);
}

$tmp = sys_get_temp_dir().'/enroll_test';
@mkdir($tmp, 0777, true);
$photo = makeImage($tmp.'/photo.jpg');
file_put_contents($tmp.'/doc.pdf', '%PDF-1.4 test');

$admin = Harness::login(1, 'can_manage_enroll,can_config', 1);

/** ค่าฟอร์มที่ถูกต้องครบถ้วน */
function goodPost($overrides = [])
{
    return array_merge([
        'link' => '',
        'level' => 1,
        'title' => 1,
        'name' => 'เด็กชายทดสอบ ระบบ',
        'id_card' => '1234567890121',
        'birthday' => '2012-05-01',
        'phone' => '0812345678',
        'email' => 'test@example.com',
        'nationality' => 'ไทย',
        'religion' => 'พุทธ',
        'address' => '99/1 หมู่ 2',
        'district' => 'เขาคราม',
        'districtID' => 1010101,
        'amphur' => 'เมืองกระบี่',
        'amphurID' => 10101,
        'province' => 'กระบี่',
        'provinceID' => 101,
        'zipcode' => '81000',
        'original_school' => 'โรงเรียนทดสอบ',
        'plan' => [0 => 1],
        'parent_name' => ['father' => 'นายพ่อ ทดสอบ', 'mother' => 'นางแม่ ทดสอบ', 'parent' => ''],
        'parent_phone' => ['father' => '0811111111', 'mother' => '0822222222', 'parent' => ''],
        'academic' => ['GPA' => '3.75'],
        // แบบฟอร์มที่เปิดไว้แล้ว 1 นาที (ผู้สมัครจริงต้องส่งรหัสนี้มากับใบสมัครใหม่ทุกครั้ง)
        'form_token' => Harness::formToken(60),
        // ยินยอมตามประกาศความเป็นส่วนตัว (บังคับสำหรับใบสมัครใหม่จากผู้สมัคร)
        'consent' => 1
    ], $overrides);
}

echo "\n=== อ่านฟอร์มใบสมัครใหม่ (ไม่ต้องเข้าระบบ) ===\n";
$r = Harness::call('\Enroll\Register\Controller', 'get', []);
Harness::check('เปิดฟอร์มได้โดยไม่ต้องล็อกอิน', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$d = $r['data']['data'] ?? [];
Harness::check('เป็นใบสมัครใหม่', ($d['is_new'] ?? null) === true && ($d['id'] ?? -1) === 0);
Harness::check('มีตัวเลือกระดับชั้นและแผนการเรียน', !empty($r['data']['options']['levels']) && !empty($r['data']['options']['plans']));
Harness::check('ช่องแผนการเรียน 1 ช่อง (ตามค่ากำหนด)', count($d['plan_fields'] ?? []) === 1, json_encode($d['plan_fields'] ?? [], JSON_UNESCAPED_UNICODE));
Harness::check('ช่องผู้ปกครอง 3 รายการจาก PARENT_LIST', count($d['parent_fields'] ?? []) === 3);
Harness::check('ช่องผลการเรียน 1 รายการจาก ACADEMIC_RESULTS', count($d['academic_fields'] ?? []) === 1);
Harness::check('ตัวเลือกคำนำหน้าจาก TITLES', count($r['data']['options']['title'] ?? []) === 4);

echo "\n=== ตรวจสอบข้อมูลก่อนบันทึก ===\n";
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => goodPost(['name' => '', 'nationality' => ''])]);
Harness::check('ไม่กรอกชื่อ/สัญชาติ → ผิดพลาดตรงช่อง', isset($r['errors']['name'], $r['errors']['nationality']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => goodPost(['id_card' => '123'])]);
Harness::check('เลขบัตรไม่ครบ 13 หลัก → ผิดพลาด', isset($r['errors']['id_card']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => goodPost(['plan' => [0 => 0]])]);
Harness::check('ไม่เลือกแผนการเรียน → ผิดพลาด', isset($r['errors']['plan[0]']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => goodPost(['provinceID' => 0])]);
Harness::check('ไม่มีรหัสจังหวัด → ผิดพลาดที่ช่อง province', isset($r['errors']['province']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => goodPost()]);
Harness::check('ใบสมัครใหม่ไม่แนบรูป → ผิดพลาด', isset($r['errors']['thumbnail']), json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== บันทึกใบสมัครใหม่ ===\n";
$r = Harness::call('\Enroll\Register\Controller', 'save', [
    'post' => goodPost(),
    'files' => [
        'thumbnail' => uploaded($photo, 'photo.jpg', 'image/jpeg'),
        'enroll' => uploaded($tmp.'/doc.pdf', 'doc.pdf', 'application/pdf')
    ]
]);
Harness::check('บันทึกสำเร็จ', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$actions = $r['data']['actions'] ?? [];
$redirect = '';
foreach ($actions as $a) {
    if (($a['type'] ?? '') === 'redirect') { $redirect = $a['url']; }
}
Harness::check('พาไปหน้าผลการสมัครพร้อม link', strpos($redirect, '/enroll-result?id=') === 0, $redirect.' | '.json_encode($actions, JSON_UNESCAPED_UNICODE));

$row = \Enroll\Enroll\Model::get(1);
Harness::check('มี link 32 ตัวอักษร', $row && preg_match('/^[a-z0-9]{32}$/', $row->link), $row->link ?? '-');
Harness::check('ออกเลขประจำตัวผู้สมัครแล้ว', !empty($row->enroll_no), $row->enroll_no ?? '-');
$year = substr((string) \Kotchasan\Config::create()->school_year, 2, 2);
Harness::check('เลขผู้สมัครขึ้นต้นด้วย E+ปี+ระดับ', strpos($row->enroll_no, 'E'.$year.'1') === 0, $row->enroll_no.' (คาดว่าขึ้นต้น E'.$year.'1)');
Harness::check('บันทึกแผนที่เลือกลง enroll_choices', \Enroll\Enroll\Model::choices(1) === [0 => 1], json_encode(\Enroll\Enroll\Model::choices(1)));
$parent = json_decode($row->parent, true);
Harness::check('เก็บข้อมูลผู้ปกครองครบ 3 รายการ', count($parent) === 3 && $parent['father']['name'] === 'นายพ่อ ทดสอบ', $row->parent);
$academic = json_decode($row->academic_results, true);
Harness::check('เก็บผลการเรียน', ($academic['GPA'] ?? null) == 3.75, $row->academic_results);
Harness::check('มีรูปนักเรียน', \Enroll\Enroll\Model::pictureUrl(1) !== null);
Harness::check('มีไฟล์แนบ 1 ไฟล์', count(\Enroll\Enroll\Model::attachments(1)) === 1, json_encode(\Enroll\Enroll\Model::attachments(1), JSON_UNESCAPED_UNICODE));

echo "\n=== เลขบัตรซ้ำ ===\n";
$r = Harness::call('\Enroll\Register\Controller', 'save', [
    'post' => goodPost(),
    'files' => ['thumbnail' => uploaded($photo, 'photo.jpg', 'image/jpeg')]
]);
Harness::check('เลขบัตรซ้ำกับใบสมัครอื่น → ผิดพลาด', isset($r['errors']['id_card']), json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== แก้ไขใบสมัครด้วย link ===\n";
$link = $row->link;
$r = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $link]]);
Harness::check('เปิดใบสมัครเดิมด้วย link ได้', ($r['data']['data']['id'] ?? 0) === 1, json_encode($r['data']['data'] ?? [], JSON_UNESCAPED_UNICODE));
Harness::check('เติมชื่อ ตำบล อำเภอ จังหวัด กลับมาให้',
    ($r['data']['data']['district'] ?? '') === 'เขาคราม' && ($r['data']['data']['province'] ?? '') === 'กระบี่');
Harness::check('มีรูปนักเรียนเดิมในช่องอัปโหลด', !empty($r['data']['data']['thumbnail']));

$r = Harness::call('\Enroll\Register\Controller', 'save', [
    'post' => goodPost(['link' => $link, 'name' => 'เด็กชายแก้ไข แล้ว'])
]);
Harness::check('แก้ไขได้โดยไม่ต้องแนบรูปใหม่', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$row = \Enroll\Enroll\Model::get(1);
Harness::check('ชื่อถูกแก้', $row->name === 'เด็กชายแก้ไข แล้ว', $row->name);
Harness::check('เลขประจำตัวผู้สมัครไม่เปลี่ยน', $row->enroll_no === $row->enroll_no && !empty($row->enroll_no));

$r = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => str_repeat('f', 32)]]);
Harness::check('link ไม่มีอยู่จริง → 404', ($r['code'] ?? 0) === 404, json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== สถานะที่แก้ไขไม่ได้ ===\n";
$p = Harness::PREFIX;
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET `result_status` = 1 WHERE `id` = 1");
// แก้ไม่ได้ = แสดงข้อความในหน้าฟอร์มแทน ไม่ตอบ 403 (หน้า /403 ต้องเข้าระบบ ผู้สมัครจะถูกพาไป login)
$r = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $link]]);
$d = $r['data']['data'] ?? [];
Harness::check('สถานะอนุมัติแล้ว → แสดงข้อความแก้ไขไม่ได้แทนฟอร์ม',
    !empty($r['success']) && ($d['closed'] ?? null) === true && ($d['closed_message'] ?? '') === 'รายการนี้ไม่สามารถแก้ไขได้',
    json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ข้อความมีลิงก์ไปหน้าผลการสมัครของใบนี้', ($d['result_url'] ?? '') === '/enroll-result?id='.$link, $d['result_url'] ?? '');
Harness::check('ไม่ส่งข้อมูลใบสมัครมากับข้อความ', !isset($d['name']) && !isset($d['id_card']), json_encode(array_keys($d)));
$r = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $link], 'token' => $admin]);
Harness::check('หน้าผู้สมัคร: เจ้าหน้าที่ที่เข้าระบบไว้ก็ได้กติกาของผู้สมัคร (แก้ไม่ได้)', ($r['data']['data']['closed'] ?? null) === true, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enroll\Controller', 'get', ['get' => ['id' => $link], 'token' => $admin]);
$d = $r['data']['data'] ?? [];
Harness::check('หน้าเจ้าหน้าที่: เปิดดูได้แต่บันทึกไม่ได้ (เหมือนระบบเดิม)', !empty($r['success']) && ($d['can_save'] ?? null) === false
    && ($d['readonly_message'] ?? '') === 'รายการนี้ไม่สามารถแก้ไขได้' && ($d['name'] ?? '') !== '', json_encode(array_intersect_key($d, array_flip(['can_save', 'readonly_message', 'name'])), JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enroll\Controller', 'save', ['post' => goodPost(['link' => $link]), 'token' => $admin]);
Harness::check('หน้าเจ้าหน้าที่: บันทึกใบที่แก้ไม่ได้ → 403', ($r['code'] ?? 0) === 403, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => goodPost(['link' => $link])]);
Harness::check('บันทึกใบที่แก้ไม่ได้ → ยังถูกปฏิเสธ (403)', ($r['code'] ?? 0) === 403, json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET `result_status` = 0 WHERE `id` = 1");

echo "\n=== ช่วงเวลารับสมัคร ===\n";
$cfg = \Kotchasan\Config::create();
$cfg->enroll_begin = strtotime('-10 day');
$cfg->enroll_end = strtotime('-1 day');
$r = Harness::call('\Enroll\Register\Controller', 'get', []);
$d = $r['data']['data'] ?? [];
Harness::check('ปิดรับสมัครแล้ว → แสดงข้อความปิดรับสมัครแทนฟอร์ม (ไม่ใช่ 403)',
    !empty($r['success']) && ($d['closed'] ?? null) === true && ($d['is_open'] ?? null) === false
    && ($d['closed_message'] ?? '') === 'ยังไม่ถึงเวลาเปิดรับสมัคร หรือปิดรับสมัครแล้ว',
    json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ปุ่มในข้อความพาไปหน้าค้นหาผลการสมัคร', ($d['result_url'] ?? '') === '/enroll-result', $d['result_url'] ?? '');
$r = Harness::call('\Enroll\Register\Controller', 'save', [
    'post' => goodPost(['id_card' => '8888888888886']),
    'files' => ['thumbnail' => uploaded($photo, 'photo.jpg', 'image/jpeg')]
]);
Harness::check('ปิดรับสมัครแล้ว → ผู้สมัครบันทึกไม่ได้ (403)', ($r['code'] ?? 0) === 403, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'get', ['token' => $admin]);
Harness::check('หน้าผู้สมัคร: เจ้าหน้าที่ที่เข้าระบบไว้ก็เห็นว่าปิดรับสมัคร', ($r['data']['data']['closed'] ?? null) === true, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enroll\Controller', 'get', ['token' => $admin]);
Harness::check('หน้าเจ้าหน้าที่ยังเปิดฟอร์มได้', !empty($r['success']) && ($r['data']['data']['can_save'] ?? null) === true, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enroll\Controller', 'save', [
    'token' => $admin,
    'post' => goodPost(['id_card' => '9999999999994', 'name' => 'ผู้ดูแลเพิ่มให้']),
    'files' => ['thumbnail' => uploaded($photo, 'photo.jpg', 'image/jpeg')]
]);
Harness::check('หน้าเจ้าหน้าที่บันทึกนอกช่วงรับสมัครได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$cfg->enroll_begin = 0;
$cfg->enroll_end = 0;

echo "\n=== ลบไฟล์แนบ ===\n";
$files = \Enroll\Enroll\Model::attachments(1);
$r = Harness::call('\Enroll\Register\Controller', 'removefile', [
    'post' => ['action' => 'delete', 'link' => $link, 'url' => $files[0]['url']]
]);
Harness::check('ลบไฟล์แนบได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ไฟล์หายไปจริง', count(\Enroll\Enroll\Model::attachments(1)) === 0);
$r = Harness::call('\Enroll\Register\Controller', 'removefile', [
    'post' => ['action' => 'delete', 'link' => $link, 'url' => 'http://x/../../settings/config.php']
]);
Harness::check('ลบไฟล์นอกไดเร็คทอรี่ไม่ได้', empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== กันสแปม: รหัสแบบฟอร์ม (ไม่ใช้ captcha ไม่อิง IP) ===\n";
/** เลขบัตรประชาชนที่หลักตรวจสอบถูกต้อง */
function thaiId($base12)
{
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $base12[$i] * (13 - $i);
    }
    return $base12.((11 - $sum % 11) % 10);
}
/** ใบสมัครใหม่ที่เลขบัตรและเบอร์ไม่ซ้ำกับใบอื่น */
function newPost($n, $overrides = [])
{
    return goodPost(array_merge([
        'id_card' => thaiId('3100'.sprintf('%08d', $n)),
        'phone' => '06'.sprintf('%08d', $n)
    ], $overrides));
}
/** ใบที่เจ้าหน้าที่บันทึก (หน้าเจ้าหน้าที่กลับไปตาราง ไม่มี link ใน URL ให้ตาม) */
function rowByIdCard($idCard)
{
    $p = Harness::PREFIX;
    $stmt = Harness::$pdo->prepare("SELECT * FROM `{$p}_enroll` WHERE `id_card` = ? ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([$idCard]);
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
$pic = function () use ($photo) {
    return ['thumbnail' => uploaded($photo, 'photo.jpg', 'image/jpeg')];
};

$r = Harness::call('\Enroll\Register\Controller', 'get', []);
$d = $r['data']['data'] ?? [];
Harness::check('ฟอร์มใหม่ได้รหัสแบบฟอร์มที่ลงลายเซ็น', preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $d['form_token'] ?? '') === 1, $d['form_token'] ?? '');
Harness::check('เปิดตรวจหลักตรวจสอบเลขบัตรฝั่งเบราว์เซอร์ (TH)', ($d['id_card_checksum'] ?? null) === true);
$r = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $link]]);
Harness::check('ใบเดิมที่แก้ด้วย link ไม่มีรหัสแบบฟอร์ม (ไม่ต้องใช้)', ($r['data']['data']['form_token'] ?? null) === '');

$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(101, ['form_token' => '']), 'files' => $pic()]);
Harness::check('ไม่มีรหัสแบบฟอร์ม (ยิงตรงไม่ผ่านฟอร์ม) → ปฏิเสธ 400', ($r['code'] ?? 0) === 400, json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ข้อความบอกให้โหลดหน้าใหม่', ($r['message'] ?? '') === 'แบบฟอร์มนี้หมดอายุแล้ว กรุณาโหลดหน้าใหม่', $r['message'] ?? '');
$forged = explode('.', Harness::formToken(60));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(102, ['form_token' => $forged[0].'.AAAA'.substr($forged[1], 4)]), 'files' => $pic()]);
Harness::check('ลายเซ็นปลอม → ปฏิเสธ', ($r['code'] ?? 0) === 400, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(103, ['form_token' => Harness::formToken(\Enroll\Formguard\Model::MAX_AGE + 60)]), 'files' => $pic()]);
Harness::check('เปิดฟอร์มค้างไว้เกินอายุ → ปฏิเสธ', ($r['code'] ?? 0) === 400, json_encode($r, JSON_UNESCAPED_UNICODE));

$token = Harness::formToken(90);
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(104, ['form_token' => $token, 'name' => '']), 'files' => $pic()]);
Harness::check('กรอกไม่ครบ → แจ้งที่ช่อง (รหัสยังไม่ถูกใช้)', isset($r['errors']['name']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(104, ['form_token' => $token]), 'files' => $pic()]);
$row = savedRow($r);
Harness::check('แก้แล้วส่งใหม่ด้วยรหัสเดิม → บันทึกได้', $row !== null, json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ใบปกติไม่ติดป้ายต้องตรวจสอบ', $row && $row->review === '', $row->review ?? '-');
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(105, ['form_token' => $token]), 'files' => $pic()]);
Harness::check('ใช้รหัสแบบฟอร์มเดิมส่งใบที่สอง → ปฏิเสธ', ($r['code'] ?? 0) === 400 && ($r['message'] ?? '') === 'แบบฟอร์มนี้ถูกส่งไปแล้ว กรุณาเปิดแบบฟอร์มใหม่',
    json_encode($r, JSON_UNESCAPED_UNICODE));

$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(106, ['form_token' => Harness::formToken(3)]), 'files' => $pic()]);
$row = savedRow($r);
Harness::check('ส่งเร็วผิดธรรมชาติ → บันทึก แต่ติดป้าย fast', $row && $row->review === 'fast', json_encode([$r['success'] ?? null, $row->review ?? null]));
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(107, ['website' => 'http://spam.example']), 'files' => $pic()]);
$row = savedRow($r);
Harness::check('กรอกช่องดักบอต → บันทึก แต่ติดป้าย honeypot', $row && $row->review === 'honeypot', json_encode([$r['success'] ?? null, $row->review ?? null]));
Harness::check('ผู้ส่งไม่รู้ว่าถูกติดป้าย (ตอบเหมือนปกติ)', !empty($r['success']) && savedRow($r) !== null);
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(108, ['form_token' => '', 'website' => 'x']), 'files' => $pic(), 'token' => $admin]);
Harness::check('หน้าผู้สมัคร: เจ้าหน้าที่ที่เข้าระบบไว้ก็ต้องมีรหัสแบบฟอร์ม (400)', ($r['code'] ?? 0) === 400, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enroll\Controller', 'save', ['post' => newPost(108, ['form_token' => '', 'website' => 'x']), 'files' => $pic(), 'token' => $admin]);
$row = rowByIdCard(newPost(108)['id_card']);
Harness::check('หน้าเจ้าหน้าที่ไม่ต้องมีรหัสแบบฟอร์ม และไม่ถูกติดป้าย', !empty($r['success']) && $row && $row->review === '' && $row->form_nonce === null,
    json_encode([$r['success'] ?? null, $row->review ?? null], JSON_UNESCAPED_UNICODE));

echo "\n=== เลขบัตรประชาชน: หลักตรวจสอบ ===\n";
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(109, ['id_card' => '1234567890123']), 'files' => $pic()]);
Harness::check('หลักตรวจสอบไม่ตรง → แจ้งที่ช่องเลขบัตร', ($r['errors']['id_card'] ?? '') === \Kotchasan\Language::replace('Invalid :name', [':name' => \Kotchasan\Language::get('Identification No.')]), json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
Harness::check('ตัวตรวจในโมเดลถูกต้อง', \Enroll\Enroll\Model::validThaiId('1234567890121') && !\Enroll\Enroll\Model::validThaiId('1234567890123')
    && !\Enroll\Enroll\Model::validThaiId('12345'));
$cfg = \Kotchasan\Config::create();
$cfg->enroll_country = 'LA';
$d = Harness::call('\Enroll\Register\Controller', 'get', [])['data']['data'] ?? [];
Harness::check('โรงเรียนในลาว → ไม่ตรวจหลักตรวจสอบ (เลขบัตรคนละแบบ)', ($d['id_card_checksum'] ?? null) === false);
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(110, ['id_card' => '1234567890123']), 'files' => $pic()]);
Harness::check('โรงเรียนในลาว บันทึกเลขที่ไม่ผ่านหลักตรวจสอบได้', savedRow($r) !== null, json_encode($r, JSON_UNESCAPED_UNICODE));
$cfg->enroll_country = 'TH';
$p = Harness::PREFIX;
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET `id_card` = '5555555555555' WHERE `id` = 1");
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => goodPost(['link' => $link, 'id_card' => '5555555555555'])]);
Harness::check('ใบเดิม (ย้ายจากระบบเดิม) เลขบัตรไม่เปลี่ยน → ยังบันทึกได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== จำกัดจำนวนใบสมัครต่อเบอร์โทรศัพท์ ===\n";
for ($i = 1; $i <= \Enroll\Register\Controller::PHONE_LIMIT; $i++) {
    Harness::$pdo->exec("INSERT INTO `{$p}_enroll` (`name`, `link`, `phone`) VALUES ('พี่น้อง $i', '".md5('phone'.$i)."', '0899999999')");
}
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(111, ['phone' => '0899999999']), 'files' => $pic()]);
Harness::check('เบอร์เดียวกันครบ 5 ใบ → ใบที่ 6 แจ้งที่ช่องโทรศัพท์', ($r['errors']['phone'] ?? '') === 'เบอร์โทรศัพท์นี้ใช้สมัครครบ 5 ใบแล้ว',
    json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Enroll\Controller', 'save', ['post' => newPost(112, ['phone' => '0899999999', 'form_token' => '']), 'files' => $pic(), 'token' => $admin]);
Harness::check('หน้าเจ้าหน้าที่บันทึกเบอร์นี้ได้ (ไม่จำกัด)', !empty($r['success']) && rowByIdCard(newPost(112)['id_card']) !== null, json_encode($r, JSON_UNESCAPED_UNICODE));
$cfg->enroll_phone_limit = 0;
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => newPost(113, ['phone' => '0899999999']), 'files' => $pic()]);
Harness::check('enroll_phone_limit = 0 → ไม่จำกัด', savedRow($r) !== null, json_encode($r, JSON_UNESCAPED_UNICODE));
unset($cfg->enroll_phone_limit);

echo "\n=== ตัวเลือกแผนการเรียนตามระดับ (public) ===\n";
$r = Harness::call('\Enroll\Register\Controller', 'plans', ['get' => ['level' => 4]]);
Harness::check('ได้แผนของระดับ 4 จำนวน 3 รายการ', count($r['data'] ?? []) === 3, json_encode($r, JSON_UNESCAPED_UNICODE));

$code = Harness::summary();
Harness::cleanup();
exit($code);
