<?php
/**
 * @filesource modules/enroll/views/index.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Index;

use Kotchasan;
use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * module=enroll-index
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * แผนการเรียนตามระดับที่เลือก
     *
     * @var array
     */
    private $planing;

    /**
     * ตารางรายชื่อผู้ลงทะเบียน
     *
     * @param Request $request
     * @param array   $params
     *
     * @return string
     */
    public function render(Request $request, $params)
    {
        $this->planing = \Enroll\Plan\Model::toSelect($params['level']);
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Enroll\Index\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('enrollIndex_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => 'create_date desc',
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('name'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('result_plan'),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'create_date' => array(
                    'text' => '{LNG_Date}'
                ),
                'name' => array(
                    'text' => '{LNG_Name}'
                ),
                'plan' => array(
                    'text' => '{LNG_Study plan}'
                ),
                'result_status' => array(
                    'text' => '{LNG_Result}',
                    'class' => 'center'
                )
            )
        ));
        // save cookie
        setcookie('enrollIndex_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML
        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array  $item ข้อมูลแถว
     * @param int    $o    ID ของข้อมูล
     * @param object $prop กำหนด properties ของ TR
     *
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $item['create_date'] = Date::format($item['create_date'], 'd M Y');
        $item['plan'] = $this->plan($item['plan']);
        return $item;
    }

    /**
     * คืนค่าแผนการเรียน
     *
     * @param string $plan
     *
     * @return string
     */
    public function plan($plan)
    {
        $result = [];
        foreach (explode(',', $plan) as $i) {
            if (isset($this->planing[$i])) {
                $result[$i] = $this->planing[$i];
            }
        }
        return implode(', ', $result);
    }
}
