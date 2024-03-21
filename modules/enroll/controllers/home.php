<?php
/**
 * @filesource modules/enroll/controllers/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Home;

use Kotchasan\Http\Request;

/**
 * module=enroll-home.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ฟังก์ชั่นสร้าง card
     *
     * @param Request         $request
     * @param \Kotchasan\Html $card
     * @param array           $login
     */
    public static function addCard(Request $request, $card, $login)
    {
        $datas = \Enroll\Home\Model::datas();
        foreach (\Enroll\Level\Model::toSelect() as $level => $label) {
            $count = isset($datas[$level]) ? $datas[$level] : 0;
            \Index\Home\Controller::renderCard($card, 'icon-register', $label, number_format($count), '{LNG_Number of registrants}', 'index.php?module=enroll&amp;level='.$level);
        }
    }

    /**
     * ฟังก์ชั่นสร้าง block
     *
     * @param Request $request
     * @param Collection $block
     * @param array $login
     */
    public static function addBlock(Request $request, $block, $login)
    {
        $content = \Enroll\Home\View::create()->render($request, $login);
        $block->set('Enroll', $content);
    }
}
