<?php
/**
 * @filesource modules/enroll/views/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Home;

use Kotchasan\Html;
use Kotchasan\Language;

/**
 * หน้า Home.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * หน้า Home
     *
     * @param object $index
     * @param array  $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        if (file_exists(ROOT_PATH.DATA_FOLDER.'pages/dashboard_'.Language::name().'.html')) {
            // เนื้อหาในภาษาที่เลือก
            $content = file_get_contents(ROOT_PATH.DATA_FOLDER.'pages/dashboard_'.LANGUAGE.'.html');
        } elseif (file_exists(ROOT_PATH.DATA_FOLDER.'pages/dashboard_th.html')) {
            // ถ้าไม่มี ใช้เนื้อหาภาษาไทย
            $content = file_get_contents(ROOT_PATH.DATA_FOLDER.'pages/dashboard_th.html');
        } else {
            // เนื้อหาภาษาไทยเริ่มต้น
            $content = file_get_contents(ROOT_PATH.self::$cfg->skin.'/dashboard.html');
        }
        $section = Html::create('section');
        $section->add('div', array(
            'innerHTML' => $content
        ));
        // คืนค่า HTML
        return $section->render();
    }
}
