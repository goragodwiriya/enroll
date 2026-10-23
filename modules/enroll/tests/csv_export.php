<?php
/** ตรวจไฟล์ CSV ที่ได้จาก run_csv.php */
$out = shell_exec('php '.escapeshellarg(__DIR__.'/run_csv.php').' 2>/dev/null');
$passed = 0;
$failed = 0;
$check = function ($label, $cond, $detail = '') use (&$passed, &$failed) {
    if ($cond) { $passed++; echo "  ✓ $label\n"; }
    else { $failed++; echo "  ✗ $label".($detail === '' ? '' : "\n      $detail")."\n"; }
};

echo "\n=== ส่งออก CSV ===\n";
$check('ได้ข้อมูลกลับมา', !empty($out), 'ว่างเปล่า');
// เนื้อหาเป็น TIS-620? ค่าเริ่มต้นคือ UTF-8 with BOM
$hasBom = substr($out, 0, 3) === "\xEF\xBB\xBF";
$check('มี BOM ของ UTF-8', $hasBom, bin2hex(substr($out, 0, 6)));
$csv = $hasBom ? substr($out, 3) : $out;
$lines = array_values(array_filter(explode("\n", trim($csv)), 'strlen'));
$check('มีหัวตาราง + 2 แถว (กรองระดับ 1)', count($lines) === 3, count($lines).' บรรทัด: '.substr($csv, 0, 200));

$header = str_getcsv($lines[0]);
$check('หัวตารางเริ่มด้วยเลขประจำตัวผู้สมัคร และระดับการศึกษา',
    ($header[0] ?? '') === 'เลขประจำตัวผู้สมัคร' && ($header[1] ?? '') === 'ระดับการศึกษา',
    json_encode(array_slice($header, 0, 4), JSON_UNESCAPED_UNICODE));
$check('มีคอลัมน์ชื่อผู้ปกครองครบ 3 คน',
    count(array_filter($header, fn($h) => strpos($h, 'บิดา') !== false || strpos($h, 'มารดา') !== false || strpos($h, 'ผู้ปกครอง') !== false)) === 3,
    json_encode($header, JSON_UNESCAPED_UNICODE));
$check('มีคอลัมน์ผลการเรียน', in_array('เกรดเฉลี่ยสะสม (GPA)', $header, true));
$check('คอลัมน์สุดท้ายคือผลการสมัคร', end($header) === 'ผลการสมัคร', end($header));

$row = str_getcsv($lines[1]);
$check('แถวแรกคือผู้สมัครที่สมัครก่อน (เรียงตามวันที่)', ($row[0] ?? '') === 'E690001', $row[0] ?? '-');
$check('ระดับการศึกษาแปลงเป็นข้อความ', ($row[1] ?? '') === 'มัธยมศึกษาปีที่ 1', $row[1] ?? '-');
$check('แผนการเรียนที่เลือกแปลงเป็นข้อความ', ($row[2] ?? '') === 'คณิต-อังกฤษ', $row[2] ?? '-');
$check('คำนำหน้าแปลงเป็นข้อความ', in_array('นาย', $row, true), json_encode(array_slice($row, 0, 6), JSON_UNESCAPED_UNICODE));
$check('มีชื่อผู้ปกครอง', in_array('พ่อของ กานดา ใจงาม', $row, true), json_encode($row, JSON_UNESCAPED_UNICODE));
$check('ผลการสมัครแปลงเป็นข้อความ', end($row) === 'อนุมัติ', end($row));
$check('ตำบล/อำเภอ/จังหวัด แปลงเป็นชื่อ',
    in_array('เขาคราม', $row, true) && in_array('เมืองกระบี่', $row, true) && in_array('กระบี่', $row, true),
    json_encode($row, JSON_UNESCAPED_UNICODE));
$check('จำนวนคอลัมน์ของหัวตารางกับข้อมูลตรงกัน', count($header) === count($row), count($header).' vs '.count($row));

echo "\nผ่าน $passed / ล้มเหลว $failed\n";
exit($failed === 0 ? 0 : 1);
