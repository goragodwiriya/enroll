<?php
/**
 * ชุดทดสอบโมดูล enroll แบบไม่เปิดเบราว์เซอร์
 *
 * ใช้:  php modules/enroll/tests/run.php
 *
 * ทุกชุดสร้างฐานข้อมูลทดสอบของตัวเองจาก install/database.sql แล้วลบทิ้งเมื่อจบ
 * ค่าเชื่อมต่อฐานข้อมูลกำหนดไว้ใน harness.php (แก้ที่นั่นถ้าเครื่องต่างออกไป)
 */
$suites = [
    'ระดับชั้น/แผนการเรียน/ที่อยู่' => 'levels_plans_address.php',
    'ฟอร์มลงทะเบียน' => 'register.php',
    'ผลการสมัคร + พิมพ์ใบสมัคร' => 'result_print.php',
    'หน้าแรก + รายละเอียดของหน้า' => 'home_pages.php',
    'จำนวนที่รับ / PDPA / ส่งลิงก์ / ประกาศผล / บัตรสอบ / ภาษา' => 'features.php',
    'ตารางจัดการผู้สมัคร + การ์ดหน้าแรก' => 'enrolls_table.php',
    'ใบสมัครของเจ้าหน้าที่ (แยกจากหน้าผู้สมัคร)' => 'enroll_form.php',
    'ตั้งค่า + เมนู/สิทธิ์' => 'settings_menus.php',
    'เส้นทาง URL และการผูกเทมเพลต' => 'routes.php',
    'ส่งออก CSV' => 'csv_export.php',
    'ผู้สมัครตัวอย่างของตัวติดตั้ง' => 'install_sample.php'
];

// ชุดทดสอบใช้โฟลเดอร์ datas/enroll/ ของโปรเจ็คจริง (DATA_FOLDER เป็นค่าคงที่ ย้ายไม่ได้)
// และทดสอบ "ล้างฐานข้อมูล" กับ "ลบใบสมัคร" ซึ่งลบรูปและไฟล์แนบในนั้นทิ้งจริง
// มีไฟล์อยู่แล้ว = เครื่องนี้มีข้อมูลผู้สมัคร ห้ามรัน ไม่งั้นรูปของผู้สมัครหายหมด
$dataDir = dirname(__DIR__, 3).'/datas/enroll';
$hadDataDir = is_dir($dataDir);
if ($hadDataDir && count(array_diff(scandir($dataDir), ['.', '..'])) > 0) {
    fwrite(STDERR, "หยุด: $dataDir มีไฟล์ของผู้สมัครอยู่ ชุดทดสอบจะลบไฟล์เหล่านี้\n"
        ."ให้รันบนสำเนาของโปรเจ็ค หรือย้ายโฟลเดอร์นี้ออกไปก่อน\n");
    exit(2);
}

$passed = 0;
$failed = 0;
foreach ($suites as $label => $file) {
    echo "\n########## $label ##########\n";
    $output = [];
    $exitCode = 0;
    exec('php '.escapeshellarg(__DIR__.'/'.$file).' 2>&1', $output, $exitCode);
    $summarized = false;
    foreach ($output as $line) {
        // ข้ามคำเตือนของ PHP และ log ของเฟรมเวิร์กที่ไม่เกี่ยวกับผลทดสอบ
        if (strpos($line, 'PHP warning') === 0 || strpos($line, '[ERROR]') === 0) {
            continue;
        }
        echo $line."\n";
        if (preg_match('/^ผ่าน ([0-9]+) \/ ล้มเหลว ([0-9]+)$/u', trim($line), $m)) {
            $passed += (int) $m[1];
            $failed += (int) $m[2];
            $summarized = true;
        }
    }
    // ชุดที่หยุดกลางคัน (exception/fatal) ไม่มีบรรทัดสรุป นับเป็นล้มเหลว
    // ไม่งั้นผลรวมจะบอกว่าไม่มีข้อผิดพลาดทั้งที่ข้อทดสอบที่เหลือไม่ได้รันเลย
    if (!$summarized) {
        $failed++;
        echo "  ✗ ชุดทดสอบ $file หยุดก่อนจบ (exit $exitCode)\n";
    }
}

// ไฟล์ที่ชุดทดสอบอัปโหลดไว้ ลบทิ้งถ้าก่อนรันยังไม่มีโฟลเดอร์นี้
if (!$hadDataDir && is_dir($dataDir)) {
    exec('rm -rf '.escapeshellarg($dataDir));
}

echo "\n==================================================\n";
echo "รวมทั้งหมด: ผ่าน $passed / ล้มเหลว $failed\n";
exit($failed === 0 ? 0 : 1);
