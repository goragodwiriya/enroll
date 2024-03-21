<?php
/**
 * @filesource modules/enroll/models/result.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Result;

use Kotchasan\Database\Sql;

/**
 * module=enroll-result
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลสมาชิกที่ $link
     * ไม่ได้ระบุ $link คืนค่า true
     * คืนค่าข้อมูล object ไม่พบคืนค่า false
     *
     * @param string $link
     *
     * @return object|bool
     */
    public static function get($link)
    {
        if (preg_match('/[0-9a-z]{32,32}/', $link)) {
            $enroll = static::createQuery()->from('enroll')->where(array('link', $link))->first();
            if ($enroll) {
                // ลงทะเบียนผู้สมัคร
                $_SESSION['enroll'] = array(
                    'id' => $enroll->link,
                    'permission' => [],
                    'status' => 0
                );
            }
        } else {
            $enroll = false;
        }
        return $enroll;
    }

    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param string $link
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($id)
    {
        return static::createQuery()
            ->select('E.create_date', 'E.name', 'E.id', 'E.id_card', 'E.phone', 'E.level', Sql::GROUP_CONCAT('Q.value', 'plan', ',', null, 'Q.no'),
                'E.link', 'E.result_plan', 'E.result_status', 'E.enroll_no')
            ->from('enroll E')
            ->join('enroll_plan Q', 'LEFT', array('Q.enroll_id', 'E.id'))
            ->where(array('E.id', $id))
            ->groupBy('E.id');
    }
}
