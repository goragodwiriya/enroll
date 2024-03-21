<?php
/**
 * @filesource modules/index/models/autocomplete.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Autocomplete;

use Kotchasan\Http\Request;

/**
 * คลาสสำหรับการโหลด ตำบล อำเภอ จังหวัด.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ประมวลผลค่าที่ส่งมา และส่งค่ากลับเป็น JSON
     *
     * @param array $where
     *
     * @return JSON
     */
    public function execute($where, $nodistrict)
    {
        $query = static::createQuery()
            ->from('province P')
            ->join('amphur A', 'INNER', array('A.province_id', 'P.id'))
            ->where($where)
            ->limit(50)
            ->cacheOn()
            ->toArray();
        $select = array('P.province', 'P.id provinceID', 'A.amphur', 'A.id amphurID');
        if (!$nodistrict) {
            $query->join('district D', 'INNER', array('D.amphur_id', 'A.id'));
            $select[] = 'D.district';
            $select[] = 'D.id districtID';
        }
        $result = $query->select($select)->execute();
        // คืนค่า JSON
        if (!empty($result)) {
            echo json_encode($result);
        }
    }

    /**
     * คืนค่า ตำบล อำเภอ จังหวัด จาก อำเภอ
     *
     * @param Request $request
     *
     * @return JSON
     */
    public function amphur(Request $request)
    {
        // session, referer
        if ($request->initSession() && $request->isReferer()) {
            try {
                // ข้อความค้นหาที่ส่งมา
                $value = $request->post('amphur')->topic();
                $country = $request->get('country')->filter('A-Z');
                $nodistrict = $request->get('nodistrict')->toInt();
                if ($value != '') {
                    $this->execute(array(
                        array('A.country', $country),
                        array('A.amphur', 'LIKE', $value.'%')
                    ), $nodistrict);
                }
            } catch (\Kotchasan\InputItemException $e) {
            }
        }
    }

    /**
     * คืนค่า ตำบล อำเภอ จังหวัด จาก ตำบล.
     *
     * @param Request $request
     *
     * @return JSON
     */
    public function district(Request $request)
    {
        // session, referer
        if ($request->initSession() && $request->isReferer()) {
            try {
                // ข้อความค้นหาที่ส่งมา
                $value = $request->post('district')->topic();
                $country = $request->get('country')->filter('A-Z');
                if ($value != '') {
                    $this->execute(array(
                        array('D.country', $country),
                        array('D.district', 'LIKE', $value.'%')
                    ), 0);
                }
            } catch (\Kotchasan\InputItemException $e) {
            }
        }
    }

    /**
     * คืนค่า ตำบล อำเภอ จังหวัด จาก จังหวัด.
     *
     * @param Request $request
     *
     * @return JSON
     */
    public function province(Request $request)
    {
        // session, referer
        if ($request->initSession() && $request->isReferer()) {
            try {
                // ข้อความค้นหาที่ส่งมา
                $value = $request->post('province')->topic();
                $country = $request->get('country')->filter('A-Z');
                $nodistrict = $request->get('nodistrict')->toInt();
                if ($value != '') {
                    $this->execute(array(
                        array('P.country', $country),
                        array('P.province', 'LIKE', $value.'%')
                    ), $nodistrict);
                }
            } catch (\Kotchasan\InputItemException $e) {
            }
        }
    }
}
