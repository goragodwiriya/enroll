<?php
/**
 * @filesource modules/enroll/controllers/tablebase.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Tablebase;

use Kotchasan\Http\Request;

/**
 * คลาสฐานของ API ชนิดตารางในโมดูล enroll
 *
 * แยกจาก \Enroll\Base\Controller เพราะต้องสืบทอด \Gcms\Table
 * แต่ใช้กติกาเรื่องภาษาชุดเดียวกัน (\Gcms\Table เรียก initLanguage() เองภายใน)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
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
        return \Enroll\Base\Controller::resolveLanguage($request);
    }
}
