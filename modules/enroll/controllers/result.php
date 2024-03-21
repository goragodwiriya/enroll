<?php
/**
 * @filesource modules/enroll/controllers/result.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Result;

use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=enroll-result
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ยืนยันการลงทะเบียน
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // เข้าระบบเป็นผู้สมัคร
        $id = isset($_SESSION['enroll']) ? $_SESSION['enroll']['id'] : '';
        // ตรวจสอบรายการที่เลือก
        $enroll = \Enroll\Result\Model::get($request->request('id', $id)->password());
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_Result} &amp; {LNG_Print}');
        // เลือกเมนู
        $this->menu = 'result';
        // แสดงผล
        $section = Html::create('section');
        // breadcrumbs
        $breadcrumbs = $section->add('nav', array(
            'class' => 'breadcrumbs'
        ));
        $ul = $breadcrumbs->add('ul');
        $ul->appendChild('<li><a class="icon-verfied" href="index.php">{LNG_Home}</a></li>');
        if ($enroll !== false) {
            $ul->appendChild('<li><span>'.$enroll->name.'</span></li>');
            $this->title .= ' '.$enroll->name;
        }
        $ul->appendChild('<li><span>{LNG_Result} &amp; {LNG_Print}</span></li>');
        $section->add('header', array(
            'innerHTML' => '<h2 class="icon-write">'.$this->title.'</h2>'
        ));
        $div = $section->add('div', array(
            'class' => 'content_bg'
        ));
        if ($enroll === false) {
            // แสดงฟอร์ม
            $div->appendChild(\Enroll\Login\View::create()->render($request));
        } else {
            // ผลการสมัคร
            $div->appendChild(\Enroll\Result\View::create()->render($request, $enroll));
        }
        // คืนค่า HTML
        return $section->render();
    }
}
