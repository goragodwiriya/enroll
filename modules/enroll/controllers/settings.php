<?php
/**
 * @filesource modules/enroll/controllers/settings.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Settings;

use Gcms\Api as ApiController;
use Gcms\Config;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * API ตั้งค่าโมดูลรับสมัครนักเรียน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/settings/get
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
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_config')) {
                return $this->errorResponse('Permission required', 403);
            }

            return $this->successResponse([
                'data' => [
                    'school_name' => self::$cfg->school_name,
                    'school_year' => (int) self::$cfg->school_year,
                    'enroll_study_plan_count' => (int) self::$cfg->enroll_study_plan_count,
                    'enroll_w' => (int) self::$cfg->enroll_w,
                    'enroll_csv_language' => self::$cfg->enroll_csv_language,
                    'enroll_country' => self::$cfg->enroll_country,
                    'enroll_prefix' => self::$cfg->enroll_prefix,
                    'enroll_no' => self::$cfg->enroll_no,
                    'enroll_begin' => empty(self::$cfg->enroll_begin) ? '' : date('Y-m-d\TH:i', self::$cfg->enroll_begin),
                    'enroll_end' => empty(self::$cfg->enroll_end) ? '' : date('Y-m-d\TH:i', self::$cfg->enroll_end),
                    // จำนวนช่องขึ้นกับรายการภาษา REGISTER_STATUS จึงสร้างด้วย initEnrollSettings
                    'enroll_editable' => is_array(self::$cfg->enroll_editable) ? array_map('intval', self::$cfg->enroll_editable) : [],
                    'enroll_phone_limit' => (int) self::$cfg->enroll_phone_limit,
                    'enroll_capacity_exclude_statuses' => \Enroll\Plan\Model::uncountedStatuses(),
                    'enroll_notify_email' => (int) self::$cfg->enroll_notify_email,
                    'enroll_notify_sms' => (int) self::$cfg->enroll_notify_sms,
                    'enroll_announce' => (int) self::$cfg->enroll_announce,
                    'enroll_announce_statuses' => array_map('intval', (array) self::$cfg->enroll_announce_statuses),
                    'enroll_exam_card' => (int) self::$cfg->enroll_exam_card,
                    'enroll_exam_statuses' => array_map('intval', (array) self::$cfg->enroll_exam_statuses),
                    'enroll_exam_date' => (string) self::$cfg->enroll_exam_date,
                    'enroll_exam_place' => (string) self::$cfg->enroll_exam_place,
                    'enroll_exam_note' => (string) self::$cfg->enroll_exam_note
                ],
                'options' => [
                    'enroll_csv_language' => \Gcms\Controller::arrayToOptions(Language::get('CSV_LANGUAGES', [])),
                    'enroll_country' => \Gcms\Controller::arrayToOptions(Language::get('COUNTRIES', [])),
                    'status_fields' => \Gcms\Controller::arrayToOptions(Language::get('REGISTER_STATUS', []))
                ]
            ], 'Enroll settings loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/enroll/settings/save
     *
     * @param Request $request
     *
     * @return Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $config = Config::load(ROOT_PATH.'settings/config.php');
            $config->school_name = $request->post('school_name')->topic();
            $config->school_year = $request->post('school_year')->toInt();
            $config->enroll_study_plan_count = max(1, $request->post('enroll_study_plan_count')->toInt());
            $config->enroll_w = max(100, $request->post('enroll_w')->toInt());
            $config->enroll_csv_language = $request->post('enroll_csv_language')->filter('A-Z0-9\-');
            $config->enroll_country = $request->post('enroll_country')->filter('A-Z');
            $config->enroll_prefix = $request->post('enroll_prefix')->topic();
            $config->enroll_no = $request->post('enroll_no')->topic();

            // รับเฉพาะสถานะที่มีอยู่จริง กันค่าที่ไม่รู้จักหลุดลง config
            $config->enroll_editable = self::statusList($request, 'enroll_editable');

            $config->enroll_phone_limit = max(0, $request->post('enroll_phone_limit')->toInt());
            $config->enroll_capacity_exclude_statuses = self::statusList($request, 'enroll_capacity_exclude_statuses');
            $config->enroll_notify_email = $request->post('enroll_notify_email')->toInt() === 1 ? 1 : 0;
            $config->enroll_notify_sms = $request->post('enroll_notify_sms')->toInt() === 1 ? 1 : 0;
            $config->enroll_announce = $request->post('enroll_announce')->toInt() === 1 ? 1 : 0;
            $config->enroll_announce_statuses = self::statusList($request, 'enroll_announce_statuses');
            $config->enroll_exam_card = $request->post('enroll_exam_card')->toInt() === 1 ? 1 : 0;
            $config->enroll_exam_statuses = self::statusList($request, 'enroll_exam_statuses');
            $config->enroll_exam_date = $request->post('enroll_exam_date')->topic();
            $config->enroll_exam_place = $request->post('enroll_exam_place')->topic();
            $config->enroll_exam_note = $request->post('enroll_exam_note')->textarea();

            $begin = $request->post('enroll_begin')->datetime();
            $end = $request->post('enroll_end')->datetime();
            $config->enroll_begin = empty($begin) ? 0 : strtotime($begin);
            $config->enroll_end = empty($end) ? 0 : strtotime($end);

            if (!Config::save($config, ROOT_PATH.'settings/config.php')) {
                return $this->errorResponse(
                    Language::replace('File %s cannot be created or is read-only.', 'settings/config.php'),
                    500
                );
            }

            \Index\Log\Model::add(0, 'enroll', 'Save', 'Enroll settings saved', $login->id);

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * สถานะผลการสมัครที่เลือกจาก select หลายค่า เฉพาะที่มีอยู่จริงใน REGISTER_STATUS
     *
     * @param Request $request
     * @param string $name
     *
     * @return array
     */
    protected static function statusList(Request $request, $name)
    {
        $statuses = array_keys(Language::get('REGISTER_STATUS', []));
        $result = [];
        foreach ($request->post($name, [])->toInt() as $value) {
            if (in_array($value, $statuses)) {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * POST api/enroll/settings/reset
     * ล้างข้อมูลการสมัครทั้งหมด (ใบสมัคร แผนที่เลือก เลขที่ผู้สมัคร และไฟล์)
     *
     * @param Request $request
     *
     * @return Response
     */
    public function reset(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            // สิทธิ์เดียวกับระบบเดิม: จัดการการรับสมัครได้ และไม่ใช่บัญชีตัวอย่าง
            if (!ApiController::canModify($login, ['can_manage_enroll'])) {
                return $this->errorResponse('Permission required', 403);
            }

            \Enroll\Enroll\Model::reset();

            \Index\Log\Model::add(0, 'enroll', 'Delete', Language::get('Reset database'), $login->id);

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
