<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * เพิ่ม/แก้คำแปลใน language/<lng>.php และ .json
 *
 * อ่านไฟล์เดิมเป็นอาร์เรย์ด้วย include แล้วเขียนใหม่ทั้งไฟล์ด้วยรูปแบบเดียวกับ
 * Index\Languages\Model::generatePhpContent() (หน้าจัดการภาษาส่งออกได้ไฟล์เดียวกันทุกไบต์)
 *
 *   - คงลำดับรายการเดิมทั้งหมด · คีย์ใหม่แทรกที่ **ระดับบนสุด** ตามลำดับตัวอักษรแบบไม่สนตัวพิมพ์
 *     (เหมือน ORDER BY `key` ของ MySQL) ไม่มีทางลงไปอยู่ในอาร์เรย์ย่อยอย่าง DATE_LONG หรือ ESIGNATURE_ACTION
 *     (รุ่นก่อนแยกไฟล์ด้วย regex ตามการย่อหน้า คีย์ที่แทรกผิดที่หนึ่งครั้งทำให้อาร์เรย์ย่อยถูกแบ่ง
 *     แล้วคีย์ถัด ๆ ไปไหลเข้าไปอยู่ข้างใน — ฝั่ง PHP หาคำแปลไม่เจอแม้ .json จะถูกต้อง)
 *   - --repair  ย้ายคีย์ข้อความ (มีช่องว่าง) ที่หลงอยู่ในอาร์เรย์ย่อยกลับขึ้นระดับบนสุด
 *               อาร์เรย์ย่อยของระบบใช้คีย์แบบตัวระบุ/ตัวเลขเท่านั้น (DOCUMENT_CREATED, 1, pdf)
 *   - .json สร้างจากอาร์เรย์เดียวกัน สองไฟล์จึงตรงกันเสมอ
 *
 * ใช้:  php lang_merge.php <base-dir> <entries.php> [--repair]
 *       php lang_merge.php <base-dir> --repair
 *   entries.php คืนค่า ['th' => [key => value, ...], 'en' => [...]]
 */
$args = array_slice($argv, 1);
$repair = in_array('--repair', $args, true);
$args = array_values(array_diff($args, ['--repair']));
if (!isset($args[0]) || (!isset($args[1]) && !$repair)) {
    fwrite(STDERR, "ใช้: php lang_merge.php <base-dir> <entries.php> [--repair]\n");
    exit(1);
}
/**
 * include ใน scope แยก — ไฟล์คำแปลของโมดูลมักประกาศตัวแปร ($th, $data, ...) ซึ่งจะทับตัวแปรของเครื่องมือนี้
 */
function loadFile($file)
{
    return (static function ($__file) {
        return include $__file;
    })($file);
}
$base = rtrim($args[0], '/').'/';
$entries = isset($args[1]) ? loadFile($args[1]) : [];
if (!is_array($entries)) {
    fwrite(STDERR, "entries.php ต้องคืนค่า array\n");
    exit(1);
}
if ($repair) {
    // ซ่อมทุกภาษาที่มีไฟล์ แม้ entries ไม่มีภาษานั้น
    foreach (glob($base.'*.php') ?: [] as $file) {
        $lng = basename($file, '.php');
        if (preg_match('/^[a-z]{2}$/', $lng) && !isset($entries[$lng])) {
            $entries[$lng] = [];
        }
    }
}

/**
 * escape สำหรับ string แบบ single-quoted ของ PHP — มีแค่ \ กับ ' เท่านั้น
 */
function q($value)
{
    return str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value);
}

/**
 * เนื้อไฟล์ — ตรงกับ Index\Languages\Model::generatePhpContent()
 */
function generatePhpContent($lang, array $data)
{
    $lines = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $k => $v) {
                $keyPart = is_int($k) ? $k.' => ' : "'".q($k)."' => ";
                $items[] = is_int($v) || is_float($v) ? $keyPart.$v : $keyPart."'".q((string) $v)."'";
            }
            $lines[] = "'".q($key)."' => array(\n    ".implode(",\n    ", $items)."\n  )";
        } elseif (is_int($value)) {
            $lines[] = "'".q($key)."' => ".$value;
        } else {
            $lines[] = "'".q($key)."' => '".q($value)."'";
        }
    }
    return "<?php\n/* language/{$lang}.php */\nreturn array(\n  ".implode(",\n  ", $lines)."\n);\n";
}

/**
 * แทรกคีย์ใหม่ที่ระดับบนสุด ก่อนคีย์แรกที่มากกว่า (strcasecmp) — ไม่แตะลำดับของคีย์เดิม
 */
function insertSorted(array $data, $key, $value)
{
    $out = [];
    $inserted = false;
    foreach ($data as $k => $v) {
        if (!$inserted && strcasecmp((string) $k, (string) $key) > 0) {
            $out[$key] = $value;
            $inserted = true;
        }
        $out[$k] = $v;
    }
    if (!$inserted) {
        $out[$key] = $value;
    }
    return $out;
}

/**
 * คีย์ข้อความที่หลงอยู่ในอาร์เรย์ย่อย
 *
 * @return array [[parent, key, value], ...]
 */
function strayKeys(array $data)
{
    $stray = [];
    foreach ($data as $parent => $value) {
        if (!is_array($value)) {
            continue;
        }
        foreach ($value as $k => $v) {
            if (is_string($k) && preg_match('/\s/u', $k) && is_string($v)) {
                $stray[] = [$parent, $k, $v];
            }
        }
    }
    return $stray;
}

$status = 0;
foreach ($entries as $lng => $items) {
    $php_file = $base.$lng.'.php';
    $json_file = $base.$lng.'.json';
    if (!is_file($php_file)) {
        fwrite(STDERR, "ไม่พบ $php_file\n");
        $status = 1;
        continue;
    }
    $data = loadFile($php_file);
    if (!is_array($data)) {
        fwrite(STDERR, "$php_file ไม่ได้คืนค่า array — ไม่แตะไฟล์\n");
        $status = 1;
        continue;
    }

    $moved = 0;
    foreach (strayKeys($data) as list($parent, $key, $value)) {
        if (!$repair) {
            fwrite(STDERR, "$lng: คีย์ '$key' หลงอยู่ในอาร์เรย์ย่อย '$parent' — รันด้วย --repair เพื่อย้ายขึ้นระดับบนสุด\n");
            continue;
        }
        unset($data[$parent][$key]);
        if (!array_key_exists($key, $data)) {
            $data = insertSorted($data, $key, $value);
        }
        $moved++;
        echo "$lng: ย้าย '$key' ออกจาก '$parent' ขึ้นระดับบนสุด\n";
    }

    $added = 0;
    $updated = 0;
    foreach ((array) $items as $key => $value) {
        if (array_key_exists($key, $data)) {
            if ($data[$key] !== $value) {
                $data[$key] = $value;
                $updated++;
            }
            continue;
        }
        $data = insertSorted($data, $key, $value);
        $added++;
    }

    file_put_contents($php_file, generatePhpContent($lng, $data));
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($php_file, true);
    }
    // .json จากอาร์เรย์เดียวกัน (รูปแบบเดียวกับ Index\Languages\Model::exportToFile)
    file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    echo "$lng: เพิ่ม $added, แก้ไข $updated".($repair ? ", ย้าย $moved" : '').", รวม ".count($data)." รายการ\n";
}
exit($status);
