<?php
/**
 * @filesource modules/enroll/models/export.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Export;

use Kotchasan\Database\Sql;

/**
 * export.php?module=enroll-export&typ=csv|print
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ส่งออกข้อมูล CSV
     *
     * @param array $params
     *
     * @return array
     */
    public static function csv($params)
    {
        $where = array(
            array('E.level', $params['level'])
        );
        if ($params['plan'] > 0) {
            $where[] = array('E.result_plan', $params['plan']);
        }
        if ($params['status'] > 0) {
            $where[] = array('E.result_status', $params['status']);
        }
        return \Kotchasan\Model::createQuery()
            ->select('E.level', Sql::GROUP_CONCAT('Q.value', 'plan', ',', null, 'Q.no'), 'E.title', 'E.name',
                'E.id_card', 'E.birthday', 'E.phone', 'E.email', 'E.nationality', 'E.religion',
                'E.address', 'D.district', 'A.amphur', 'P.province', 'E.zipcode', 'E.parent',
                'E.original_school', 'E.academic_results', 'E.result_plan', 'E.result_status')
            ->from('enroll E')
            ->join('province P', 'LEFT', array('P.id', 'E.provinceID'))
            ->join('amphur A', 'LEFT', array(array('A.id', 'E.amphurID'), array('A.province_id', 'P.id')))
            ->join('district D', 'LEFT', array(array('D.id', 'E.districtID'), array('D.amphur_id', 'A.id')))
            ->join('enroll_plan Q', 'LEFT', array('Q.enroll_id', 'E.id'))
            ->where($where)
            ->groupBy('E.id')
            ->order($params['sort'])
            ->cacheOn()
            ->execute();
    }

    /**
     * อ่านข้อมูลที่ $id
     * คืนค่าข้อมูล object ไม่พบคืนค่า false
     *
     * @param string $link
     *
     * @return object|bool
     */
    public static function get($link)
    {
        $q1 = static::createQuery()
            ->select('P.enroll_id', 'C.topic', 'C.sub_category')
            ->from('enroll_plan P')
            ->join('category C', 'INNER', array(array('C.category_id', 'P.value'), array('C.type', 'enroll')))
            ->groupBy('P.enroll_id', 'P.no', 'C.sub_category');
        return static::createQuery()
            ->from('enroll E')
            ->join('province P', 'LEFT', array('P.id', 'E.provinceID'))
            ->join('amphur A', 'LEFT', array(array('A.country', 'P.country'), array('A.id', 'E.amphurID'), array('A.province_id', 'P.id')))
            ->join('district D', 'LEFT', array(array('D.country', 'P.country'), array('D.id', 'E.districtID'), array('D.amphur_id', 'A.id')))
            ->join('category L', 'LEFT', array(array('L.type', 'enroll'), array('L.sub_category', 0), array('L.category_id', 'E.level')))
            ->join(array($q1, 'Q'), 'LEFT', array(array('Q.enroll_id', 'E.id'), array('Q.sub_category', 'E.level')))
            ->where(array('E.link', $link))
            ->first('E.*', 'P.province', 'A.amphur', 'D.district', 'L.topic level', Sql::GROUP_CONCAT('Q.topic', 'plan'));
    }
}
