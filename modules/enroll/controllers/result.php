<?php
/**
 * @filesource modules/enroll/controllers/result.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Result;

use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * API ผลการสมัครของผู้สมัคร (หน้าสาธารณะ)
 *
 * ไม่ระบุ link = ยังไม่รู้ว่าเป็นใคร ให้ค้นด้วยเลขประจำตัวประชาชนและวันเกิดก่อน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/result/get?id=<link>
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

            $key = $request->get('id')->filter('a-z0-9');
            $enroll = $key === '' ? null : \Enroll\Enroll\Model::get($key);

            // ไม่มี link หรือ link ไม่ถูกต้อง = แสดงฟอร์มค้นหาแทน
            if (!$enroll || $enroll->link !== $key) {
                return $this->successResponse([
                    'data' => self::schoolInfo() + [
                        'found' => false,
                        'content' => self::pageContent('result')
                    ]
                ], 'Enter your information');
            }

            $status = Language::get('REGISTER_STATUS', []);
            $plans = \Enroll\Plan\Model::toArray($enroll->level);
            $levels = \Enroll\Level\Model::toArray();

            $editable = is_array(self::$cfg->enroll_editable) ? array_map('intval', self::$cfg->enroll_editable) : [];

            return $this->successResponse([
                'data' => self::schoolInfo() + [
                    'found' => true,
                    'link' => $enroll->link,
                    'enroll_no' => $enroll->enroll_no,
                    'barcode' => 'data:image/png;base64,'.base64_encode(\Enroll\Enroll\Model::barcodePng($enroll->enroll_no, 40, 9)),
                    'created_at' => Date::format($enroll->created_at, 'd M Y'),
                    'name' => $enroll->name,
                    'id_card' => $enroll->id_card,
                    'phone' => $enroll->phone,
                    // ค่าที่ประกอบเป็นข้อความแล้ว ส่งมาจากฝั่งนี้เลย
                    // ตัวประเมินนิพจน์ของเทมเพลตไม่รับประกันการต่อสตริงทุกรูปแบบ
                    'phone_url' => 'tel:'.$enroll->phone,
                    'level' => $levels[$enroll->level] ?? '',
                    'plan' => self::planText($enroll, $plans),
                    'result_status' => (int) $enroll->result_status,
                    'result_status_text' => $status[$enroll->result_status] ?? '',
                    'status_class' => 'term'.(int) $enroll->result_status,
                    'picture' => \Enroll\Enroll\Model::pictureUrl($enroll->id),
                    'can_edit' => in_array((int) $enroll->result_status, $editable, true),
                    'edit_url' => '/enroll-edit?id='.$enroll->link,
                    'print_url' => WEB_URL.'api/enroll/printform?id='.$enroll->link,
                    // บัตรประจำตัวผู้สอบ (เมื่อผู้ดูแลเปิดและสถานะของใบนี้พิมพ์ได้)
                    'card_url' => \Enroll\Examcard\Controller::allowed($enroll) ? WEB_URL.'api/enroll/examcard?id='.$enroll->link : '',
                    'content' => self::pageContent('result')
                ]
            ], 'Result retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/enroll/result/lookup
     * ค้นใบสมัครด้วยเลขประจำตัวประชาชนและวันเกิด
     *
     * @param Request $request
     *
     * @return Response
     */
    public function lookup(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $this->initLanguage($request);

            $id_card = $request->post('id_card')->number();
            $birthday = $request->post('birthday')->date();

            $errors = [];
            // Text::date() คืนค่า null (ไม่ใช่สตริงว่าง) เมื่อไม่ได้กรอกหรือรูปแบบผิด
            if (empty($id_card)) {
                $errors['id_card'] = 'Please fill in';
            }
            if (empty($birthday)) {
                $errors['birthday'] = 'Please fill in';
            }
            if (!empty($errors)) {
                return $this->formErrorResponse($errors);
            }

            $enroll = \Enroll\Enroll\Model::getByIdCard($id_card, $birthday);
            if (!$enroll) {
                return $this->errorResponse(Language::get('Incorrect information, please check.'), 400);
            }

            return $this->redirectResponse('/enroll-result?id='.$enroll->link, '', 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * แผนการเรียนที่แสดงในผลการสมัคร
     * ประกาศผลแล้ว (อนุมัติ) แสดงเฉพาะแผนที่ได้ ไม่งั้นแสดงแผนที่เลือกไว้ทั้งหมด
     *
     * @param object $enroll
     * @param array $plans
     *
     * @return string
     */
    protected static function planText($enroll, $plans)
    {
        if ((int) $enroll->result_status === 1 && isset($plans[$enroll->result_plan])) {
            return $plans[$enroll->result_plan];
        }

        $result = [];
        foreach (\Enroll\Enroll\Model::choices($enroll->id) as $plan_id) {
            if (isset($plans[$plan_id])) {
                $result[] = $plans[$plan_id];
            }
        }

        return implode(', ', $result);
    }
}
