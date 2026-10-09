<?php
/**
 * @filesource modules/enroll/models/notify.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Notify;

use Kotchasan\Language;

/**
 * ส่งลิงก์ใบสมัครให้ผู้สมัครหลังสมัครสำเร็จ (อีเมล / SMS)
 *
 * ลิงก์ใบสมัครคือกุญแจกลับมาดูผล พิมพ์ และแก้ไข ผู้สมัครที่กรอกที่เครื่องของโรงเรียน
 * จึงควรได้ลิงก์ติดตัวไปด้วย การส่งไม่สำเร็จต้องไม่ทำให้การสมัครล้มเหลว
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * รหัสแม่แบบอีเมล (สร้างแม่แบบชื่อนี้ในตาราง emailtemplate เพื่อปรับข้อความเองได้)
     */
    const EMAIL_TEMPLATE = 'enroll_link';

    /**
     * ส่งลิงก์ใบสมัครที่เพิ่งบันทึก
     *
     * @param object $enroll แถวใบสมัคร
     *
     * @return array ['email' => true|string|null, 'sms' => true|string|null]
     *               true = ส่งแล้ว, string = ข้อผิดพลาด, null = ไม่ได้ส่ง (ปิดไว้ หรือไม่มีที่อยู่)
     */
    public static function applicationSaved($enroll)
    {
        $result = ['email' => null, 'sms' => null];
        $resultUrl = WEB_URL.'enroll-result?id='.$enroll->link;

        if (!empty(self::$cfg->enroll_notify_email) && filter_var((string) $enroll->email, FILTER_VALIDATE_EMAIL)) {
            try {
                self::registerTemplate();
                $error = \Gcms\EmailTemplate::send(self::EMAIL_TEMPLATE, $enroll->email, self::variables($enroll), [
                    'buttonUrl' => $resultUrl,
                    'buttonLabel' => Language::get('Check application results'),
                    'headerTitle' => self::$cfg->school_name
                ]);
                $result['email'] = $error === '' || $error === true || $error === null ? true : (string) $error;
            } catch (\Throwable $e) {
                $result['email'] = $e->getMessage();
            }
        }

        if (!empty(self::$cfg->enroll_notify_sms) && !empty($enroll->phone)) {
            // ตัวส่ง SMS คืนค่าว่าง (= สำเร็จ) เมื่อยังไม่ได้ตั้งบัญชี ต้องตรวจเองก่อน
            // ไม่งั้นจะรายงานว่าส่งแล้วทั้งที่ไม่ได้ส่ง
            if (empty(self::$cfg->sms_username) || empty(self::$cfg->sms_password)) {
                $result['sms'] = 'SMS is not configured';
            } else {
                try {
                    $message = Language::replace('Application received. Applicant ID :no. Check results or print: :url', [
                        ':no' => $enroll->enroll_no,
                        ':url' => $resultUrl
                    ]);
                    $error = \Gcms\Sms::send($enroll->phone, trim(self::$cfg->school_name.' '.$message));
                    $result['sms'] = $error === '' ? true : (string) $error;
                } catch (\Throwable $e) {
                    $result['sms'] = $e->getMessage();
                }
            }
        }

        if (is_string($result['email']) || is_string($result['sms'])) {
            \Index\Log\Model::add((int) $enroll->id, 'enroll', 'Notify', 'Send application link failed: '
                .json_encode(array_filter($result, 'is_string'), JSON_UNESCAPED_UNICODE), 0);
        }

        return $result;
    }

    /**
     * ค่าที่ใช้แทนในแม่แบบ (%NAME% %ENROLL_NO% %LEVEL% %SCHOOL% %RESULT_URL% %PRINT_URL%)
     *
     * @param object $enroll
     *
     * @return array
     */
    public static function variables($enroll)
    {
        $levels = \Enroll\Level\Model::toArray();

        return [
            'NAME' => Language::get('TITLES', [], $enroll->title).$enroll->name,
            'ENROLL_NO' => $enroll->enroll_no,
            'LEVEL' => $levels[$enroll->level] ?? '',
            'SCHOOL' => self::$cfg->school_name,
            'WEBTITLE' => self::$cfg->school_name ?: (self::$cfg->web_title ?? ''),
            'RESULT_URL' => WEB_URL.'enroll-result?id='.$enroll->link,
            'PRINT_URL' => WEB_URL.'api/enroll/printform?id='.$enroll->link
        ];
    }

    /**
     * แม่แบบเริ่มต้น ใช้เมื่อยังไม่มีแม่แบบ enroll_link ในฐานข้อมูล
     * (EmailTemplate::get() ใช้แม่แบบที่ลงทะเบียนในโค้ดก่อนฐานข้อมูล จึงลงทะเบียนเฉพาะตอนที่ไม่มี)
     *
     * @return void
     */
    protected static function registerTemplate()
    {
        if (\Gcms\EmailTemplate::get(self::EMAIL_TEMPLATE, Language::name()) !== null
            || \Gcms\EmailTemplate::get(self::EMAIL_TEMPLATE) !== null) {
            return;
        }
        \Gcms\EmailTemplate::register(self::EMAIL_TEMPLATE, [
            'subject' => Language::get('Applicant ID').' %ENROLL_NO% - %SCHOOL%',
            'detail' => '<p>%NAME%</p>'
                .'<p>'.Language::get('Your application has been received.').'</p>'
                .'<p>'.Language::get('Applicant ID').': <b>%ENROLL_NO%</b><br>'
                .Language::get('Education level').': %LEVEL%</p>'
                .'<p>'.Language::get('Use the link below to check the result, print or edit your application. Keep this link private.').'</p>'
                .'<p><a href="%RESULT_URL%">%RESULT_URL%</a></p>'
        ]);
    }
}
