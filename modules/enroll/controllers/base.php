<?php
/**
 * @filesource modules/enroll/controllers/base.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Base;

use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * คลาสฐานของ API ในโมดูล enroll (ชนิดที่ไม่ใช่ตาราง)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Api
{
    /**
     * เลือกภาษาของ response
     *
     * @param Request $request
     *
     * @return string
     */
    protected function initLanguage(Request $request)
    {
        return self::resolveLanguage($request);
    }

    /**
     * ภาษาที่ควรใช้ตอบคำขอนี้
     *
     * ApiController::initLanguage() ตกไปที่ 'en' เมื่อคำขอไม่ได้ระบุภาษามาเลย
     * ซึ่งเหมาะกับ API ที่ client เป็นโปรแกรม แต่โมดูลนี้มีหน้าที่คนอ่านโดยตรง
     * (ใบสมัครสำหรับพิมพ์, ไฟล์ CSV) และเปิดจาก URL ตรง ๆ ได้
     * ไม่ระบุภาษามา จึงควรใช้ภาษาของเว็บ ไม่ใช่ภาษาอังกฤษ
     *
     * @param Request $request
     *
     * @return string ชื่อภาษาที่เลือก
     */
    public static function resolveLanguage(Request $request)
    {
        $lang = strtolower($request->request('lang')->filter('a-zA-Z_-'));
        if ($lang === '') {
            // ภาษาที่ผู้เข้าชมเลือกไว้ (ปุ่มเปลี่ยนภาษาตั้งคุกกี้ my_lang) ลำดับเดียวกับ ApiController
            $cookies = $request->getCookieParams();
            $lang = strtolower(preg_replace('/[^a-zA-Z_-]/', '', (string) ($cookies['my_lang'] ?? '')));
        }
        if ($lang === '') {
            $acceptable = $request->getAcceptableLanguages();
            if (!empty($acceptable)) {
                $lang = strtolower($acceptable[0]);
            }
        }
        if ($lang === '') {
            return \Kotchasan\Language::name();
        }
        foreach (['-', '_'] as $separator) {
            if (($pos = strpos($lang, $separator)) !== false) {
                $lang = substr($lang, 0, $pos);
            }
        }
        \Kotchasan\Language::setName($lang);

        return $lang;
    }

    /**
     * URL โลโก้ของโรงเรียน หรือ null ถ้ายังไม่ได้อัปโหลด
     * (storedImage() หาได้ทุกนามสกุล ไม่ผูกกับ stored_img_type ปัจจุบัน)
     *
     * @return string|null
     */
    public static function logoUrl()
    {
        $file = self::storedImage('images/logo');

        return $file === null ? null : WEB_URL.DATA_FOLDER.$file.'?'.filemtime(ROOT_PATH.DATA_FOLDER.$file);
    }

    /**
     * หน้าที่ผู้ดูแลแก้ไขเนื้อหาได้ (ชื่อไฟล์เดียวกับระบบเดิม ข้อมูลที่ย้ายมาใช้ได้ทันที)
     * dashboard = หน้าแรก/รายละเอียดการรับสมัคร, result = ข้อความในหน้าผลการสมัคร
     *
     * @return array [src => ชื่อที่แสดง]
     */
    public static function pages()
    {
        return [
            'dashboard' => '{LNG_Home}',
            'result' => '{LNG_Result}',
            // ข้อความที่ผู้สมัครต้องยินยอมก่อนส่งใบสมัคร (PDPA) ระบบเดิมไม่มี
            'privacy' => '{LNG_Privacy Policy}'
        ];
    }

    /**
     * เนื้อหาของหน้าที่แก้ไขได้ datas/pages/<src>_<ภาษา>.html
     * ไม่มีภาษาที่ใช้อยู่ ใช้ภาษาไทย ไม่มีทั้งคู่ ใช้เนื้อหาเริ่มต้นของโมดูล (ถ้ามี)
     *
     * @param string $src
     * @param string|null $language null = ภาษาที่ใช้อยู่ แล้วตามด้วยภาษาไทย
     *
     * @return string
     */
    public static function pageContent($src, $language = null)
    {
        $languages = $language === null ? [\Kotchasan\Language::name(), 'th'] : [$language];
        foreach ($languages as $lng) {
            $file = ROOT_PATH.DATA_FOLDER.'pages/'.$src.'_'.$lng.'.html';
            if (is_file($file)) {
                return (string) file_get_contents($file);
            }
        }
        $default = ROOT_PATH.'templates/enroll/pages/'.$src.'.html';

        return is_file($default) ? (string) file_get_contents($default) : '';
    }

    /**
     * เหตุที่ไม่รับใบสมัครใหม่ หรือ null ถ้ารับอยู่
     * นอกช่วงรับสมัคร หรือรับครบทุกแผนการเรียนแล้ว (ใบเดิมยังแก้ไขได้ในช่วงรับสมัคร)
     *
     * @return string|null
     */
    public static function closedReason()
    {
        if (!self::isOpen()) {
            return \Kotchasan\Language::get('Applications are not open at this time');
        }
        if (\Enroll\Plan\Model::allFull()) {
            return \Kotchasan\Language::get('All study plans are full');
        }

        return null;
    }

    /**
     * ช่วงเวลาที่เปิดรับสมัคร ไม่ได้กำหนดวันไว้ = เปิดตลอด
     *
     * @return bool
     */
    public static function isOpen()
    {
        if (empty(self::$cfg->enroll_begin) || empty(self::$cfg->enroll_end)) {
            return true;
        }
        $today = time();

        return $today >= self::$cfg->enroll_begin && $today <= self::$cfg->enroll_end;
    }

    /**
     * ข้อมูลโรงเรียนสำหรับส่วนหัวและเมนูของหน้าผู้สมัคร
     * is_open คุมการแสดงเมนู/ปุ่มสมัครเรียน (ระบบเดิมซ่อนเมนูสมัครเมื่อปิดรับสมัคร)
     *
     * @return array
     */
    protected static function schoolInfo()
    {
        $period = '';
        if (!empty(self::$cfg->enroll_begin) && !empty(self::$cfg->enroll_end)) {
            $period = Date::format((int) self::$cfg->enroll_begin, 'd M Y').' - '.Date::format((int) self::$cfg->enroll_end, 'd M Y');
        }

        $closed = self::closedReason();

        return [
            // ยังไม่ได้ตั้งชื่อโรงเรียน ใช้ชื่อเว็บแทน หัวเว็บจะได้ไม่ว่าง
            'school_name' => (string) (self::$cfg->school_name ?: strip_tags((string) (self::$cfg->web_title ?? ''))),
            'school_year' => (string) self::$cfg->school_year,
            'logo' => self::logoUrl(),
            'period' => $period,
            // รับใบสมัครใหม่อยู่หรือไม่ (ช่วงเวลา และยังมีแผนที่รับไม่ครบ) คุมเมนู/ปุ่มสมัครเรียน
            'is_open' => $closed === null,
            'closed_message' => (string) $closed,
            'is_announce' => !empty(self::$cfg->enroll_announce)
        ];
    }
}
