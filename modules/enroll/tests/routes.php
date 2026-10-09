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
    'api/enroll/home/get' => ['enroll', 'home', 'get'],
    'api/enroll/pages/get' => ['enroll', 'pages', 'get'],
    'api/enroll/pages/save' => ['enroll', 'pages', 'save'],
    'api/enroll/address/district' => ['enroll', 'address', 'district'],
    'api/enroll/address/amphur' => ['enroll', 'address', 'amphur'],
    'api/enroll/address/province' => ['enroll', 'address', 'province'],
    'api/enroll/enrolls' => ['enroll', 'enrolls', 'index'],
    'api/enroll/enrolls/action' => ['enroll', 'enrolls', 'action'],
    'api/enroll/enrolls/export' => ['enroll', 'enrolls', 'export'],
    'api/enroll/applicants' => ['enroll', 'applicants', 'index'],
    // ใบสมัครของเจ้าหน้าที่ แยกจาก api/enroll/register ของผู้สมัคร
    'api/enroll/enroll/get' => ['enroll', 'enroll', 'get'],
    'api/enroll/enroll/save' => ['enroll', 'enroll', 'save'],
    'api/enroll/enroll/plans' => ['enroll', 'enroll', 'plans'],
    'api/enroll/enroll/removefile' => ['enroll', 'enroll', 'removefile'],
    // หน้าแรกของเจ้าหน้าที่ใช้หน้ากลางของระบบ (การ์ดจาก hook initDashboard)
    'api/index/dashboard' => ['index', 'dashboard', 'index'],
    'api/enroll/announce/get' => ['enroll', 'announce', 'get'],
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
    '\Enroll\Enroll\Controller' => 'modules/enroll/controllers/enroll.php',
    '\Enroll\Applicants\Controller' => 'modules/enroll/controllers/applicants.php',
    '\Enroll\Enroll\Model' => 'modules/enroll/models/enroll.php',
    '\Enroll\Level\Model' => 'modules/enroll/models/level.php',
    '\Enroll\Plan\Model' => 'modules/enroll/models/plan.php',
    '\Enroll\Address\Model' => 'modules/enroll/models/address.php',
    '\\Enroll\\Formguard\\Model' => 'modules/enroll/models/formguard.php'
] as $class => $file) {
    Harness::check($class.'  →  '.$file, class_exists($class), 'autoload ไม่พบ');
}

echo "\n=== ไฟล์เทมเพลตที่ route อ้างถึงมีจริง ===\n";
$adminJs = file_get_contents(Harness::appRoot().'modules/enroll/admin.js');
preg_match_all("/template: '([^']+)'/", $adminJs, $m);
// ผู้สมัคร 6 หน้า: / /home /enroll-edit /enroll-result /enroll-announce /enroll-privacy
// เจ้าหน้าที่ 8 หน้า: /dashboard /enrolls /enroll /enroll-applicants /enroll-pages /enroll-levels /enroll-plans /enroll-settings
Harness::check('admin.js ลงทะเบียน 14 route', count($m[1]) === 14, count($m[1]).' route');
preg_match_all("/RouterManager\\.register\\('([^']+)'/", $adminJs, $paths);
// route => [template, requireAuth]
$routeInfo = [];
preg_match_all("#register\\('([^']+)', \\{(.*?)\\n  \\}\\);#s", $adminJs, $blocks, PREG_SET_ORDER);
foreach ($blocks as $block) {
    preg_match("/template: '([^']+)'/", $block[2], $tpl);
    $routeInfo[$block[1]] = [$tpl[1] ?? '', (bool) preg_match('/requireAuth: true/', $block[2])];
}
foreach (['/', '/home', '/enroll-edit', '/enroll-result', '/enroll-announce', '/enroll-privacy'] as $path) {
    Harness::check('หน้าผู้สมัคร '.$path.' เปิดได้โดยไม่ต้องเข้าระบบ', isset($routeInfo[$path]) && $routeInfo[$path][1] === false);
}
foreach (['/dashboard', '/enrolls', '/enroll', '/enroll-applicants', '/enroll-pages', '/enroll-levels', '/enroll-plans', '/enroll-settings'] as $path) {
    Harness::check('หน้าเจ้าหน้าที่ '.$path.' ต้องเข้าระบบ', isset($routeInfo[$path]) && $routeInfo[$path][1] === true);
}
// ชื่อตามกติกา: พหูพจน์ = ตาราง เอกพจน์ = ฟอร์ม (route/เทมเพลต/controller ชื่อเดียวกัน)
Harness::check('ตารางผู้สมัคร /enrolls → enroll/enrolls.html', ($routeInfo['/enrolls'][0] ?? '') === 'enroll/enrolls.html');
Harness::check('ฟอร์มใบสมัครของเจ้าหน้าที่ /enroll → enroll/enroll.html', ($routeInfo['/enroll'][0] ?? '') === 'enroll/enroll.html');
Harness::check('รายงานผู้สมัคร /enroll-applicants → enroll/applicants.html', ($routeInfo['/enroll-applicants'][0] ?? '') === 'enroll/applicants.html');
Harness::check('หน้าแรกของเจ้าหน้าที่ /dashboard ใช้หน้ากลาง index.html', ($routeInfo['/dashboard'][0] ?? '') === 'index.html');
Harness::check('ไม่มี route ชื่อเก่า (/enroll-setup, /enroll-list)', !isset($routeInfo['/enroll-setup']) && !isset($routeInfo['/enroll-list']));
Harness::check('เจ้าหน้าที่ที่เข้าระบบถูกพาจาก / ไป /dashboard', (bool) preg_match("#return '/dashboard';#", $adminJs));
Harness::check('หน้าของผู้สมัครครบ: / /home /enroll-edit /enroll-result',
    count(array_intersect(['/', '/home', '/enroll-edit', '/enroll-result'], $paths[1])) === 4, json_encode($paths[1]));
