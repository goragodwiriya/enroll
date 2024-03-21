<?php
/**
 * @filesource modules/enroll/models/index.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Index;

use Kotchasan\Database\Sql;

/**
 * module=enroll-index
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = array(
            array('E.level', $params['level'])
        );
        return static::createQuery()
            ->select('E.create_date', 'E.name', Sql::GROUP_CONCAT('Q.value', 'plan', ',', null, 'Q.no'), 'E.result_plan')
            ->from('enroll E')
            ->join('enroll_plan Q', 'LEFT', array('Q.enroll_id', 'E.id'))
            ->where($where)
            ->groupBy('E.id');
    }
}
