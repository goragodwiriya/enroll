<?php
/**
 * ใบสมัครของเจ้าหน้าที่ (หน้า /enroll, api/enroll/enroll) แยกจากหน้าที่ผู้สมัครกรอก (/enroll-edit, api/enroll/register)
 *
 * ใช้ตรวจ/บันทึกชุดเดียวกับผู้สมัคร แต่ต้องเข้าระบบและมีสิทธิ์ can_manage_enroll
 * ใช้กติกาของเจ้าหน้าที่ (ไม่ติดช่วงรับสมัคร จำนวนที่รับ ตัวกันสแปม ยินยอม) บันทึกเสร็จกลับไปตารางผู้สมัคร
 */
require __DIR__.'/harness.php';
Harness::boot(__DIR__);

$admin = Harness::login(1, 'can_manage_enroll,can_config', 1);
$manager = Harness::login(2, 'can_manage_enroll', 0);
$member = Harness::login(5, '', 0);
$p = Harness::PREFIX;
$cfg = \Kotchasan\Config::create();

$tmp = sys_get_temp_dir().'/enroll_test_form';
@mkdir($tmp, 0777, true);
$photo = $tmp.'/photo.jpg';
$im = imagecreatetruecolor(300, 400);
imagefill($im, 0, 0, imagecolorallocate($im, 220, 230, 250));
imagejpeg($im, $photo, 80);
file_put_contents($tmp.'/doc.pdf', '%PDF-1.4 test');