Harness::check('หน้าแรก (/) เปิดได้โดยไม่ต้องเข้าระบบ',
    (bool) preg_match("#register\\('/', \\{[^}]*?template: 'enroll/home\\.html',\\s*title: '[^']*',\\s*requireAuth: false#s", $adminJs));
foreach ($m[1] as $template) {
    Harness::check('มีไฟล์ templates/'.$template, is_file(Harness::appRoot().'templates/'.$template));
}

echo "\n=== ฟังก์ชัน JS ที่เทมเพลตเรียกใช้ มีอยู่ใน admin.js ===\n";
foreach (glob(Harness::appRoot().'templates/enroll/*.html') as $file) {
    $html = file_get_contents($file);
    preg_match_all('/data-(?:on-load|formatter)="([a-zA-Z0-9_]+)"/', $html, $fn);
    foreach (array_unique($fn[1]) as $name) {
        Harness::check(basename($file).' → '.$name.'()',
            strpos($adminJs, 'function '.$name.'(') !== false, 'ไม่พบใน admin.js');
    }
}

echo "\n=== หน้าเจ้าหน้าที่แยกจากหน้าผู้สมัคร / ตัวกรองของตาราง ===\n";
// ตัดความเห็น HTML ออกก่อน (คำอธิบายในเทมเพลตอ้างถึงอีกฝั่งได้ ไม่ใช่การเรียกใช้)
$tpl = function ($name) {
    return preg_replace('/<!--.*?-->/s', '', file_get_contents(Harness::appRoot().'templates/enroll/'.$name));
};
$register = $tpl('register.html');
$enroll = $tpl('enroll.html');
Harness::check('ฟอร์มผู้สมัครใช้เฉพาะ api/enroll/register', strpos($register, 'api/enroll/register/get') !== false
    && strpos($register, 'api/enroll/register/save') !== false && strpos($register, 'api/enroll/enroll/') === false);
Harness::check('ฟอร์มเจ้าหน้าที่ใช้เฉพาะ api/enroll/enroll (โหลด บันทึก ลบไฟล์แนบ)', strpos($enroll, 'api/enroll/enroll/get') !== false
    && strpos($enroll, 'api/enroll/enroll/save') !== false && strpos($enroll, 'api/enroll/enroll/removefile') !== false
    && strpos($enroll, 'api/enroll/register') === false);
Harness::check('ฟอร์มเจ้าหน้าที่อยู่ในเลย์เอาต์ผู้ดูแล ไม่มีส่วนของผู้สมัคร (ยินยอม/ดักบอต/รหัสแบบฟอร์ม/ทีละขั้น)',
    strpos($enroll, 'data-component="sidebar"') !== false && strpos($enroll, 'name="consent"') === false
    && strpos($enroll, 'name="website"') === false && strpos($enroll, 'form_token') === false && strpos($enroll, 'data-step') === false);
foreach (glob(Harness::appRoot().'templates/enroll/*.html') as $file) {
    Harness::check(basename($file).' ไม่มีฟอร์มตัวกรองภายนอก (ใช้ตัวกรองในตัวของ TableManager)',
        strpos(file_get_contents($file), 'data-table-filter') === false);
}

$code = Harness::summary();
Harness::cleanup();
exit($code);
