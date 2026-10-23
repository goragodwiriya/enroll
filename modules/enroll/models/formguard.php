<?php
/**
 * @filesource modules/enroll/models/formguard.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Formguard;

/**
 * รหัสแบบฟอร์มสมัคร สำหรับกันสแปมโดยไม่ใช้ captcha และไม่อิง IP
 *
 * ผู้สมัครมักกรอกที่เครื่องของโรงเรียน (IP เดียวกัน เบราว์เซอร์เดียวกัน)
 * จึงตรวจจากแบบฟอร์มแต่ละครั้งแทน:
 * - รหัสลงลายเซ็น HMAC มีเวลาที่เปิดแบบฟอร์ม ปลอมหรือแก้เวลาไม่ได้
 * - มีอายุ (MAX_AGE) และใช้บันทึกใบสมัครได้ใบเดียว (nonce เก็บกับใบสมัคร)
 * - ส่งเร็วกว่า MIN_SECONDS = น่าสงสัย (ติดป้ายให้ตรวจ ไม่ปฏิเสธ)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * กรอกแบบฟอร์มที่มีหลายช่องและแนบรูปเสร็จเร็วกว่านี้ = ผิดธรรมชาติ (วินาที)
     */
    const MIN_SECONDS = 20;

    /**
     * อายุของแบบฟอร์ม (วินาที) เผื่อเวลาหาเอกสาร ถ่ายรูป
     */
    const MAX_AGE = 43200;

    /**
     * สร้างรหัสใหม่สำหรับการเปิดแบบฟอร์มหนึ่งครั้ง
     *
     * @return string
     */
    public static function issue()
    {
        $payload = self::encode(json_encode([
            't' => time(),
            'n' => bin2hex(random_bytes(16))
        ]));

        return $payload.'.'.self::sign($payload);
    }

    /**
     * ตรวจรหัสที่ส่งมากับใบสมัคร
     *
     * @param string $token
     *
     * @return array ['error' => null|'invalid'|'expired'|'used', 'nonce' => string, 'elapsed' => int]
     */
    public static function check($token)
    {
        $result = ['error' => 'invalid', 'nonce' => '', 'elapsed' => 0];
        $parts = explode('.', (string) $token);
        if (count($parts) !== 2 || !hash_equals(self::sign($parts[0]), $parts[1])) {
            return $result;
        }
        $data = json_decode(self::decode($parts[0]), true);
        if (!is_array($data) || !isset($data['t'], $data['n']) || !preg_match('/^[a-f0-9]{32}$/', (string) $data['n'])) {
            return $result;
        }
        $result['nonce'] = $data['n'];
        $result['elapsed'] = time() - (int) $data['t'];
        if ($result['elapsed'] < 0 || $result['elapsed'] > self::MAX_AGE) {
            $result['error'] = 'expired';
        } elseif (self::used($data['n'])) {
            $result['error'] = 'used';
        } else {
            $result['error'] = null;
        }

        return $result;
    }

    /**
     * รหัสนี้ถูกใช้บันทึกใบสมัครไปแล้วหรือยัง
     *
     * @param string $nonce
     *
     * @return bool
     */
    public static function used($nonce)
    {
        return \Kotchasan\DB::create()->exists('enroll', [['form_nonce', $nonce]]);
    }

    /**
     * ลายเซ็นของรหัส (กุญแจแยกจาก jwt_secret ด้วย HMAC จึงไม่ปนกับงานอื่น)
     *
     * @param string $payload
     *
     * @return string
     */
    protected static function sign($payload)
    {
        $key = hash_hmac('sha256', 'enroll-form-guard', (string) (self::$cfg->jwt_secret ?? '').(string) (self::$cfg->password_key ?? ''), true);

        return self::encode(hash_hmac('sha256', $payload, $key, true));
    }

    /**
     * @param string $data
     *
     * @return string
     */
    protected static function encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param string $data
     *
     * @return string
     */
    protected static function decode($data)
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
