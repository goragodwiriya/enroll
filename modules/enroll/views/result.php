<?php
/**
 * @filesource modules/enroll/views/result.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Result;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=enroll-result
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * แผนการเรียน
     *
     * @var array
     */
    private $planing;
    /**
     * ระดับชั้น
     *
     * @var array
     */
    private $level;
    /**
     * ผลการสมัคร
     *
     * @var array
     */
    private $status;
    /**
     * ผลการสมัคร
     *
     * @param Request $request
     * @param object   $enroll
     *
     * @return string
     */
    public function render(Request $request, $enroll)
    {
        $this->planing = \Enroll\Plan\Model::toSelect($enroll->level);
        $this->level = \Enroll\Level\Model::toSelect();
        $this->status = Language::get('REGISTER_STATUS');
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Enroll\Result\Model::toDataTable($enroll->id),
            /* ไม่ต้องแสดง caption */
            'showCaption' => false,
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('link', 'result_plan'),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'enroll_no' => array(
                    'text' => '{LNG_Applicant ID}'
                ),
                'create_date' => array(
                    'text' => '{LNG_Date}'
                ),
                'name' => array(
                    'text' => '{LNG_Name}'
                ),
                'id_card' => array(
                    'text' => '{LNG_Identification No.}',
                    'class' => 'center'
                ),
                'id' => array(
                    'text' => ''
                ),
                'phone' => array(
                    'text' => '{LNG_Phone}',
                    'class' => 'center'
                ),
                'level' => array(
                    'text' => '{LNG_Education level}',
                    'class' => 'center'
                ),
                'plan' => array(
                    'text' => '{LNG_Study plan}',
                    'class' => 'center'
                ),
                'result_status' => array(
                    'text' => '{LNG_Result}',
                    'class' => 'center'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'id_card' => array(
                    'class' => 'center'
                ),
                'phone' => array(
                    'class' => 'center'
                ),
                'level' => array(
                    'class' => 'center'
                ),
                'plan' => array(
                    'class' => 'center'
                ),
                'result_status' => array(
                    'class' => 'center'
                )
            ),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                array(
                    'class' => 'icon-print button print',
                    'href' => WEB_URL.'export.php?module=enroll-export&amp;typ=print&amp;id=:link',
                    'target' => 'export',
                    'text' => '{LNG_Print}'
                ),
                array(
                    'class' => 'icon-edit button green',
                    'href' => WEB_URL.'index.php?module=enroll-register&amp;id=:link',
                    'text' => '{LNG_Edit}'
                )
            )
        ));
        if (file_exists(ROOT_PATH.DATA_FOLDER.'pages/result_'.LANGUAGE.'.html')) {
            // ภาษาที่เลือก
            $content = file_get_contents(ROOT_PATH.DATA_FOLDER.'pages/result_'.LANGUAGE.'.html');
        } elseif (file_exists(ROOT_PATH.self::$cfg->skin.'/result.html')) {
            // เนื้อหาเริ่มต้น
            $content = file_get_contents(ROOT_PATH.self::$cfg->skin.'/result.html');
        } else {
            // หน้าเปล่าๆ
            $content = '<h1 class="center">Topic</h1>Xxxxxxx Yyyyyyy';
        }
        // คืนค่า HTML
        return $table->render().'<div class="dashboard clear">'.$content.'</div>';
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
        $item['enroll_no'] = '<img style="max-width:none" src="data:image/png;base64,'.base64_encode(\Kotchasan\Barcode::create($item['enroll_no'], 40, 9)->toPng()).'">';
        $item['phone'] = '<a href="tel:'.$item['phone'].'">'.$item['phone'].'</a>';
        $thumb = is_file(ROOT_PATH.DATA_FOLDER.'enroll/'.$item['id'].'.jpg') ? WEB_URL.DATA_FOLDER.'enroll/'.$item['id'].'.jpg?'.time() : WEB_URL.'skin/img/noicon.jpg';
        $item['id'] = '<img src="'.$thumb.'" style="max-height:32px;max-width:50px" alt=thumbnail>';
        $item['create_date'] = Date::format($item['create_date'], 'd M Y');
        $item['level'] = isset($this->level[$item['level']]) ? $this->level[$item['level']] : '';
        $item['plan'] = $this->plan($item['plan'], $item['result_plan'], $item['result_status']);
        $item['result_status'] = isset($this->status[$item['result_status']]) ? '<span class=term'.$item['result_status'].'>'.$this->status[$item['result_status']].'</span>' : '';
        return $item;
    }

    /**
     * คืนค่าแผนการเรียน
     *
     * @param string $plan
     * @param int $result_plan
     * @param int $result_status
     *
     * @return string
     */
    public function plan($plan, $result_plan, $result_status)
    {
        if ($result_status == 1 && isset($this->planing[$result_plan])) {
            return $this->planing[$result_plan];
        } else {
            $result = [];
            if ($plan !== null) {
                foreach (explode(',', $plan) as $i) {
                    if (isset($this->planing[$i])) {
                        $result[$i] = $this->planing[$i];
                    }
                }
            }
            return implode(', ', $result);
        }
    }
}
