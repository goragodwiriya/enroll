<?php
/** รัน export CSV แล้วปล่อยผลออก stdout (handler เรียก exit เอง) */
require __DIR__.'/harness.php';
Harness::boot(__DIR__);
$admin = Harness::login(1, 'can_manage_enroll,can_config', 1);
$p = Harness::PREFIX;

$rows = [
    [1, 1, 'E690001', 'กานดา ใจงาม', '{"GPA":3.90}', 1, 1, '2026-01-01 09:00:00'],
    [2, 1, 'E690002', 'ขจร ยอดเยี่ยม', '{"GPA":2.50}', 2, 0, '2026-01-02 09:00:00'],
    [3, 4, 'E690003', 'คมสัน ตั้งใจ', '{"GPA":3.10}', 0, 0, '2026-01-03 09:00:00']
];
foreach ($rows as $r) {
    $stmt = Harness::$pdo->prepare("INSERT INTO `{$p}_enroll`
        (`id`,`level`,`enroll_no`,`title`,`name`,`id_card`,`birthday`,`phone`,`email`,`nationality`,`religion`,
         `address`,`districtID`,`amphurID`,`provinceID`,`zipcode`,`parent`,`original_school`,`academic_results`,
         `created_at`,`link`,`result_plan`,`result_status`)
        VALUES (?, ?, ?, 3, ?, ?, '2009-05-05', '0800000000', 'a@b.c', 'ไทย', 'พุทธ',
         '1/1', 1010101, 10101, 101, '81000', ?, 'โรงเรียนเดิม', ?, ?, ?, ?, ?)");
    $stmt->execute([$r[0], $r[1], $r[2], $r[3], '110000000000'.$r[0],
        '{"father":{"name":"พ่อของ '.$r[3].'","phone":"0811111111"},"mother":{"name":"แม่","phone":"0822222222"},"parent":{"name":"","phone":""}}',
        $r[4], $r[7], str_pad((string) $r[0], 32, 'y'), $r[5], $r[6]]);
}
Harness::$pdo->exec("INSERT INTO `{$p}_enroll_choices` (`enroll_id`,`no`,`plan_id`) VALUES (1,0,1),(1,1,3),(2,0,2),(3,0,4)");

// เก็บชื่อฐานข้อมูลไว้ให้สคริปต์แม่ลบทีหลัง (ที่นี่ exit ก่อน cleanup)
register_shutdown_function(function () {
    (new PDO('mysql:host=localhost', Harness::DB_USER, Harness::DB_PASS))
        ->exec('DROP DATABASE IF EXISTS `'.Harness::DB_NAME.'`');
});

Harness::call('\Enroll\Enrolls\Controller', 'export', [
    'token' => $admin,
    'get' => ['type' => 'csv', 'level' => 1, 'sort' => 'created_at asc']
]);
