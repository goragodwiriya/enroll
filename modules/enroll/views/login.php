<?php
/**
 * @filesource modules/enroll/views/login.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Login;

use Kotchasan\Html;
use Kotchasan\Http\Request;

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
     * เข้าระบบเพื่อดูผลหรือพิมพ์
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/enroll/model/login/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Registered Information}'
        ));
        // id_card
        $fieldset->add('number', array(
            'id' => 'id_card',
            'labelClass' => 'g-input icon-profile',
            'itemClass' => 'item',
            'label' => '{LNG_Identification No.}',
            'maxlength' => 13
        ));
        // birthday
        $fieldset->add('date', array(
            'id' => 'birthday',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Birthday}',
            'value' => null
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-info',
            'value' => '{LNG_Login}'
        ));
        // คืนค่า HTML
        return $form->render();
    }
}
