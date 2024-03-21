<?php
/**
 * @filesource modules/enroll/controllers/initmenu.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Initmenu;

use Gcms\Login;
use Kotchasan\Http\Request;

/**
 * Init Menu
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * ฟังก์ชั่นเริ่มต้นการทำงานของโมดูลที่ติดตั้ง
     * และจัดการเมนูของโมดูล
     *
     * @param Request                $request
     * @param \Index\Menu\Controller $menu
     * @param array                  $login
     */
    public static function execute(Request $request, $menu, $login)
    {
        // วันนี้
        $today = time();
        if (empty(self::$cfg->enroll_begin) || empty(self::$cfg->enroll_end) || ($today >= self::$cfg->enroll_begin && $today <= self::$cfg->enroll_end)) {
            $menu->addTopLvlMenu('enroll', '{LNG_Enroll}', 'index.php?module=enroll-register', null, 'member');
        }
        if ($login) {
            // เมนูตั้งค่า
            $submenus = [];
            // สามารถตั้งค่าระบบได้
            if (Login::checkPermission($login, 'can_config')) {
                $submenus['settings'] = array(
                    'text' => '{LNG_Settings}',
                    'url' => 'index.php?module=enroll-settings'
                );
                $submenus['ACADEMIC_RESULTS'] = array(
                    'text' => '{LNG_Academic result}',
                    'url' => 'index.php?module=languageedit&amp;key=ACADEMIC_RESULTS'
                );
                $submenus['PARENT_LIST'] = array(
                    'text' => '{LNG_Parent}',
                    'url' => 'index.php?module=languageedit&amp;key=PARENT_LIST'
                );
            }
            // สามารถจัดการการลงทะเบียนได้
            if (Login::checkPermission($login, 'can_manage_enroll')) {
                $submenus['level'] = array(
                    'text' => '{LNG_Education level}',
                    'url' => 'index.php?module=enroll-level'
                );
                $submenus['plan'] = array(
                    'text' => '{LNG_Study plan}',
                    'url' => 'index.php?module=enroll-plan'
                );
                $menu->addTopLvlMenu('enrollsetup', '{LNG_List of} {LNG_Enroll}', 'index.php?module=enroll-setup', null, 'member');
            }
            if (!empty($submenus)) {
                $menu->add('settings', '{LNG_Enroll}', null, $submenus, 'enroll');
            }
        } else {
            if ($request->request('action')->toString() === 'logout') {
                unset($_SESSION['enroll']);
            }
            $menu->addTopLvlMenu('result', '{LNG_Result} &amp; {LNG_Print}', 'index.php?module=enroll-result', null, 'member');
        }
    }
}
