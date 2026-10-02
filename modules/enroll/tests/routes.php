<?php
/**
 * ตรวจว่า URL ของโมดูลถูก resolve เป็นคลาสถูกต้อง
 * จำลองสิ่งที่ Kotchasan\Router ส่งให้ ApiController::index()
 */
require __DIR__.'/harness.php';
Harness::boot(__DIR__);

$routes = [
    'api/enroll/levels/get' => ['enroll', 'levels', 'get'],
    'api/enroll/levels/save' => ['enroll', 'levels', 'save'],
    'api/enroll/plans/get' => ['enroll', 'plans', 'get'],
    'api/enroll/register/get' => ['enroll', 'register', 'get'],
    'api/enroll/register/save' => ['enroll', 'register', 'save'],
    'api/enroll/register/plans' => ['enroll', 'register', 'plans'],
    'api/enroll/register/removefile' => ['enroll', 'register', 'removefile'],
    'api/enroll/result/get' => ['enroll', 'result', 'get'],
    'api/enroll/result/lookup' => ['enroll', 'result', 'lookup'],
    'api/enroll/printform' => ['enroll', 'printform', 'index'],
    'api/enroll/address/district' => ['enroll', 'address', 'district'],
    'api/enroll/address/amphur' => ['enroll', 'address', 'amphur'],
    'api/enroll/address/province' => ['enroll', 'address', 'province'],
    'api/enroll/enrolls' => ['enroll', 'enrolls', 'index'],
    'api/enroll/enrolls/action' => ['enroll', 'enrolls', 'action'],
    'api/enroll/enrolls/export' => ['enroll', 'enrolls', 'export'],
    'api/enroll/enrolls/summary' => ['enroll', 'enrolls', 'summary'],
    'api/enroll/applicants' => ['enroll', 'applicants', 'index'],
    'api/enroll/settings/get' => ['enroll', 'settings', 'get'],
    'api/enroll/settings/save' => ['enroll', 'settings', 'save'],
    'api/enroll/settings/reset' => ['enroll', 'settings', 'reset']
];

echo "\n=== URL → คลาส/เมธอด ===\n";
foreach ($routes as $url => $parts) {
    list($module, $method, $action) = $parts;
    $class = ucfirst($module).'\\'.ucfirst($method).'\\Controller';
    $ok = class_exists($class) && method_exists($class, $action);
    Harness::check($url.'  →  '.$class.'::'.$action.'()', $ok,
        class_exists($class) ? 'ไม่มีเมธอด '.$action : 'ไม่พบคลาส '.$class);
}

echo "\n=== เส้นทาง autoload ของโมดูล ===\n";
foreach ([
    '\Enroll\Init\Controller' => 'modules/enroll/controllers/init.php',
    '\Enroll\Base\Controller' => 'modules/enroll/controllers/base.php',
    '\Enroll\Tablebase\Controller' => 'modules/enroll/controllers/tablebase.php',
    '\Enroll\Enroll\Model' => 'modules/enroll/models/enroll.php',
    '\Enroll\Level\Model' => 'modules/enroll/models/level.php',
    '\Enroll\Plan\Model' => 'modules/enroll/models/plan.php',
    '\Enroll\Address\Model' => 'modules/enroll/models/address.php'
] as $class => $file) {
    Harness::check($class.'  →  '.$file, class_exists($class), 'autoload ไม่พบ');
}

echo "\n=== ไฟล์เทมเพลตที่ route อ้างถึงมีจริง ===\n";
$adminJs = file_get_contents(Harness::APP_ROOT.'modules/enroll/admin.js');
preg_match_all("/template: '([^']+)'/", $adminJs, $m);
Harness::check('admin.js ลงทะเบียน 7 route', count($m[1]) === 7, count($m[1]).' route');
foreach ($m[1] as $template) {
    Harness::check('มีไฟล์ templates/'.$template, is_file(Harness::APP_ROOT.'templates/'.$template));
}

echo "\n=== ฟังก์ชัน JS ที่เทมเพลตเรียกใช้ มีอยู่ใน admin.js ===\n";
foreach (glob(Harness::APP_ROOT.'templates/enroll/*.html') as $file) {
    $html = file_get_contents($file);
    preg_match_all('/data-(?:on-load|formatter)="([a-zA-Z0-9_]+)"/', $html, $fn);
    foreach (array_unique($fn[1]) as $name) {
        Harness::check(basename($file).' → '.$name.'()',
            strpos($adminJs, 'function '.$name.'(') !== false, 'ไม่พบใน admin.js');
    }
}

$code = Harness::summary();
Harness::cleanup();
exit($code);
