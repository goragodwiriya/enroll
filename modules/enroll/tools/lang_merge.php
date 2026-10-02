<?php
/**
 * เพิ่มรายการภาษาลง language/<lng>.php และ .json โดยไม่จัดเรียงรายการเดิมใหม่
 * รูปแบบผลลัพธ์ตรงกับ Index\Languages\Model::generatePhpContent()
 *
 * ใช้:  php lang_merge.php <base-dir> <entries.php>
 *   entries.php คืนค่า ['th' => [key => value, ...], 'en' => [...]]
 */
$base = rtrim($argv[1], '/').'/';
$entries = include $argv[2];

/** สร้างข้อความของรายการเดียว ตามรูปแบบของ generatePhpContent() */
function renderEntry($key, $value)
{
    if (is_array($value)) {
        $items = [];
        foreach ($value as $k => $v) {
            $keyPart = is_int($k) ? $k.' => ' : "'".addslashes($k)."' => ";
            if (is_int($v) || is_float($v)) {
                $items[] = $keyPart.$v;
            } else {
                $items[] = $keyPart."'".addslashes((string) $v)."'";
            }
        }
        return "'".addslashes($key)."' => array(\n    ".implode(",\n    ", $items)."\n  )";
    }
    if (is_int($value)) {
        return "'".addslashes($key)."' => ".$value;
    }
    return "'".addslashes($key)."' => '".addslashes($value)."'";
}

foreach ($entries as $lng => $items) {
    $php_file = $base.$lng.'.php';
    $json_file = $base.$lng.'.json';
    if (!is_file($php_file)) {
        fwrite(STDERR, "ไม่พบ $php_file\n");
        continue;
    }
    $data = include $php_file;

    // แยกข้อความของรายการเดิมออกมา เพื่อคงลำดับและรูปแบบเดิมไว้ทั้งหมด
    $raw = file_get_contents($php_file);
    $body = preg_replace('/^.*?return array\(\n/s', '', $raw);
    $body = preg_replace('/\n\);\s*$/s', '', $body);
    $chunks = preg_split("/,\n(?=  ')/", $body);

    $keys = [];
    foreach ($chunks as $i => $chunk) {
        if (preg_match("/^  '((?:[^'\\\\]|\\\\.)*)'/", $chunk, $m)) {
            $keys[$i] = stripslashes($m[1]);
        } else {
            $keys[$i] = null;
        }
    }

    $added = 0;
    $updated = 0;
    foreach ($items as $key => $value) {
        $rendered = '  '.renderEntry($key, $value);
        $pos = array_search($key, $keys, true);
        if ($pos !== false) {
            $chunks[$pos] = $rendered;
            $updated++;
            continue;
        }
        // แทรกตามลำดับตัวอักษรแบบไม่สนตัวพิมพ์ (เหมือน ORDER BY `key` ของ MySQL)
        $at = count($chunks);
        foreach ($keys as $i => $existing) {
            if ($existing !== null && strcasecmp($existing, $key) > 0) {
                $at = $i;
                break;
            }
        }
        array_splice($chunks, $at, 0, [$rendered]);
        array_splice($keys, $at, 0, [$key]);
        $added++;
    }

    $out = "<?php\n/* language/{$lng}.php */\nreturn array(\n".implode(",\n", $chunks)."\n);\n";
    file_put_contents($php_file, $out);

    // .json สร้างจากผลลัพธ์ของ .php เพื่อให้สองไฟล์ตรงกันเสมอ
    $merged = include $php_file;
    file_put_contents($json_file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    echo "$lng: เพิ่ม $added, แก้ไข $updated, รวม ".count($merged)." รายการ\n";
}
