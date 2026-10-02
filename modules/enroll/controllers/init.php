<?php
/**
 * @filesource modules/enroll/controllers/init.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Init;

use Gcms\Api as ApiController;

/**
 * ลงทะเบียนเมนูและสิทธิ์ของโมดูลรับสมัครนักเรียน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * สิทธิ์ของโมดูล
     *
     * @param array $permissions
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initPermission($permissions, $params = null, $login = null)
    {
        $permissions[] = [
            'value' => 'can_manage_enroll',
            'text' => '{LNG_Can manage the} {LNG_Enroll}'
        ];

        return $permissions;
    }

    /**
     * เมนูของโมดูล
     *
     * @param array $menus
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initMenus($menus, $params = null, $login = null)
    {
        if (ApiController::hasPermission($login, 'can_manage_enroll')) {
            $settings = [
                [
                    'title' => '{LNG_Module settings}',
                    'url' => '/enroll-settings',
                    'icon' => 'icon-cog'
                ],
                [
                    'title' => '{LNG_Education level}',
                    'url' => '/enroll-levels',
                    'icon' => 'icon-elearning'
                ],
                [
                    'title' => '{LNG_Study plan}',
                    'url' => '/enroll-plans',
                    'icon' => 'icon-category'
                ],
                [
                    'title' => '{LNG_Academic result}',
                    'url' => '/language?key=ACADEMIC_RESULTS',
                    'icon' => 'icon-language'
                ],
                [
                    'title' => '{LNG_Parent}',
                    'url' => '/language?key=PARENT_LIST',
                    'icon' => 'icon-language'
                ]
            ];

            $menus = parent::insertMenuChildren($menus, [
                [
                    'title' => '{LNG_Enroll}',
                    'icon' => 'icon-register',
                    'children' => $settings
                ]
            ], 'settings', null, 1);
        }

        return $menus;
    }

    /**
     * เมนูลัดไปยังหน้าแก้ไขรายการภาษาชนิด array ของโมดูล
     * ข้ามรายการที่ยังไม่ถูกนำเข้าฐานข้อมูล
     *
     * @return array
     */
    protected static function languageMenus()
    {
        $labels = [
            'ACADEMIC_RESULTS' => '{LNG_Academic result}',
            'PARENT_LIST' => '{LNG_Parent}'
        ];

        try {
            $rows = \Kotchasan\Model::createQuery()
                ->select('id', 'key')
                ->from('language')
                ->where([['key', array_keys($labels)], ['type', 'array']])
                ->cacheOn()
                ->fetchAll();
        } catch (\Exception $e) {
            return [];
        }

        $menus = [];
        foreach ($rows as $row) {
            $menus[] = [
                'title' => $labels[$row->key],
                'url' => '/language?id='.$row->id,
                'icon' => 'icon-language'
            ];
        }

        return $menus;
    }
}
