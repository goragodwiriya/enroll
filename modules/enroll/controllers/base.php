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
}
