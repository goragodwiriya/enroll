<?php
/**
 * @filesource modules/enroll/views/register.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Register;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Http\UploadedFile;
use Kotchasan\Language;

/**
 * module=enroll-register
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มลงทะเบียนเรียน
     *
     * @param Request $request
     * @param object $enroll
     * @param object $login
     *
     * @return string
     */
    public function render(Request $request, $enroll, $login)
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/enroll/model/register/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Enroll type}'
        ));
        // level
        $fieldset->add('select', array(
            'id' => 'register_level',
            'labelClass' => 'g-input icon-elearning',
            'itemClass' => 'item',
            'label' => '{LNG_Education level}',
            'options' => \Enroll\Level\Model::toSelect(),
            'value' => isset($enroll->level) ? $enroll->level : 1
        ));
        // plan
        $plan = \Enroll\Register\Model::plan($enroll->id);
        for ($i = 0; $i < self::$cfg->enroll_study_plan_count; $i++) {
            $v = isset($plan[$i]) ? $plan[$i] : 0;
            $fieldset->add('select', array(
                'id' => 'register_plan'.$i,
                'name' => 'register_plan['.$i.']',
                'labelClass' => 'g-input icon-menus',
                'itemClass' => 'item',
                'label' => '{LNG_Study plan} '.(self::$cfg->enroll_study_plan_count == 1 ? '' : $i + 1),
                'options' => array($v => '{LNG_Please select}'),
                'value' => $v
            ));
        }
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Registered Information}'
        ));
        $groups = $fieldset->add('groups');
        // title
        $groups->add('select', array(
            'id' => 'register_title',
            'labelClass' => 'g-input',
            'itemClass' => 'width20',
            'label' => '{LNG_Title}',
            'options' => Language::get('TITLES'),
            'value' => isset($enroll->title) ? $enroll->title : 1
        ));
        // name
        $groups->add('text', array(
            'id' => 'register_name',
            'labelClass' => 'g-input icon-customer',
            'itemClass' => 'width80',
            'label' => '{LNG_Name}',
            'maxlength' => 100,
            'value' => isset($enroll->name) ? $enroll->name : ''
        ));
        // thumbnail
        $thumb = is_file(ROOT_PATH.DATA_FOLDER.'enroll/'.$enroll->id.'.jpg') ? WEB_URL.DATA_FOLDER.'enroll/'.$enroll->id.'.jpg?'.time() : WEB_URL.'skin/img/noicon.jpg';
        $fieldset->add('file', array(
            'id' => 'thumbnail',
            'labelClass' => 'g-input icon-thumbnail',
            'itemClass' => 'item',
            'label' => '{LNG_Picture of student}',
            'comment' => Language::replace('Straight face photos Wearing a uniform, not wearing a hat and glasses, taken within 6 months, :type type only', array(':type' => 'jpg, jpeg, png')),
            'dataPreview' => 'imgPicture',
            'previewSrc' => $thumb,
            'accept' => array('jpg', 'jpeg', 'png')
        ));
        $groups = $fieldset->add('groups', array(
            'comment' => '{LNG_Enter your ID number and date of birth correctly. Used to login to edit additional information. and verification of application results}'
        ));
        // id_card
        $groups->add('number', array(
            'id' => 'register_id_card',
            'labelClass' => 'g-input icon-profile',
            'itemClass' => 'width50',
            'label' => '{LNG_Identification No.}',
            'maxlength' => 13,
            'value' => isset($enroll->id_card) ? $enroll->id_card : ''
        ));
        // birthday
        $groups->add('date', array(
            'id' => 'register_birthday',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'label' => '{LNG_Birthday}',
            'value' => isset($enroll->birthday) ? $enroll->birthday : null
        ));
        $groups = $fieldset->add('groups');
        // phone
        $groups->add('number', array(
            'id' => 'register_phone',
            'labelClass' => 'g-input icon-phone',
            'itemClass' => 'width50',
            'label' => '{LNG_Phone}',
            'maxlength' => 10,
            'value' => isset($enroll->phone) ? $enroll->phone : ''
        ));
        // email
        $groups->add('email', array(
            'id' => 'register_email',
            'labelClass' => 'g-input icon-email',
            'itemClass' => 'width50',
            'label' => '{LNG_Email}',
            'maxlength' => 255,
            'value' => isset($enroll->email) ? $enroll->email : ''
        ));
        $groups = $fieldset->add('groups');
        // nationality
        $groups->add('text', array(
            'id' => 'register_nationality',
            'labelClass' => 'g-input icon-world',
            'itemClass' => 'width50',
            'label' => '{LNG_Nationality}',
            'maxlength' => 20,
            'value' => isset($enroll->nationality) ? $enroll->nationality : ''
        ));
        // religion
        $groups->add('text', array(
            'id' => 'register_religion',
            'labelClass' => 'g-input icon-customer',
            'itemClass' => 'width50',
            'label' => '{LNG_Religion}',
            'maxlength' => 20,
            'value' => isset($enroll->religion) ? $enroll->religion : ''
        ));
        // address
        $fieldset->add('text', array(
            'id' => 'register_address',
            'labelClass' => 'g-input icon-address',
            'itemClass' => 'item',
            'label' => '{LNG_Address}',
            'maxlength' => 150,
            'value' => isset($enroll->address) ? $enroll->address : ''
        ));
        $groups = $fieldset->add('groups');
        // district
        $groups->add('text', array(
            'id' => 'register_district',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-location',
            'label' => '{LNG_District}',
            'value' => isset($enroll->district) ? $enroll->district : ''
        ));
        // amphur
        $groups->add('text', array(
            'id' => 'register_amphur',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-location',
            'label' => '{LNG_Amphur}',
            'value' => isset($enroll->amphur) ? $enroll->amphur : ''
        ));
        $groups = $fieldset->add('groups');
        // province
        $groups->add('text', array(
            'id' => 'register_province',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-location',
            'label' => '{LNG_Province}',
            'value' => isset($enroll->province) ? $enroll->province : ''
        ));
        // zipcode
        $groups->add('number', array(
            'id' => 'register_zipcode',
            'labelClass' => 'g-input icon-location',
            'itemClass' => 'width50',
            'label' => '{LNG_Zipcode}',
            'maxlength' => 5,
            'value' => isset($enroll->zipcode) ? $enroll->zipcode : ''
        ));
        // parent
        $parent_list = Language::get('PARENT_LIST', []);
        if (!empty($parent_list)) {
            if (isset($enroll->parent)) {
                $parent = json_decode($enroll->parent, true);
            } else {
                $parent = [];
            }
            $fieldset = $form->add('fieldset', array(
                'title' => '{LNG_Parent}'
            ));
            foreach ($parent_list as $key => $label) {
                $groups = $fieldset->add('groups', array(
                    'comment' => $key == 'parent' ? '{LNG_If living with someone other than the parent while studying}' : ''
                ));
                // parent
                $groups->add('text', array(
                    'id' => 'register_'.$key,
                    'itemClass' => 'width50',
                    'labelClass' => 'g-input icon-customer',
                    'label' => '{LNG_Name} '.$label,
                    'value' => empty($parent[$key]['name']) ? '' : $parent[$key]['name']
                ));
                // phone
                $groups->add('number', array(
                    'id' => 'register_'.$key.'_phone',
                    'labelClass' => 'g-input icon-phone',
                    'itemClass' => 'width50',
                    'label' => '{LNG_Phone}',
                    'maxlength' => 10,
                    'value' => empty($parent[$key]['phone']) ? '' : $parent[$key]['phone']
                ));
            }
        }
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Education qualifications used for registration}'
        ));
        $groups = $fieldset->add('groups');
        // original_school
        $groups->add('text', array(
            'id' => 'register_original_school',
            'itemClass' => 'width50',
            'labelClass' => 'g-input icon-office',
            'label' => '{LNG_Original school}',
            'value' => isset($enroll->original_school) ? $enroll->original_school : ''
        ));
        $onet_list = Language::get('ACADEMIC_RESULTS');
        if (!empty($onet_list)) {
            if (isset($enroll->academic_results)) {
                $academic_results = json_decode($enroll->academic_results, true);
            } else {
                $academic_results = [];
            }
            $i = 1;
            foreach ($onet_list as $key => $label) {
                if ($i % 2 == 0) {
                    $groups = $fieldset->add('groups');
                }
                $i++;
                $groups->add('number', array(
                    'id' => 'register_'.$key,
                    'labelClass' => 'g-input icon-number',
                    'itemClass' => 'width50',
                    'label' => $label,
                    'data-keyboard' => '0123456789.',
                    'maxlength' => 6,
                    'value' => isset($academic_results[$key]) ? $academic_results[$key] : ''
                ));
            }
        }
        // enroll
        $fieldset->add('file', array(
            'name' => 'enroll[]',
            'id' => 'enroll',
            'labelClass' => 'g-input icon-upload',
            'itemClass' => 'item',
            'label' => '{LNG_Attach file}',
            'placeholder' => '{LNG_Upload :type files} {LNG_no larger than :size} ({LNG_Can select multiple files})',
            'comment' => '{LNG_ENROLL_ATTACH_COMMENT}',
            'dataPreview' => 'previewAttach',
            'multiple' => true,
            'accept' => self::$cfg->enroll_attach_file_typies
        ));
        if ($enroll->id > 0) {
            $fieldset->add('div', array(
                'innerHTML' => \Download\Index\Controller::init($enroll->id, 'enroll', self::$cfg->enroll_attach_file_typies, $login['id'])
            ));
        }
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        if ($enroll->can_edit || Login::checkPermission($login, 'can_manage_enroll')) {
            // submit
            $fieldset->add('submit', array(
                'class' => 'button save large icon-save',
                'value' => '{LNG_Save}'
            ));
        } elseif ($enroll->can_register) {
            // submit
            $fieldset->add('submit', array(
                'class' => 'button save large icon-save',
                'value' => '{LNG_Enroll}'
            ));
        }
        $fieldset->add('hidden', array(
            'id' => 'register_id',
            'value' => $enroll->id
        ));
        // districtID
        $fieldset->add('hidden', array(
            'id' => 'register_districtID',
            'value' => isset($enroll->districtID) ? $enroll->districtID : 0
        ));
        // amphurID
        $fieldset->add('hidden', array(
            'id' => 'register_amphurID',
            'value' => isset($enroll->amphurID) ? $enroll->amphurID : 0
        ));
        // provinceID
        $fieldset->add('hidden', array(
            'id' => 'register_provinceID',
            'value' => isset($enroll->provinceID) ? $enroll->provinceID : 0
        ));
        // Javascript
        $form->script('initEnroll("%s ({LNG_age} %y {LNG_year}, %m {LNG_month} %d {LNG_days})", "'.self::$cfg->enroll_country.'");');
        \Gcms\Controller::$view->setContentsAfter(array(
            '/:type/' => implode(', ', self::$cfg->enroll_attach_file_typies),
            '/:size/' => UploadedFile::getUploadSize()
        ));
        // คืนค่า HTML
        return $form->render();
    }
}
