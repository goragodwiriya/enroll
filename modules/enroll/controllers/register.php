<?php
/**
 * @filesource modules/enroll/controllers/register.php
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
use Kotchasan\Language;

/**
 * module=enroll-register
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ฟอร์มลงทะเบียน
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Registration form');
        // เลือกเมนู
        $this->menu = 'enroll';
        // สมาชิก
        $login = Login::isMember();
        if (!$login) {
            // ไม่ใช่สมาชิกตรวจสอบว่าเป็นคนลงทะเบียนหรือเปล่า
            $login = isset($_SESSION['enroll']) ? $_SESSION['enroll'] : null;
        }
        // รายการที่ต้องการ
        $id = $request->request('id')->filter('a-z0-9');
        // ตรวจสอบรายการที่เลือก
        $enroll = \Enroll\Register\Model::get($id);
        if ($enroll) {
            // วันนี้
            $today = time();
            // ใหม่
            $enroll->can_register = $enroll->id == 0;
            // แก้ไขจาก link
            $enroll->can_edit = $enroll->id > 0 && $enroll->link == $id;
            // ใหม่และแก้ไขจาก link ตามวันที่ ที่กำหนด
            if (empty(self::$cfg->enroll_begin) || empty(self::$cfg->enroll_end) || ($today >= self::$cfg->enroll_begin && $today <= self::$cfg->enroll_end)) {
                $can_register = $enroll->can_register || $enroll->can_edit;
            } else {
                $can_register = false;
            }
            // สามารถ register ได้ หรือ ผู้ดูแล
            if ($can_register || Login::checkPermission($login, 'can_manage_enroll')) {
                // แสดงผล
                $section = Html::create('section');
                // breadcrumbs
                $breadcrumbs = $section->add('nav', array(
                    'class' => 'breadcrumbs'
                ));
                $ul = $breadcrumbs->add('ul');
                $ul->appendChild('<li><a class="icon-register" href="index.php">{LNG_Home}</a></li>');
                $ul->appendChild('<li><span>{LNG_Enroll}</span></li>');
                $section->add('header', array(
                    'innerHTML' => '<h2 class="icon-write">'.$this->title.'</h2>'
                ));
                $div = $section->add('div', array(
                    'class' => 'content_bg'
                ));
                // แสดงฟอร์ม
                $div->appendChild(\Enroll\Register\View::create()->render($request, $enroll, $login));
                // คืนค่า HTML
                return $section->render();
            }
        }
        // 404
        return \Index\Error\Controller::execute($this);
    }
}
