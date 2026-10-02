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
        'id_card' => '1234567890123',
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
        'academic' => ['GPA' => '3.75']
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
$r = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $link]]);
Harness::check('สถานะอนุมัติแล้ว → แก้ไขไม่ได้ (403)', ($r['code'] ?? 0) === 403, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $link], 'token' => $admin]);
Harness::check('ผู้ดูแลก็แก้ไม่ได้เช่นกัน (เหมือนระบบเดิม)', ($r['code'] ?? 0) === 403, json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET `result_status` = 0 WHERE `id` = 1");

echo "\n=== ช่วงเวลารับสมัคร ===\n";
$cfg = \Kotchasan\Config::create();
$cfg->enroll_begin = strtotime('-10 day');
$cfg->enroll_end = strtotime('-1 day');
$r = Harness::call('\Enroll\Register\Controller', 'get', []);
Harness::check('ปิดรับสมัครแล้ว → ผู้สมัครเข้าไม่ได้', ($r['code'] ?? 0) === 403, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'get', ['token' => $admin]);
Harness::check('ผู้ดูแลยังเปิดฟอร์มได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Register\Controller', 'save', [
    'token' => $admin,
    'post' => goodPost(['id_card' => '9999999999999', 'name' => 'ผู้ดูแลเพิ่มให้']),
    'files' => ['thumbnail' => uploaded($photo, 'photo.jpg', 'image/jpeg')]
]);
Harness::check('ผู้ดูแลบันทึกนอกช่วงรับสมัครได้', !empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
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

echo "\n=== ตัวเลือกแผนการเรียนตามระดับ (public) ===\n";
$r = Harness::call('\Enroll\Register\Controller', 'plans', ['get' => ['level' => 4]]);
Harness::check('ได้แผนของระดับ 4 จำนวน 3 รายการ', count($r['data'] ?? []) === 3, json_encode($r, JSON_UNESCAPED_UNICODE));

$code = Harness::summary();
Harness::cleanup();
exit($code);