function thaiId($base12)
{
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $base12[$i] * (13 - $i);
    }
    return $base12.((11 - $sum % 11) % 10);
}
function post($n, $overrides = [])
{
    return array_merge([
        'link' => '', 'level' => 1, 'title' => 1, 'name' => 'ใบเจ้าหน้าที่ '.$n,
        'id_card' => thaiId('4200'.sprintf('%08d', $n)), 'birthday' => '2012-05-01',
        'phone' => '07'.sprintf('%08d', $n), 'email' => '', 'nationality' => 'ไทย', 'religion' => 'พุทธ',
        'address' => '1 หมู่ 1', 'district' => 'เขาคราม', 'districtID' => 1010101, 'amphur' => 'เมืองกระบี่', 'amphurID' => 10101,
        'province' => 'กระบี่', 'provinceID' => 101, 'zipcode' => '81000', 'original_school' => 'โรงเรียนเดิม',
        'plan' => [0 => 1], 'academic' => ['GPA' => '3.00']
    ], $overrides);
}
function files($withDoc = false)
{
    global $photo, $tmp;
    $files = ['thumbnail' => new \Kotchasan\Http\UploadedFile($photo, filesize($photo), UPLOAD_ERR_OK, 'p.jpg', 'image/jpeg')];
    if ($withDoc) {
        $files['enroll'] = new \Kotchasan\Http\UploadedFile($tmp.'/doc.pdf', filesize($tmp.'/doc.pdf'), UPLOAD_ERR_OK, 'doc.pdf', 'application/pdf');
    }
    return $files;
}
/** ปลายทางที่ API สั่งให้หน้าเว็บไปหลังบันทึก */
function redirectUrl($r)
{
    foreach ($r['data']['actions'] ?? [] as $a) {
        if (($a['type'] ?? '') === 'redirect') {
            return $a['url'] ?? '';
        }
    }
    return '';
}
function rowByIdCard($idCard)
{
    $p = Harness::PREFIX;
    $stmt = Harness::$pdo->prepare("SELECT * FROM `{$p}_enroll` WHERE `id_card` = ? ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([$idCard]);
    return $stmt->fetchObject() ?: null;
}

echo "\n=== สิทธิ์: ต้องเข้าระบบและจัดการการรับสมัครได้ ===\n";
foreach (['get', 'plans'] as $method) {
    $r = Harness::call('\Enroll\Enroll\Controller', $method, ['get' => ['level' => 1]]);
    Harness::check($method.': ไม่เข้าระบบ → 401', ($r['code'] ?? 0) === 401, json_encode($r['code'] ?? $r));
    $r = Harness::call('\Enroll\Enroll\Controller', $method, ['get' => ['level' => 1], 'token' => $member]);
    Harness::check($method.': สมาชิกที่ไม่มีสิทธิ์ → 403', ($r['code'] ?? 0) === 403, json_encode($r['code'] ?? $r));
}
$r = Harness::call('\Enroll\Enroll\Controller', 'save', ['post' => post(1), 'files' => files()]);
Harness::check('save: ไม่เข้าระบบ → 401 และไม่บันทึก', ($r['code'] ?? 0) === 401 && rowByIdCard(post(1)['id_card']) === null);
$r = Harness::call('\Enroll\Enroll\Controller', 'save', ['post' => post(1), 'files' => files(), 'token' => $member]);
Harness::check('save: สมาชิกที่ไม่มีสิทธิ์ → 403 และไม่บันทึก', ($r['code'] ?? 0) === 403 && rowByIdCard(post(1)['id_card']) === null);
$r = Harness::call('\Enroll\Enroll\Controller', 'removefile', ['post' => ['action' => 'delete', 'link' => str_repeat('a', 32), 'url' => 'x.pdf'], 'token' => $member]);
Harness::check('removefile: สมาชิกที่ไม่มีสิทธิ์ → 403', ($r['code'] ?? 0) === 403);

echo "\n=== เพิ่มใบสมัครใหม่โดยเจ้าหน้าที่ ===\n";
$r = Harness::call('\Enroll\Enroll\Controller', 'get', ['token' => $manager]);
$d = $r['data']['data'] ?? [];
Harness::check('เจ้าหน้าที่ (ไม่ใช่ผู้ดูแลระบบ) เปิดฟอร์มใหม่ได้', !empty($r['success']) && ($d['is_new'] ?? null) === true && ($d['can_save'] ?? null) === true,
    json_encode($r['message'] ?? $r, JSON_UNESCAPED_UNICODE));
Harness::check('ใช้กติกาเจ้าหน้าที่: ไม่มีรหัสแบบฟอร์มกันสแปม', ($d['can_manage'] ?? null) === true && ($d['form_token'] ?? 'x') === '');
Harness::check('ปุ่มกลับไปตารางของระดับชั้นนั้น', ($d['back_url'] ?? '') === '/enrolls?level='.($d['level'] ?? ''), $d['back_url'] ?? '');
$r = Harness::call('\Enroll\Enroll\Controller', 'get', ['token' => $manager, 'get' => ['level' => 4]]);
Harness::check('?level= เลือกระดับชั้นไว้ให้ (เพิ่มจากตารางของระดับนั้น)', ($r['data']['data']['level'] ?? '') === '4'
    && ($r['data']['data']['back_url'] ?? '') === '/enrolls?level=4');
// ไม่มีรหัสแบบฟอร์ม ไม่ติ๊กยินยอม กรอกช่องดักบอต = ผู้สมัครทำแบบนี้จะถูกปฏิเสธ/ติดป้าย
$r = Harness::call('\Enroll\Enroll\Controller', 'save', ['post' => post(2, ['website' => 'x']), 'files' => files(), 'token' => $manager]);
$row = rowByIdCard(post(2)['id_card']);
Harness::check('บันทึกใบใหม่ได้โดยไม่ต้องมีรหัสแบบฟอร์ม/ยินยอม', !empty($r['success']) && $row !== null, json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
Harness::check('บันทึกเสร็จกลับไปตารางผู้สมัครของระดับชั้นนั้น', redirectUrl($r) === '/enrolls?level=1', redirectUrl($r));
Harness::check('ใบของเจ้าหน้าที่ไม่ถูกติดป้ายกันสแปม ไม่มีเวลายินยอม', $row && $row->review === '' && $row->form_nonce === null && $row->consent_at === null);
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => post(3, ['website' => 'x']), 'files' => files(), 'token' => $manager]);
Harness::check('หน้าผู้สมัคร: ส่งแบบเดียวกันด้วย token เจ้าหน้าที่ → ยังถูกปฏิเสธ (ไม่มีรหัสแบบฟอร์ม)', ($r['code'] ?? 0) === 400
    && rowByIdCard(post(3)['id_card']) === null, json_encode($r['code'] ?? $r));

