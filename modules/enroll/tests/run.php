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
    'ตารางจัดการผู้สมัคร' => 'enrolls_table.php',
    'ตั้งค่า + เมนู/สิทธิ์' => 'settings_menus.php',
    'เส้นทาง URL และการผูกเทมเพลต' => 'routes.php',
    'ส่งออก CSV' => 'csv_export.php'
];

$passed = 0;
$failed = 0;
foreach ($suites as $label => $file) {
    echo "\n########## $label ##########\n";
    $output = [];
    exec('php '.escapeshellarg(__DIR__.'/'.$file).' 2>&1', $output);
    foreach ($output as $line) {
        // ข้ามคำเตือนของ PHP และ log ของเฟรมเวิร์กที่ไม่เกี่ยวกับผลทดสอบ
        if (strpos($line, 'PHP warning') === 0 || strpos($line, '[ERROR]') === 0) {
            continue;
        }
        echo $line."\n";
        if (preg_match('/^ผ่าน ([0-9]+) \/ ล้มเหลว ([0-9]+)$/u', trim($line), $m)) {
            $passed += (int) $m[1];
            $failed += (int) $m[2];
        }
    }
}

echo "\n==================================================\n";
echo "รวมทั้งหมด: ผ่าน $passed / ล้มเหลว $failed\n";
exit($failed === 0 ? 0 : 1);
