<?php
/**
 * @filesource modules/enroll/views/settings.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Settings;

use Kotchasan\Date;
use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=enroll-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ตั้งค่าโมดูล
     *
     * @return string
     */
    public function render()
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/enroll/model/settings/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Module settings}'
        ));
        // school_name
        $fieldset->add('text', array(
            'id' => 'school_name',
            'labelClass' => 'g-input icon-office',
            'itemClass' => 'item',
            'label' => '{LNG_School name}',
            'value' => isset(self::$cfg->school_name) ? self::$cfg->school_name : ''
        ));
        // enroll_study_plan_count
        $fieldset->add('number', array(
            'id' => 'enroll_study_plan_count',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'item',
            'label' => '{LNG_Study plan}',
            'comment' => '{LNG_Number of study plans that can be selected}',
            'value' => isset(self::$cfg->enroll_study_plan_count) ? self::$cfg->enroll_study_plan_count : 1
        ));
        // enroll_w
        $fieldset->add('number', array(
            'id' => 'enroll_w',
            'labelClass' => 'g-input icon-width',
            'itemClass' => 'item',
            'label' => '{LNG_Size of} {LNG_Image} ({LNG_Width})',
            'comment' => '{LNG_Image size is in pixels} ({LNG_resized automatically})',
            'value' => isset(self::$cfg->enroll_w) ? self::$cfg->enroll_w : 600
        ));
        // enroll_csv_language
        $fieldset->add('select', array(
            'id' => 'enroll_csv_language',
            'labelClass' => 'g-input icon-excel',
            'itemClass' => 'item',
            'label' => '{LNG_Export}',
            'comment' => '{LNG_CSV file language encoding}',
            'options' => Language::get('CSV_LANGUAGES'),
            'value' => isset(self::$cfg->enroll_csv_language) ? self::$cfg->enroll_csv_language : 'UTF-8'
        ));
        // enroll_country
        $fieldset->add('select', array(
            'id' => 'enroll_country',
            'labelClass' => 'g-input icon-world',
            'itemClass' => 'item',
            'label' => '{LNG_Country}',
            'comment' => '{LNG_Country for province selection information}',
            'options' => Language::get('COUNTRIES'),
            'value' => isset(self::$cfg->enroll_country) ? self::$cfg->enroll_country : 'TH'
        ));
        // enroll_editable
        $fieldset->add('checkboxgroups', array(
            'id' => 'enroll_editable',
            'labelClass' => 'g-input icon-edit',
            'itemClass' => 'item',
            'label' => '{LNG_Editable}',
            'comment' => '{LNG_Applicants can edit the registration form in the state of their choice}',
            'options' => Language::get('REGISTER_STATUS'),
            'value' => isset(self::$cfg->enroll_editable) ? self::$cfg->enroll_editable : array(0, 2)
        ));
        $comment = '{LNG_The document number prefix, such as %Y%M, is replaced with the year and month. When the prefix changes (New month starts) The number will count to 1 again.}';
        $comment .= ', {LNG_%s will be replaced with the 2-digit academic year and level ID.}';
        $comment .= ', {LNG_Number such as %04d (%04d means 4 digits, maximum 11 digits)}';
        $groups = $fieldset->add('groups', array(
            'comment' => $comment
        ));
        // enroll_prefix
        $groups->add('text', array(
            'id' => 'enroll_prefix',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width50',
            'label' => '{LNG_Prefix}',
            'placeholder' => 'E%s',
            'value' => isset(self::$cfg->enroll_prefix) ? self::$cfg->enroll_prefix : ''
        ));
        // enroll_no
        $groups->add('text', array(
            'id' => 'enroll_no',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width50',
            'label' => '{LNG_Applicant ID}',
            'placeholder' => '%04d, E%s%04d',
            'value' => isset(self::$cfg->enroll_no) ? self::$cfg->enroll_no : '%04d'
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Recruitment}'
        ));
        // school_year
        $fieldset->add('number', array(
            'id' => 'school_year',
            'labelClass' => 'g-input icon-event',
            'itemClass' => 'item',
            'label' => '{LNG_School year}',
            'value' => isset(self::$cfg->school_year) ? self::$cfg->school_year : Date::format('Y')
        ));
        $groups = $fieldset->add('groups', array(
            'comment' => '{LNG_Date of application opening-closing}'
        ));
        // enroll_begin
        $groups->add('datetime', array(
            'id' => 'enroll_begin',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'label' => '{LNG_from}',
            'value' => empty(self::$cfg->enroll_begin) ? null : date('Y-m-d H:i', self::$cfg->enroll_begin)
        ));
        // enroll_end
        $groups->add('datetime', array(
            'id' => 'enroll_end',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'label' => '{LNG_to}',
            'value' => empty(self::$cfg->enroll_end) ? null : date('Y-m-d H:i', self::$cfg->enroll_end)
        ));
        $fieldset->add('button', array(
            'id' => 'enroll_reset',
            'itemClass' => 'item',
            'labelClass' => 'g-input',
            'class' => 'red button wide center icon-reset',
            'label' => '&nbsp;',
            'value' => '{LNG_Reset database}'
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
        $form->script('initEnrollSettings();');
        // คืนค่า HTML
        return $form->render();
    }
}
