<?php
/**
 * @filesource modules/enroll/controllers/enroll.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Enroll;

use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * API ใบสมัครของเจ้าหน้าที่ (หน้า /enroll ที่เปิดจากตารางผู้สมัคร)
 *
 * แยกจากหน้าของผู้สมัคร (api/enroll/register) ทุก endpoint ต้องเข้าระบบและมีสิทธิ์ can_manage_enroll
 * ใช้ตรวจ/บันทึกชุดเดียวกับผู้สมัคร (สืบทอด \Enroll\Register\Controller) ต่างกันที่กติกาของเจ้าหน้าที่:
 * ไม่ติดช่วงรับสมัคร จำนวนที่รับ และตัวกันสแปม แต่ยังแก้ได้เฉพาะสถานะที่ตั้งไว้ว่าแก้ไขได้ (กติกาเดิมของระบบ)
 * ใบที่แก้ไม่ได้ยังเปิดดูได้ บันทึกเสร็จกลับไปตารางผู้สมัครของระดับชั้นนั้น
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Register\Controller
{
    /**
     * GET api/enroll/enroll/get?id=<link>
     * ใบสมัครสำหรับดู/แก้ไข (ไม่ระบุ id = เจ้าหน้าที่เพิ่มใบสมัครใหม่)
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

            $login = $this->authenticateRequest($request);
            $denied = $this->denied($login);
            if ($denied) {
                return $denied;
            }

            $key = $request->get('id')->filter('a-z0-9');
            $enroll = $key === '' ? null : Model::get($key);
            if ($key !== '' && !$enroll) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $payload = $this->formPayload($request, $enroll, true);
            // สถานะที่ไม่อยู่ในรายการ "แก้ไขได้" = ดูได้อย่างเดียว
            $editable = $enroll === null ? true : self::canEdit($enroll, true);
            $status = Language::get('REGISTER_STATUS', []);
            $payload['data'] = array_merge($payload['data'], [
                'can_save' => $editable === true,
                'readonly_message' => $editable === true ? '' : $editable,
                'created_at' => $enroll ? Date::format($enroll->created_at, 'd M Y H:i') : '',
                'result_status_text' => $enroll ? ($status[$enroll->result_status] ?? '') : '',
                'status_class' => $enroll ? 'term'.(int) $enroll->result_status : '',
                'review_text' => $enroll ? \Enroll\Enrolls\Controller::reviewText($enroll->review) : '',
                'card_url' => $enroll && \Enroll\Examcard\Controller::allowed($enroll) ? WEB_URL.'api/enroll/examcard?id='.$enroll->link : '',
                'back_url' => '/enrolls?level='.(int) $payload['data']['level']
            ]);

            return $this->successResponse($payload, 'Application loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/enroll/enroll/save
     *
     * @param Request $request
     *
     * @return Response
     */
    public function save(Request $request)
    {
        $denied = $this->denied($this->authenticateRequest($request));

        return $denied ?: parent::save($request);
    }

    /**
     * GET api/enroll/enroll/plans?level=
     *
     * @param Request $request
     *
     * @return Response
     */
    public function plans(Request $request)
    {
        $denied = $this->denied($this->authenticateRequest($request));

        return $denied ?: parent::plans($request);
    }

    /**
     * POST api/enroll/enroll/removefile
     *
     * @param Request $request
     *
     * @return Response
     */
    public function removefile(Request $request)
    {
        $denied = $this->denied($this->authenticateRequest($request));

        return $denied ?: parent::removefile($request);
    }

    /**
     * กติกาของเจ้าหน้าที่ ตามสิทธิ์จัดการการรับสมัคร
     *
     * @param object|null $login
     *
     * @return bool
     */
    protected static function isStaff($login)
    {
        return ApiController::hasPermission($login, 'can_manage_enroll');
    }

    /**
     * บันทึกเสร็จกลับไปตารางผู้สมัครของระดับชั้นนั้น
     *
     * @param object $enroll
     * @param string $message
     *
     * @return Response
     */
    protected function savedResponse($enroll, $message)
    {
        return $this->redirectResponse('/enrolls?level='.(int) $enroll->level, $message, 200, 1000);
    }

    /**
     * ไม่ได้เข้าระบบ (401) หรือไม่มีสิทธิ์จัดการการรับสมัคร (403)
     *
     * @param object|null $login
     *
     * @return Response|null null = ผ่าน
     */
    private function denied($login)
    {
        if (!$login) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if (!static::isStaff($login)) {
            return $this->errorResponse('Forbidden', 403);
        }

        return null;
    }
}
