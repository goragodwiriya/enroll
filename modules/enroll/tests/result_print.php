<?php
require __DIR__.'/harness.php';
Harness::boot(__DIR__);

function makeImage($path)
{
    $im = imagecreatetruecolor(600, 800);
    imagefill($im, 0, 0, imagecolorallocate($im, 220, 235, 255));
    imagejpeg($im, $path, 80);
    imagedestroy($im);
    return $path;
}
$tmp = sys_get_temp_dir().'/enroll_test4';
@mkdir($tmp, 0777, true);
$photo = makeImage($tmp.'/photo.jpg');

// สร้างใบสมัครหนึ่งใบผ่าน API จริง
$post = [
    'link' => '', 'level' => 4, 'title' => 3, 'name' => 'นายสมชาย ใจดี',
    'id_card' => '1234567890121', 'birthday' => '2009-03-11', 'phone' => '0812345678',
    'email' => 'somchai@example.com', 'nationality' => 'ไทย', 'religion' => 'พุทธ',
    'address' => '1/1 หมู่ 3', 'district' => 'เขาคราม', 'districtID' => 1010101,
    'amphur' => 'เมืองกระบี่', 'amphurID' => 10101, 'province' => 'กระบี่', 'provinceID' => 101,
    'zipcode' => '81000', 'original_school' => 'โรงเรียนเดิม',
    'plan' => [0 => 5],
    'parent_name' => ['father' => 'นายพ่อ ใจดี', 'mother' => 'นางแม่ ใจดี', 'parent' => ''],
    'parent_phone' => ['father' => '0811111111', 'mother' => '', 'parent' => ''],
    'academic' => ['GPA' => '3.20'],
    'form_token' => Harness::formToken(60),
    'consent' => 1
];
$r = Harness::call('\Enroll\Register\Controller', 'save', [
    'post' => $post,
    'files' => ['thumbnail' => new \Kotchasan\Http\UploadedFile($photo, filesize($photo), UPLOAD_ERR_OK, 'photo.jpg', 'image/jpeg')]
]);
if (empty($r['success'])) {
    exit("เตรียมข้อมูลไม่สำเร็จ: ".json_encode($r, JSON_UNESCAPED_UNICODE)."\n");
}
$row = \Enroll\Enroll\Model::get(1);
$link = $row->link;

echo "\n=== บันทึกอีเมล ===\n";
Harness::check('อีเมลถูกบันทึก (ไม่หายเป็นค่าว่าง)', $row->email === 'somchai@example.com', var_export($row->email, true));

