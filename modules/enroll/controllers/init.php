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
use Kotchasan\Language;

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
        // หน้าแรกของเจ้าหน้าที่ (/dashboard) คือเมนูหน้าแรกของระบบ (เข้าระบบแล้วถูกพาไปเอง)
        // แต่ละเมนูแสดงเฉพาะผู้ที่เปิดหน้านั้นได้จริง (ตรวจสิทธิ์เดียวกับ API ของหน้านั้น)
        //   ตารางผู้สมัคร /enrolls = can_manage_enroll
        //   รายงานผู้สมัคร /enroll-applicants = สมาชิกทุกคน (อ่านอย่างเดียว ไม่มีเลขบัตร/เบอร์โทร)
        $memberMenu = [];
        if (ApiController::hasPermission($login, 'can_manage_enroll')) {
            $memberMenu[] = [
                'title' => '{LNG_List of} {LNG_Enroll}',
                'url' => '/enrolls',
                'icon' => 'icon-list'
            ];
        }
        if ($login) {
            $memberMenu[] = [
                'title' => '{LNG_Report} {LNG_Enroll}',
                'url' => '/enroll-applicants',
                'icon' => 'icon-report'
            ];
        }
        if (!empty($memberMenu)) {
            $menus = parent::insertMenuAfter($menus, $memberMenu, 0);
        }

        // แต่ละรายการแสดงเฉพาะผู้ที่เปิดหน้านั้นได้จริง
        // ระดับชั้น/แผนการเรียน = can_manage_enroll, ตั้งค่าโมดูลและรายการภาษา = can_config
        $children = [];
        if (ApiController::hasPermission($login, 'can_config')) {
            $children[] = [
                'title' => '{LNG_Module Settings}',
                'url' => '/enroll-settings',
                'icon' => 'icon-cog'
            ];
        }
        // เนื้อหาหน้าแรก/หน้าผลการสมัคร เป็น HTML ที่ผู้เข้าชมทุกคนเห็น แก้ได้เฉพาะผู้ดูแลระบบ
        // เหมือนระบบเดิม (API ตรวจด้วยฟังก์ชันเดียวกัน)
        if ($login && \Enroll\Pages\Controller::canEditPages($login)) {
            $children[] = [
                'title' => '{LNG_Page details}',
                'url' => '/enroll-pages',
                'icon' => 'icon-write'
            ];
        }
        if (ApiController::hasPermission($login, 'can_manage_enroll')) {
            $children[] = [
                'title' => '{LNG_Education level}',
                'url' => '/enroll-levels',
                'icon' => 'icon-elearning'
            ];
            $children[] = [
                'title' => '{LNG_Study plan}',
                'url' => '/enroll-plans',
                'icon' => 'icon-category'
            ];
        }
        if (ApiController::hasPermission($login, 'can_config')) {
            $children[] = [
                'title' => '{LNG_Academic result}',
                'url' => '/language?key=ACADEMIC_RESULTS',
                'icon' => 'icon-language'
            ];
            $children[] = [
                'title' => '{LNG_Parent}',
                'url' => '/language?key=PARENT_LIST',
                'icon' => 'icon-language'
            ];
        }
        if (empty($children)) {
            return $menus;
        }

        $enrollMenu = [
            'title' => '{LNG_Enroll}',
            'icon' => 'icon-register',
            'children' => $children
        ];

        // ผู้จัดการที่ไม่มีสิทธิ์ can_config ไม่มีเมนูตั้งค่าให้แทรก (เดิมเมนูนี้จึงหายไปทั้งหมด
        // เข้าหน้าระดับชั้น/แผนการเรียนจากเมนูไม่ได้) ให้แสดงเป็นเมนูหลักแทน
        if (!isset($menus['settings'])) {
            $menus['enroll'] = $enrollMenu;

            return $menus;
        }

        return parent::insertMenuChildren($menus, [$enrollMenu], 'settings', null, 1);
    }

    /**
     * การ์ดบนหน้าแรกของเจ้าหน้าที่ (hook initDashboard ของ api/index/dashboard)
     *
     * หน้าแรกของเว็บ (/) เป็นของผู้สมัคร เจ้าหน้าที่ที่เข้าระบบจึงถูกพาไปหน้าแรกของระบบที่ /dashboard
     * ซึ่งรวมการ์ดจากทุกโมดูล — โมดูลนี้ส่งจำนวนผู้สมัครทั้งหมดและแต่ละระดับชั้น
     * การ์ดระดับชั้นลิงก์ไปตารางผู้สมัครของระดับนั้น บรรทัดล่างคือจำนวนใบที่ต้องตรวจสอบ (มี = สีแดง)
     *
     * @param array $cards
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initDashboardCards($cards, $params = null, $login = null)
    {
        if (!ApiController::hasPermission($login, 'can_manage_enroll')) {
            return $cards;
        }
        $counts = \Enroll\Enroll\Model::countByLevel();
        $reviews = \Enroll\Enroll\Model::countByLevel(true);
        $people = Language::get('people');
        $needsReview = Language::get('Needs review');
        $totalReview = array_sum($reviews);
        // รวมทุกระดับไม่มีตารางให้เจาะดู (ตารางแสดงทีละระดับชั้น) จึงไม่เป็นลิงก์
        $cards[] = [
            'title' => Language::get('Number of registrants'),
            'value' => number_format(array_sum($counts)),
            'unit' => $people,
            'icon' => 'icon-users',
            'url' => null,
            'hint' => $needsReview.' '.number_format($totalReview),
            'class' => $totalReview > 0 ? 'negative' : 'positive'
        ];
        foreach (\Enroll\Level\Model::all() as $level) {
            $review = $reviews[$level->id] ?? 0;
            $cards[] = [
                'title' => $level->topic,
                'value' => number_format($counts[$level->id] ?? 0),
                'unit' => $people,
                'icon' => 'icon-register',
                'url' => '/enrolls?level='.$level->id,
                'hint' => $needsReview.' '.number_format($review),
                'class' => $review > 0 ? 'negative' : 'positive'
            ];
        }

        return $cards;
    }
}
