<?php
/**
 * @filesource modules/enroll/views/plan.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Plan;

use Kotchasan\ArrayTool;
use Kotchasan\DataTable;
use Kotchasan\Form;
use Kotchasan\Html;
use Kotchasan\Http\Request;

/**
 * module=enroll-plan
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * Plan
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // level
        $levels = \Enroll\Level\Model::toSelect();
        // ไม่มี level ใช้รายการแรก
        $level = $request->request('level', ArrayTool::getFirstKey($levels))->toInt();
        // form
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/enroll/model/plan/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Details of} {LNG_Study plan}'
        ));
        // level
        $fieldset->add('select', array(
            'id' => 'level',
            'label' => '{LNG_Education level}',
            'labelClass' => 'g-input icon-elearning',
            'itemClass' => 'item',
            'options' => $levels,
            'value' => $level
        ));
        // ตารางหมวดหมู่
        $table = new DataTable(array(
            /* ข้อมูลใส่ลงในตาราง */
            'datas' => \Enroll\Plan\Model::toDataTable($level),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('id'),
            /* กำหนดให้ input ตัวแรก (id) รับค่าเป็นตัวเลขเท่านั้น */
            'onInitRow' => 'initFirstRowNumberOnly',
            'border' => true,
            'responsive' => true,
            'pmButton' => true,
            'showCaption' => false,
            'headers' => array(
                'category_id' => array(
                    'text' => '{LNG_ID}'
                ),
                'topic' => array(
                    'text' => '{LNG_Detail}'
                )
            )
        ));
        $fieldset->add('div', array(
            'class' => 'item',
            'innerHTML' => $table->render()
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ));
        // Javascript
        $form->script('initEnrollPlan();');
        // คืนค่า HTML

        return $form->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว.
     *
     * @param array  $item ข้อมูลแถว
     * @param int    $o    ID ของข้อมูล
     * @param object $prop กำหนด properties ของ TR
     *
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $item['category_id'] = Form::text(array(
            'name' => 'category_id[]',
            'labelClass' => 'g-input',
            'size' => 2,
            'value' => $item['category_id']
        ))->render();
        $item['topic'] = Form::text(array(
            'name' => 'topic[]',
            'labelClass' => 'g-input',
            'maxlength' => 128,
            'value' => $item['topic']
        ))->render();

        return $item;
    }
}
