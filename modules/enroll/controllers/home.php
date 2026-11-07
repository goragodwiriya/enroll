<?php
/**
 * @filesource modules/enroll/controllers/home.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Home;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * หน้าแรกของผู้สมัคร (รายละเอียดการรับสมัครก่อนลงทะเบียน)
 *
 * เทียบกับหน้า Home ของระบบเดิม ที่แสดงเนื้อหา datas/pages/dashboard_<ภาษา>.html
 * ให้ผู้เข้าชมทุกคนโดยไม่ต้องเข้าระบบ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/home/get
     *
     * @param Request $request
     *
     * @return Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $info = self::schoolInfo();
            // ระดับชั้น/แผนการเรียนที่เปิดรับ พร้อมจำนวนที่รับและที่สมัครแล้ว
            // ปุ่มสมัครของการ์ดพาไปฟอร์มที่เลือกระดับชั้นนั้นไว้แล้ว (เปิดรับอยู่และระดับชั้นยังไม่เต็ม)
            $levels = \Enroll\Plan\Model::summary();
            $seats = 0;
            $allLimited = true;
            foreach ($levels as $i => $level) {
                $levels[$i]['can_apply'] = $info['is_open'] && !$level['full'];
                $levels[$i]['apply_url'] = 'enroll-edit?level='.$level['id'];
                $seats += $level['seats'];
                $allLimited = $allLimited && $level['seats'] > 0;
            }

            return $this->successResponse([
                'data' => $info + [
                    'content' => self::pageContent('dashboard'),
                    'levels' => $levels,
                    // แถบตัวเลขใต้ hero: จำนวนที่รับรวมแสดงเมื่อทุกระดับกำหนดจำนวนไว้
                    'stats' => [
                        'levels' => count($levels),
                        'plans' => array_sum(array_column($levels, 'plan_count')),
                        'seats' => $allLimited ? $seats : 0
                    ]
                ]
            ], 'Home loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * GET api/enroll/home/privacy
     * ประกาศความเป็นส่วนตัว (PDPA) ที่ผู้สมัครยินยอมก่อนส่งใบสมัคร
     *
     * @param Request $request
     *
     * @return Response
     */
    public function privacy(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            return $this->successResponse([
                'data' => self::schoolInfo() + [
                    'content' => self::pageContent('privacy')
                ]
            ], 'Privacy notice loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