echo "\n=== ดู/แก้ไขใบเดิม ===\n";
$r = Harness::call('\Enroll\Enroll\Controller', 'save', ['post' => post(4), 'files' => files(true), 'token' => $admin]);
$row = rowByIdCard(post(4)['id_card']);
Harness::check('เพิ่มใบพร้อมไฟล์แนบ', $row !== null && count(\Enroll\Enroll\Model::attachments($row->id)) === 1, json_encode($r['errors'] ?? null));
$r = Harness::call('\Enroll\Enroll\Controller', 'get', ['get' => ['id' => $row->link], 'token' => $admin]);
$d = $r['data']['data'] ?? [];
Harness::check('เปิดใบเดิม: ข้อมูลครบและบันทึกได้', ($d['is_new'] ?? null) === false && ($d['name'] ?? '') === 'ใบเจ้าหน้าที่ 4' && ($d['can_save'] ?? null) === true);
Harness::check('ส่วนหัว: เลขผู้สมัคร วันที่ สถานะ', ($d['enroll_no'] ?? '') === $row->enroll_no && ($d['created_at'] ?? '') !== ''
    && ($d['result_status_text'] ?? '') === 'รอดำเนินการ' && ($d['status_class'] ?? '') === 'term0', json_encode(array_intersect_key($d, array_flip(['enroll_no', 'created_at', 'result_status_text', 'status_class'])), JSON_UNESCAPED_UNICODE));
Harness::check('ลิงก์พิมพ์ใบสมัคร / ไม่มีบัตรสอบเมื่อปิดใช้', strpos($d['print_url'] ?? '', 'printform?id='.$row->link) !== false && ($d['card_url'] ?? 'x') === '');
$cfg->enroll_exam_card = 1;
$cfg->enroll_exam_statuses = [];
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET review = 'honeypot' WHERE id = ".(int) $row->id);
$d = Harness::call('\Enroll\Enroll\Controller', 'get', ['get' => ['id' => $row->link], 'token' => $admin])['data']['data'] ?? [];
Harness::check('เปิดบัตรสอบแล้ว → มีลิงก์พิมพ์บัตรประจำตัวผู้สอบ', strpos($d['card_url'] ?? '', 'examcard?id='.$row->link) !== false, $d['card_url'] ?? '');
Harness::check('ใบที่ติดป้าย → แสดงเหตุผลที่ต้องตรวจสอบ', ($d['review_text'] ?? '') === 'มีการกรอกช่องที่ซ่อนไว้', $d['review_text'] ?? '');
$cfg->enroll_exam_card = 0;
Harness::$pdo->exec("UPDATE `{$p}_enroll` SET review = '' WHERE id = ".(int) $row->id);
$r = Harness::call('\Enroll\Enroll\Controller', 'save', ['post' => post(4, ['link' => $row->link, 'name' => 'แก้โดยเจ้าหน้าที่']), 'token' => $admin]);
Harness::check('แก้ไขใบเดิม → กลับไปตารางผู้สมัคร (ไม่ใช่หน้าผลของผู้สมัคร)', !empty($r['success']) && redirectUrl($r) === '/enrolls?level=1', redirectUrl($r));
Harness::check('บันทึกการแก้ไขจริง', rowByIdCard(post(4)['id_card'])->name === 'แก้โดยเจ้าหน้าที่');
$r = Harness::call('\Enroll\Register\Controller', 'save', ['post' => post(4, ['link' => $row->link, 'name' => 'ผู้สมัครแก้เอง'])]);
Harness::check('ผู้สมัครแก้ใบเดียวกันจากหน้าของตัวเอง → ไปหน้าผลการสมัคร/พิมพ์', !empty($r['success']) && redirectUrl($r) === '/enroll-result?id='.$row->link, redirectUrl($r));

echo "\n=== ตัวเลือกแผนการเรียน / ไฟล์แนบ ===\n";
$r = Harness::call('\Enroll\Enroll\Controller', 'plans', ['get' => ['level' => 4], 'token' => $admin]);
Harness::check('ตัวเลือกแผนของระดับชั้นที่เลือก', !empty($r['success']) && count($r['data'] ?? []) === 3, json_encode($r['data'] ?? $r, JSON_UNESCAPED_UNICODE));
$files = \Enroll\Enroll\Model::attachments($row->id);
$r = Harness::call('\Enroll\Enroll\Controller', 'removefile', ['post' => ['action' => 'delete', 'link' => $row->link, 'url' => $files[0]['url']], 'token' => $admin]);
Harness::check('เจ้าหน้าที่ลบไฟล์แนบได้', !empty($r['success']) && count(\Enroll\Enroll\Model::attachments($row->id)) === 0, json_encode($r, JSON_UNESCAPED_UNICODE));

$code = Harness::summary();
Harness::cleanup();
exit($code);