echo "\n=== หน้าผลการสมัคร: ยังไม่ระบุตัวตน ===\n";
$r = Harness::call('\Enroll\Result\Controller', 'get', []);
Harness::check('ไม่ส่ง link → ให้ค้นหาก่อน', ($r['data']['data']['found'] ?? null) === false, json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Result\Controller', 'get', ['get' => ['id' => str_repeat('a', 32)]]);
Harness::check('link มั่ว → ให้ค้นหาก่อน (ไม่บอกว่ามีหรือไม่มี)', ($r['data']['data']['found'] ?? null) === false);

echo "\n=== ค้นหาด้วยเลขบัตร + วันเกิด ===\n";
$r = Harness::call('\Enroll\Result\Controller', 'lookup', ['post' => ['id_card' => '', 'birthday' => '']]);
Harness::check('ไม่กรอกข้อมูล → ผิดพลาดตรงช่อง', isset($r['errors']['id_card'], $r['errors']['birthday']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Result\Controller', 'lookup', ['post' => ['id_card' => '1234567890121', 'birthday' => '2000-01-01']]);
Harness::check('วันเกิดไม่ตรง → แจ้งข้อมูลไม่ถูกต้อง', empty($r['success']), json_encode($r, JSON_UNESCAPED_UNICODE));
$r = Harness::call('\Enroll\Result\Controller', 'lookup', ['post' => ['id_card' => '1234567890121', 'birthday' => '2009-03-11']]);
$redirect = '';
foreach ($r['data']['actions'] ?? [] as $a) {
    if (($a['type'] ?? '') === 'redirect') { $redirect = $a['url']; }
}
Harness::check('ข้อมูลถูกต้อง → พาไปหน้าผลพร้อม link', $redirect === '/enroll-result?id='.$link, $redirect);

echo "\n=== ผลการสมัครเมื่อมี link ===\n";
$r = Harness::call('\Enroll\Result\Controller', 'get', ['get' => ['id' => $link]]);
$d = $r['data']['data'] ?? [];
Harness::check('พบใบสมัคร', ($d['found'] ?? null) === true, json_encode($r, JSON_UNESCAPED_UNICODE));
Harness::check('ชื่อ/ระดับชั้น/แผนการเรียนถูกต้อง',
    $d['name'] === 'นายสมชาย ใจดี' && $d['level'] === 'มัธยมศึกษาปีที่ 4' && $d['plan'] === 'ภาษาไทย-สังคม-อังกฤษ',
    json_encode([$d['name'] ?? '', $d['level'] ?? '', $d['plan'] ?? ''], JSON_UNESCAPED_UNICODE));
Harness::check('มีบาร์โค้ดเป็นรูป base64', strpos($d['barcode'] ?? '', 'data:image/png;base64,') === 0);
Harness::check('สถานะเริ่มต้น = รอดำเนินการ', ($d['result_status_text'] ?? '') === 'รอดำเนินการ', $d['result_status_text'] ?? '');
Harness::check('แก้ไขได้ (สถานะ 0 อยู่ใน enroll_editable)', ($d['can_edit'] ?? null) === true);
Harness::check('ลิงก์แก้ไขและลิงก์พิมพ์ชี้ถูก',
    ($d['edit_url'] ?? '') === '/enroll-edit?id='.$link && strpos($d['print_url'] ?? '', 'api/enroll/printform?id='.$link) !== false,
    json_encode([$d['edit_url'] ?? '', $d['print_url'] ?? '']));

$p = Harness::PREFIX;
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET `result_status` = 1, `result_plan` = 6 WHERE `id` = 1");
$r = Harness::call('\Enroll\Result\Controller', 'get', ['get' => ['id' => $link]]);
$d = $r['data']['data'] ?? [];
Harness::check('อนุมัติแล้ว → แสดงเฉพาะแผนที่ได้', ($d['plan'] ?? '') === 'ภาษาไทย-สังคม', $d['plan'] ?? '');
Harness::check('อนุมัติแล้ว → แก้ไขไม่ได้', ($d['can_edit'] ?? null) === false);
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET `result_status` = 0, `result_plan` = 0 WHERE `id` = 1");

echo "\n=== หน้าพิมพ์ใบสมัคร ===\n";
$r = Harness::call('\Enroll\Printform\Controller', 'index', ['get' => ['id' => $link]]);
$html = $r['__raw'] ?? '';
Harness::check('คืนค่าเป็นหน้า HTML', strpos($html, '<!doctype html>') !== false || strpos($html, '<!DOCTYPE html>') !== false,
    substr($html, 0, 120));
Harness::check('มีชื่อผู้สมัครในเอกสาร', strpos($html, 'นายสมชาย ใจดี') !== false);
Harness::check('มีระดับชั้นและแผนการเรียน', strpos($html, 'มัธยมศึกษาปีที่ 4') !== false && strpos($html, 'ภาษาไทย-สังคม-อังกฤษ') !== false);
Harness::check('แปลง {LNG_} เป็นภาษาไทยแล้ว', strpos($html, 'ใบแจ้งความจำนงเข้าศึกษาต่อ') !== false && strpos($html, '{LNG_') === false,
    strpos($html, '{LNG_') !== false ? 'ยังเหลือ {LNG_ ใน HTML' : '');
Harness::check('มีบาร์โค้ดฝังในเอกสาร', strpos($html, 'data:image/png;base64,') !== false);
Harness::check('โหลด print.css ของโมดูล', strpos($html, 'templates/enroll/print.css') !== false);
Harness::check('เลขบัตรแสดงทีละหลัก', substr_count($html, '<i>') >= 13, substr_count($html, '<i>').' ช่อง');
Harness::check('ข้อมูลผู้ปกครองอยู่ในเอกสาร', strpos($html, 'นายพ่อ ใจดี') !== false);
Harness::check('ผลการเรียนอยู่ในเอกสาร', strpos($html, '3.2') !== false, '');
preg_match('/<span class="blocknumber">(.*?)<\/span>/s', $html, $m);
Harness::check('เลขบัตร 13 หลักอยู่ในช่องทีละหลัก', substr_count($m[1] ?? '', '<i>') === 13, $m[1] ?? 'ไม่พบ .blocknumber');
Harness::check('มีปุ่มพิมพ์บนจอ (ซ่อนตอนพิมพ์)', strpos($html, 'class="print-bar noprint"') !== false && strpos($html, 'window.print()') !== false);
Harness::check('กำหนดขนาดกระดาษ A4', strpos($html, '@page { size: 210mm 297mm') !== false);
Harness::check('ไม่ใส่รูป no-image ลงเอกสาร', strpos($html, 'no-image') === false);

// โลโก้ที่บันทึกด้วยนามสกุลอื่นนอกจาก stored_img_type ต้องหาเจอ (เว็บจริงมีแค่ logo.webp)
$logoDir = ROOT_PATH.DATA_FOLDER.'images/';
$logoFile = $logoDir.'logo.webp';
$hadLogo = is_file($logoFile);
if (!$hadLogo) {
    @mkdir($logoDir, 0777, true);
    copy($photo, $logoFile);
}
$r = Harness::call('\Enroll\Printform\Controller', 'index', ['get' => ['id' => $link]]);
$html = $r['__raw'] ?? '';
Harness::check('พบโลโก้ logo.webp แสดงในหัวเอกสาร', strpos($html, 'class="logo" src="'.WEB_URL.DATA_FOLDER.'images/logo.webp') !== false);
if (!$hadLogo) {
    unlink($logoFile);
}

echo "\n=== ข้อมูลส่วนหัวของฟอร์มลงทะเบียน ===\n";
$r = Harness::call('\Enroll\Register\Controller', 'get', ['get' => ['id' => $link]]);
$d = $r['data']['data'] ?? [];
Harness::check('มีชื่อโรงเรียนและปีการศึกษา', isset($d['school_name'], $d['school_year']), json_encode(array_keys($d)));
Harness::check('ใบสมัครเดิมมีเลขที่ ลิงก์พิมพ์ และลิงก์ดูผล',
    ($d['enroll_no'] ?? '') === $row->enroll_no
    && strpos($d['print_url'] ?? '', 'api/enroll/printform?id='.$link) !== false
    && ($d['result_url'] ?? '') === '/enroll-result?id='.$link,
    json_encode([$d['enroll_no'] ?? '', $d['print_url'] ?? '', $d['result_url'] ?? '']));
Harness::check('ส่งชนิดรูปที่รับมาแทน :type', ($d['img_types'] ?? '') !== '' && strpos($d['img_types'], 'jpg') !== false, $d['img_types'] ?? '');
$r = Harness::call('\Enroll\Register\Controller', 'get', []);
$d = $r['data']['data'] ?? [];
Harness::check('ใบสมัครใหม่ไม่มีลิงก์พิมพ์', ($d['print_url'] ?? null) === '' && ($d['enroll_no'] ?? null) === '');

$r = Harness::call('\Enroll\Printform\Controller', 'index', ['get' => ['id' => str_repeat('b', 32)]]);
Harness::check('link ไม่ถูกต้อง → 404', ($r['code'] ?? 0) === 404, json_encode($r, JSON_UNESCAPED_UNICODE));

echo "\n=== XSS: ข้อมูลที่ผู้สมัครกรอกต้องถูก escape ===\n";
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET `name` = '<script>alert(1)</script>' WHERE `id` = 1");
$r = Harness::call('\Enroll\Printform\Controller', 'index', ['get' => ['id' => $link]]);
$html = $r['__raw'] ?? '';
Harness::check('ชื่อที่มีแท็กถูก escape ในหน้าพิมพ์',
    strpos($html, '<script>alert(1)</script>') === false && strpos($html, '&lt;script&gt;') !== false);

$code = Harness::summary();
Harness::cleanup();
exit($code);
