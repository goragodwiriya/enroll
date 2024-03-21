<?php
/**
 * @filesource modules/enroll/views/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Setup;

use Kotchasan;
use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=enroll-setup
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
    private $plan;
    /**
     * เกรด
     *
     * @var array
     */
    private $academic_results;
    /**
     * @var string
     */
    private $result;
    /**
     * ผลการสมัคร
     *
     * @var string
     */
    private $status;
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
        $this->plan = \Enroll\Plan\Model::toSelect($params['level']);
        $this->academic_results = Language::get('ACADEMIC_RESULTS', []);
        $register_status = Language::get('REGISTER_STATUS');
        $this->result = $request->request('result')->topic();
        if (!isset($this->academic_results[$this->result])) {
            $this->result = Kotchasan\ArrayTool::getFirstKey($this->academic_results);
        }
        $this->status = '';
        foreach ($register_status as $k => $v) {
            $this->status .= '<option value='.$k.'>'.$v.'</option>';
        }
        $plan_count = [];
        for ($i = 1; $i <= self::$cfg->enroll_study_plan_count; $i++) {
            $plan_count[$i] = '{LNG_Study plan} '.$i;
        }
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Enroll\Setup\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('enrollSetup_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('enrollSetup_sort', 'create_date desc')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('name', 'enroll_no'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/enroll/model/setup/action',
            'actionCallback' => 'dataTableActionCallback',
            'actions' => array(
                array(
                    'id' => 'action',
                    'class' => 'ok',
                    'text' => '{LNG_With selected}',
                    'options' => array(
                        'delete' => '{LNG_Delete}'
                    )
                )
            ),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('link', 'id', 'plan'),
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => array(
                array(
                    'name' => 'level',
                    'text' => '{LNG_Education level}',
                    'options' => $params['levels'],
                    'value' => $params['level']
                ),
                array(
                    'name' => 'result',
                    'text' => '{LNG_Academic result}',
                    'options' => $this->academic_results,
                    'value' => $this->result
                ),
                array(
                    'name' => 'plan',
                    'text' => '{LNG_Study plan}',
                    'options' => array(0 => '{LNG_all items}') + $this->plan,
                    'value' => $params['plan']
                ),
                array(
                    'name' => 'status',
                    'text' => '{LNG_Result}',
                    'options' => array(-1 => '{LNG_all items}') + $register_status,
                    'value' => $params['status']
                )
            ),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'enroll_no' => array(
                    'text' => '{LNG_Applicant ID}',
                    'sort' => 'enroll_no'
                ),
                'create_date' => array(
                    'text' => '{LNG_Date}',
                    'sort' => 'create_date'
                ),
                'name' => array(
                    'text' => '{LNG_Name}',
                    'sort' => 'name'
                ),
                'academic_results' => array(
                    'text' => '{LNG_Academic result}',
                    'class' => 'center',
                    'sort' => 'academic_results'
                ),
                'result_plan' => array(
                    'text' => '{LNG_Study plan}',
                    'class' => 'center',
                    'sort' => 'result_plan'
                ),
                'result_status' => array(
                    'text' => '{LNG_Result}',
                    'class' => 'center',
                    'sort' => 'result_status'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'create_date' => array(
                    'class' => 'nowrap'
                ),
                'name' => array(
                    'class' => 'nowrap'
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
                    'href' => $uri->createBackUri(array('module' => 'enroll-register', 'id' => ':id')),
                    'text' => '{LNG_Edit}'
                )
            )
        ));
        $params['sort'] = $table->sort;
        $table->actions[] = array(
            'class' => 'button icon-excel orange',
            'text' => '{LNG_Download} CSV ('.Language::get('CSV_LANGUAGES', '', self::$cfg->enroll_csv_language).')',
            'href' => 'export.php?module=enroll-export&amp;typ=csv&amp;'.http_build_query($params),
            'target' => 'export'
        );
        // save cookie
        setcookie('enrollSetup_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('enrollSetup_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
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
        $item['enroll_no'] = '<img style="max-width:none" src="data:image/png;base64,'.base64_encode(\Kotchasan\Barcode::create($item['enroll_no'], 40, 9)->toPng()).'">';
        $item['create_date'] = Date::format($item['create_date'], 'd M Y');
        $academic_results = '';
        foreach (json_decode($item['academic_results'], true) as $k => $v) {
            $sel = $this->result == $k ? ' selected' : '';
            $academic_results .= '<option value="'.$k.'"'.$sel.'>'.$this->academic_results[$k].' : '.$v.'</option>';
        }
        $item['academic_results'] = '<select>'.$academic_results.'</select>';
        $result_plan = array(
            '<option value=0>{LNG_Please select}</option>'
        );
        if ($item['plan'] !== null) {
            foreach (explode(',', $item['plan']) as $plan) {
                $sel = $item['result_plan'] == $plan ? ' selected' : '';
                $result_plan[$plan] = '<option value='.$plan.$sel.'>'.$this->plan[$plan].'</option>';
            }
        }
        $item['result_plan'] = '<label><select id=plan_'.$item['id'].'>'.implode('', $result_plan).'</select></label>';
        $item['result_status'] = '<label><select id=status_'.$item['id'].'>'.str_replace('value='.$item['result_status'].'>', 'value='.$item['result_status'].' selected>', $this->status).'</select></label>';
        return $item;
    }
}
