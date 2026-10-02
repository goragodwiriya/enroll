<?php
/**
 * ชุดทดสอบโมดูล enroll แบบไม่เปิดเบราว์เซอร์
 *
 * ใช้ APP_PATH ชี้ไปยัง tenant ชั่วคราว เพื่อ override settings/database.php
 * ของโปรเจกต์ (Kotchasan\Database อ่าน APP_PATH ก่อน ROOT_PATH) จะได้ทดสอบ
 * กับฐานข้อมูลของตัวเองโดยไม่แตะค่าตั้งของโปรเจกต์
 */
class Harness
{
    const APP_ROOT = '/mnt/Server/htdocs/now.js/enroll/adminframework/';
    const DB_NAME = 'enroll_dev_test';
    const PREFIX = 'app';
    const DB_USER = 'root';
    const DB_PASS = '21772177';

    public static $pdo;
    public static $csrf;
    public static $passed = 0;
    public static $failed = 0;

    /** ติดตั้งฐานข้อมูลทดสอบจาก install/database.sql แล้วบูตเฟรมเวิร์ก */
    public static function boot($scratch)
    {
        self::$pdo = new PDO('mysql:host=localhost;charset=utf8mb4', self::DB_USER, self::DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::$pdo->exec('DROP DATABASE IF EXISTS `'.self::DB_NAME.'`');
        self::$pdo->exec('CREATE DATABASE `'.self::DB_NAME.'` DEFAULT CHARACTER SET utf8mb4');
        self::$pdo->exec('USE `'.self::DB_NAME.'`');
        self::installSchema();

        // tenant ชั่วคราว
        $tenant = $scratch.'/tenant/';
        @mkdir($tenant.'settings', 0777, true);
        $database = include self::APP_ROOT.'settings/database.php';
        $database['mysql']['username'] = self::DB_USER;
        $database['mysql']['password'] = self::DB_PASS;
        $database['mysql']['dbname'] = self::DB_NAME;
        $database['mysql']['prefix'] = self::PREFIX;
        file_put_contents($tenant.'settings/database.php', "<?php\nreturn ".var_export($database, true).";\n");
        $config = include self::APP_ROOT.'install/settings/config.php';
        $base = include self::APP_ROOT.'settings/config.php';
        $config = array_merge($base, $config);
        $config['jwt_secret'] = str_repeat('a', 64);
        file_put_contents($tenant.'settings/config.php', "<?php\nreturn ".var_export($config, true).";\n");

        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/api';
        $_SERVER['SCRIPT_NAME'] = '/api.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        define('APP_PATH', $tenant);
        session_start();

        include self::APP_ROOT.'load.php';
        Kotchasan::createWebApplication('Gcms\Config');

        // query cache เก็บผลลัพธ์ตามตัว SQL ไม่ได้แยกตามฐานข้อมูล
        // ฐานข้อมูลทดสอบถูกสร้างใหม่ทุกครั้ง จึงต้องล้าง cache ก่อน ไม่งั้นได้ข้อมูลของรอบก่อน
        \Index\Cache\Controller::clearCache(['query']);

        self::$csrf = bin2hex(random_bytes(32));
        $_SESSION[self::$csrf] = ['times' => 0, 'expired' => time() + 3600, 'created' => time()];
    }

    /** รัน install/database.sql เหมือน install/step4.php */
    protected static function installSchema()
    {
        $lines = explode("\n", file_get_contents(self::APP_ROOT.'install/database.sql'));
        $buf = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && substr($line, 0, 2) !== '--') {
                $buf .= $line."\n";
            }
        }
        foreach (explode(";\n", $buf) as $cmd) {
            if (trim($cmd)) {
                self::$pdo->exec(str_replace('{prefix}', self::PREFIX, $cmd));
            }
        }
    }

    /** สร้างผู้ใช้ + session แล้วคืน JWT ที่ใช้เรียก API ได้ */
    public static function login($id, $permission = '', $status = 0)
    {
        $p = self::PREFIX;
        self::$pdo->exec("DELETE FROM `{$p}_user` WHERE `id` = ".(int) $id);
        $stmt = self::$pdo->prepare("INSERT INTO `{$p}_user` (`id`,`username`,`salt`,`password`,`status`,`active`,`permission`,`name`,`created_at`)
            VALUES (?, ?, 'x', 'x', ?, 1, ?, ?, NOW())");
        $stmt->execute([$id, 'user'.$id.'@test.local', $status, $permission, 'ผู้ใช้ทดสอบ '.$id]);

        $sid = substr(md5('sid'.$id), 0, 32);
        self::$pdo->exec("DELETE FROM `{$p}_user_session` WHERE `sid` = '$sid'");
        self::$pdo->exec("INSERT INTO `{$p}_user_session` (`sid`,`member_id`,`expires_at`) VALUES ('$sid', ".(int) $id.', '.(time() + 3600).')');

        return \Kotchasan\Jwt::encode([
            'sub' => (int) $id,
            'sid' => $sid,
            'iat' => time(),
            'exp' => time() + 3600
        ], \Kotchasan\Config::create()->jwt_secret);
    }

    /**
     * เรียกเมธอดของ controller แล้วคืนผลลัพธ์ที่ decode แล้ว
     *
     * @param string $class เช่น \Enroll\Levels\Controller
     * @param string $method
     * @param array $options get, post, token, method, files
     */
    public static function call($class, $method, array $options = [])
    {
        $verb = $options['method'] ?? (empty($options['post']) ? 'GET' : 'POST');
        $_SERVER['REQUEST_METHOD'] = $verb;
        $_SERVER['HTTP_AUTHORIZATION'] = isset($options['token']) ? 'Bearer '.$options['token'] : '';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = self::$csrf;
        $_SESSION[self::$csrf]['times'] = 0;

        $request = (new \Kotchasan\Http\Request())
            ->withQueryParams($options['get'] ?? [])
            ->withParsedBody($options['post'] ?? []);
        if (isset($options['files'])) {
            $request = $request->withUploadedFiles($options['files']);
        }

        $controller = new $class();
        $response = $controller->$method($request);
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        return $decoded === null ? ['__raw' => $body] : $decoded;
    }

    /** ตรวจผลลัพธ์หนึ่งข้อ */
    public static function check($label, $condition, $detail = '')
    {
        if ($condition) {
            self::$passed++;
            echo "  ✓ $label\n";
        } else {
            self::$failed++;
            echo "  ✗ $label".($detail === '' ? '' : "\n      $detail")."\n";
        }
    }

    public static function summary()
    {
        echo "\nผ่าน ".self::$passed." / ล้มเหลว ".self::$failed."\n";
        return self::$failed === 0 ? 0 : 1;
    }

    public static function cleanup()
    {
        self::$pdo->exec('DROP DATABASE IF EXISTS `'.self::DB_NAME.'`');
    }
}
