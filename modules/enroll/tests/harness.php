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
    const DB_NAME = 'enroll_dev_test';
    const PREFIX = 'app';
    const DB_USER = 'root';
    const DB_PASS = '21772177';

    public static $pdo;
    public static $csrf;
    public static $passed = 0;
    public static $failed = 0;

    /** รากของโปรเจกต์ (modules/enroll/tests/ ขึ้นไปสามชั้น) */
    public static function appRoot()
    {
        return dirname(__DIR__, 3).'/';
    }

    /**
     * ติดตั้งฐานข้อมูลทดสอบจาก install/database.sql แล้วบูตเฟรมเวิร์ก
     *
     * @param string $scratch
     * @param bool $withSamples true = เก็บผู้สมัครตัวอย่างของตัวติดตั้งไว้ (ชุด install_sample.php)
     *                          ชุดอื่นนับแถวและเลขรันจากตารางว่าง จึงล้างทิ้งก่อน
     */
    public static function boot($scratch, $withSamples = false)
    {
        self::$pdo = new PDO('mysql:host=localhost;charset=utf8mb4', self::DB_USER, self::DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::$pdo->exec('DROP DATABASE IF EXISTS `'.self::DB_NAME.'`');
        self::$pdo->exec('CREATE DATABASE `'.self::DB_NAME.'` DEFAULT CHARACTER SET utf8mb4');
        self::$pdo->exec('USE `'.self::DB_NAME.'`');
        self::installSchema();
        if (!$withSamples) {
            self::$pdo->exec('TRUNCATE TABLE `'.self::PREFIX.'_enroll`');
            self::$pdo->exec('TRUNCATE TABLE `'.self::PREFIX.'_enroll_choices`');
        }

        // tenant ชั่วคราว
        $tenant = $scratch.'/tenant/';
        @mkdir($tenant.'settings', 0777, true);
        $database = include self::appRoot().'settings/database.php';
        $database['mysql']['username'] = self::DB_USER;
        $database['mysql']['password'] = self::DB_PASS;
        $database['mysql']['dbname'] = self::DB_NAME;
        $database['mysql']['prefix'] = self::PREFIX;
        file_put_contents($tenant.'settings/database.php', "<?php\nreturn ".var_export($database, true).";\n");
        $config = include self::appRoot().'install/settings/config.php';
        $base = include self::appRoot().'settings/config.php';
        $config = array_merge($base, $config);
        $config['jwt_secret'] = str_repeat('a', 64);
        // ห้ามส่งอีเมลจริงระหว่างทดสอบ: เครื่องพัฒนามี mail server ที่ relay ออกไปได้ ใบสมัครที่มีอีเมล
        // (ส่งลิงก์ใบสมัคร) เคยเข้าคิวจริง — ชี้ SMTP ไปพอร์ตที่ปิด ถูกปฏิเสธทันทีและไม่มีอะไรออกไป
        $config['email_use_phpMailer'] = 1;
        $config['email_Host'] = '127.0.0.1';
        $config['email_Port'] = 1;
        $config['email_Timeout'] = 2;
        file_put_contents($tenant.'settings/config.php', "<?php\nreturn ".var_export($config, true).";\n");

        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/api';
        $_SERVER['SCRIPT_NAME'] = '/api.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        define('APP_PATH', $tenant);
        session_start();

        include self::appRoot().'load.php';
        Kotchasan::createWebApplication('Gcms\Config');

        // query cache เก็บผลลัพธ์ตามตัว SQL ไม่ได้แยกตามฐานข้อมูล
        // ฐานข้อมูลทดสอบถูกสร้างใหม่ทุกครั้ง จึงต้องล้าง cache ก่อน ไม่งั้นได้ข้อมูลของรอบก่อน
        \Index\Cache\Controller::clearCache(['query']);

        self::$csrf = bin2hex(random_bytes(32));
        $_SESSION[self::$csrf] = ['times' => 0, 'expired' => time() + 3600, 'created' => time()];
    }

    /**
     * สร้างสคีมาแบบเดียวกับ schemaCommands() ใน install/common.php
     *
     * ไฟล์ของโปรเจ็ค (core/database/timeline) ตามด้วยไฟล์ของแต่ละโมดูล
     * แล้วเรียงเป็น สร้างตาราง → ขยายตาราง → ใส่ข้อมูล
     */
    protected static function installSchema()
    {
        $files = [];
        foreach (['core.sql', 'database.sql', 'timeline.sql'] as $name) {
            if (is_file(self::appRoot().'install/'.$name)) {
                $files[] = self::appRoot().'install/'.$name;
            }
        }
        foreach (glob(self::appRoot().'modules/*/install/database.sql') ?: [] as $file) {
            $files[] = $file;
        }
        $creates = [];
        $alters = [];
        $others = [];
        foreach ($files as $file) {
            $buf = '';
            foreach (explode("\n", file_get_contents($file)) as $line) {
                $line = trim($line);
                if ($line !== '' && substr($line, 0, 2) !== '--') {
                    $buf .= $line."\n";
                }
            }
            foreach (explode(";\n", $buf) as $cmd) {
                if (trim($cmd) === '') {
                    continue;
                }
                $cmd = str_replace('{prefix}', self::PREFIX, $cmd);
                if (preg_match('/^ALTER\s+TABLE/i', $cmd)) {
                    $alters[] = $cmd;
                } elseif (preg_match('/^(CREATE|DROP)\s/i', $cmd)) {
                    $creates[] = $cmd;
                } else {
                    $others[] = $cmd;
                }
            }
        }
        foreach (array_merge($creates, $alters, $others) as $cmd) {
            self::$pdo->exec($cmd);
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

    /**
     * รหัสแบบฟอร์มสมัครที่ "เปิดฟอร์มไว้แล้ว $secondsAgo วินาที" (ลงลายเซ็นจริงด้วยกุญแจของระบบ)
     * ใช้แทนการรอเวลาจริงในชุดทดสอบ
     */
    public static function formToken($secondsAgo = 60, $nonce = null)
    {
        $encode = new \ReflectionMethod('\Enroll\Formguard\Model', 'encode');
        $sign = new \ReflectionMethod('\Enroll\Formguard\Model', 'sign');
        $encode->setAccessible(true);
        $sign->setAccessible(true);
        $payload = $encode->invoke(null, json_encode([
            't' => time() - (int) $secondsAgo,
            'n' => $nonce ?? bin2hex(random_bytes(16))
        ]));

        return $payload.'.'.$sign->invoke(null, $payload);
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
