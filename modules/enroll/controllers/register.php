<?php
/**
 * @filesource modules/enroll/controllers/register.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Register;

use Gcms\Api as ApiController;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * API ฟอร์มลงทะเบียนของผู้สมัคร
 *
 * เปิดให้ผู้ที่ไม่ใช่สมาชิกใช้งานได้ กุญแจเข้าถึงใบสมัครเดิมคือ link 32 ตัวอักษร
 * ที่สุ่มตอนสมัคร ผู้ดูแลที่มีสิทธิ์ can_manage_enroll เข้าถึงได้ด้วย id หรือ link
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/register/get?id=<link>
     * อ่านข้อมูลใบสมัครสำหรับเติมลงฟอร์ม (ไม่ระบุ id = ใบสมัครใหม่)
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
            $canManage = ApiController::hasPermission($login, 'can_manage_enroll');

            $key = $request->get('id')->filter('a-z0-9');
            $enroll = $key === '' ? null : \Enroll\Enroll\Model::get($key);

            if ($key !== '' && !$enroll) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $allowed = $enroll === null
                ? self::canRegister($canManage)
                : self::canEdit($enroll, $canManage);
            if ($allowed !== true) {
                return $this->errorResponse($allowed, 403);
            }

            $level = $enroll ? (int) $enroll->level : \Enroll\Level\Model::firstId(true);
            $choices = $enroll ? \Enroll\Enroll\Model::choices($enroll->id) : [];

            $data = [
                'school_name' => self::$cfg->school_name,
                'id' => $enroll ? (int) $enroll->id : 0,
                'link' => $enroll ? $enroll->link : '',
                'level' => (string) $level,
                'title' => (string) ($enroll ? $enroll->title : 1),
                'name' => $enroll ? $enroll->name : '',
                'id_card' => $enroll ? $enroll->id_card : '',
                'birthday' => $enroll ? $enroll->birthday : null,
                'phone' => $enroll ? $enroll->phone : '',
                'email' => $enroll ? $enroll->email : '',
                'nationality' => $enroll ? $enroll->nationality : '',
                'religion' => $enroll ? $enroll->religion : '',
                'address' => $enroll ? $enroll->address : '',
                'district' => $enroll ? $enroll->district : '',
                'districtID' => $enroll ? (int) $enroll->districtID : 0,
                'amphur' => $enroll ? $enroll->amphur : '',
                'amphurID' => $enroll ? (int) $enroll->amphurID : 0,
                'province' => $enroll ? $enroll->province : '',
                'provinceID' => $enroll ? (int) $enroll->provinceID : 0,
                'zipcode' => $enroll ? $enroll->zipcode : '',
                'original_school' => $enroll ? $enroll->original_school : '',
                'is_new' => $enroll === null,
                'can_manage' => $canManage,
                // ส่วนที่จำนวนช่องขึ้นกับค่ากำหนดและรายการภาษา สร้างโดย initEnrollRegister
                'plan_fields' => self::planFields($choices),
                'parent_fields' => self::parentFields($enroll),
                'academic_fields' => self::academicFields($enroll),
                'attach_accept' => implode(',', self::$cfg->enroll_attach_file_typies),
                'thumbnail' => self::thumbnailField($enroll),
                'attachments' => $enroll ? \Enroll\Enroll\Model::attachments($enroll->id) : []
            ];

            return $this->successResponse([
                'data' => $data,
                'options' => [
                    'levels' => \Enroll\Level\Model::toOptions(true),
                    'plans' => \Enroll\Plan\Model::toOptions($level, true),
                    'title' => \Gcms\Controller::arrayToOptions(Language::get('TITLES', []))
                ]
            ], 'Registration form loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * GET api/enroll/register/plans?level=
     * ตัวเลือกแผนการเรียนของระดับชั้นที่เลือก (ใช้ตอนเปลี่ยนระดับชั้นในฟอร์ม)
     *
     * @param Request $request
     *
     * @return Response
     */
    public function plans(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $level = $request->get('level')->toInt();

            return $this->successResponse(
                \Enroll\Plan\Model::toOptions($level, true),
                'Study plans retrieved'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/enroll/register/save
     * บันทึกใบสมัคร
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
            $canManage = ApiController::hasPermission($login, 'can_manage_enroll');

            $key = $request->post('link')->filter('a-z0-9');
            $enroll = $key === '' ? null : \Enroll\Enroll\Model::get($key);
            if ($key !== '' && !$enroll) {
                return $this->errorResponse('No data available', 404);
            }

            $allowed = $enroll === null
                ? self::canRegister($canManage)
                : self::canEdit($enroll, $canManage);
            if ($allowed !== true) {
                return $this->errorResponse($allowed, 403);
            }

            $id = $enroll ? (int) $enroll->id : 0;

            $save = [
                'level' => $request->post('level')->toInt(),
                'title' => $request->post('title')->toInt(),
                'name' => $request->post('name')->topic(),
                'id_card' => $request->post('id_card')->number(),
                'birthday' => $request->post('birthday')->date(),
                'phone' => $request->post('phone')->number(),
                'email' => $request->post('email')->url(),
                'nationality' => $request->post('nationality')->topic(),
                'religion' => $request->post('religion')->topic(),
                'address' => $request->post('address')->topic(),
                'districtID' => $request->post('districtID')->toInt(),
                'amphurID' => $request->post('amphurID')->toInt(),
                'provinceID' => $request->post('provinceID')->toInt(),
                'zipcode' => $request->post('zipcode')->number(),
                'original_school' => $request->post('original_school')->topic()
            ];

            $errors = [];

            // แผนการเรียนที่เลือก อันดับแรกต้องเลือกเสมอ
            $available = \Enroll\Plan\Model::toArray($save['level'], true);
            $planIds = [];
            foreach ($request->post('plan', [])->toInt() as $no => $plan_id) {
                if ($plan_id > 0 && isset($available[$plan_id])) {
                    $planIds[] = $plan_id;
                } elseif ($no == 0) {
                    $errors['plan[0]'] = 'Please select';
                }
            }
            if (empty($planIds) && !isset($errors['plan[0]'])) {
                $errors['plan[0]'] = 'Please select';
            }

            foreach (['name', 'birthday', 'phone', 'nationality', 'religion', 'address', 'zipcode', 'original_school'] as $field) {
                if (empty($save[$field])) {
                    $errors[$field] = 'Please fill in';
                }
            }
            foreach (['districtID', 'amphurID', 'provinceID'] as $field) {
                if (empty($save[$field])) {
                    // ข้อความผิดพลาดชี้ไปที่ช่องที่ผู้ใช้เห็น ไม่ใช่ hidden ที่เก็บรหัส
                    $errors[str_replace('ID', '', $field)] = 'Please fill in';
                }
            }
            if (!preg_match('/^[0-9]{13}$/', (string) $save['id_card'])) {
                $errors['id_card'] = Language::replace('Invalid :name', [':name' => Language::get('Identification No.')]);
            } elseif (\Enroll\Enroll\Model::idCardExists($save['id_card'], $id)) {
                $errors['id_card'] = Language::replace('This :name already exist', [':name' => Language::get('Identification No.')]);
            }
            if (!\Enroll\Level\Model::exists($save['level'])) {
                $errors['level'] = 'Please select';
            }

            // รูปนักเรียนบังคับเฉพาะใบสมัครใหม่ ใบเดิมไม่อัปโหลดใหม่ = ใช้รูปเดิม
            $uploads = $request->getUploadedFiles();
            $picture = isset($uploads['thumbnail']) ? $uploads['thumbnail'] : null;
            $hasPicture = $picture && $picture->hasUploadFile();
            if ($id === 0 && !$hasPicture) {
                $errors['thumbnail'] = Language::get('Please upload pictures of students');
            } elseif ($picture && !$hasPicture && $picture->hasError()) {
                $errors['thumbnail'] = Language::get($picture->getErrorMessage());
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors);
            }

            $save['parent'] = json_encode(self::readParents($request), JSON_UNESCAPED_UNICODE);
            $save['academic_results'] = json_encode(self::readAcademicResults($request), JSON_UNESCAPED_UNICODE);
            if ($enroll) {
                $save['enroll_no'] = $enroll->enroll_no;
            }

            $id = \Enroll\Enroll\Model::save($id, $save, $planIds);

            // เลขประจำตัวผู้สมัครออกหลังรู้ id แล้ว เพราะ running number ใช้ id ตรวจซ้ำ
            if (empty($save['enroll_no'])) {
                \Kotchasan\DB::create()->update('enroll', ['id', $id], [
                    'enroll_no' => \Enroll\Enroll\Model::makeEnrollNo($id, $save['level'])
                ]);
            }

            // รูปนักเรียน
            $dir = ROOT_PATH.DATA_FOLDER.'enroll/';
            if ($hasPicture) {
                if (!File::makeDirectory($dir)) {
                    $errors['thumbnail'] = Language::replace('Directory %s cannot be created or is read-only.', DATA_FOLDER.'enroll/');
                } else {
                    try {
                        $picture->resizeImage(self::$cfg->img_typies, $dir, $id.self::$cfg->stored_img_type, self::$cfg->enroll_w);
                    } catch (\Exception $exc) {
                        $errors['thumbnail'] = Language::get($exc->getMessage());
                    }
                }
            }

            // ไฟล์แนบ
            \Download\Upload\Model::execute($errors, $request, $id, 'enroll', self::$cfg->enroll_attach_file_typies);

            if (!empty($errors)) {
                return $this->formErrorResponse($errors);
            }

            $enrollRow = \Enroll\Enroll\Model::get($id);

            \Index\Log\Model::add($id, 'enroll', 'Save', Language::get('Registration form').' ID : '.$id, $login ? $login->id : 0);

            // ใบสมัครใหม่ พาไปหน้าผลการสมัครพร้อม link ที่ใช้กลับมาแก้ไขได้
            if ($key === '') {
                return $this->redirectResponse('/enroll-result?id='.$enrollRow->link, 'Saved successfully', 200, 1000);
            }

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/enroll/register/removefile
     * ลบไฟล์แนบของใบสมัคร
     *
     * @param Request $request
     *
     * @return Response
     */
    public function removefile(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            $canManage = ApiController::hasPermission($login, 'can_manage_enroll');

            if ($request->post('action')->filter('a-z') !== 'delete') {
                return $this->errorResponse('Invalid action', 400);
            }

            $key = $request->post('link')->filter('a-z0-9');
            $enroll = $key === '' ? null : \Enroll\Enroll\Model::get($key);
            if (!$enroll) {
                return $this->errorResponse('No data available', 404);
            }
            $allowed = self::canEdit($enroll, $canManage);
            if ($allowed !== true) {
                return $this->errorResponse($allowed, 403);
            }

            // ยอมรับเฉพาะไฟล์ที่อยู่ในไดเร็คทอรี่ของใบสมัครนี้เท่านั้น
            $name = basename($request->post('url')->url());
            $file = ROOT_PATH.DATA_FOLDER.'enroll/'.$enroll->id.'/'.$name;
            if ($name === '' || !is_file($file)) {
                return $this->errorResponse('No data available', 404);
            }
            unlink($file);

            \Index\Log\Model::add($enroll->id, 'enroll', 'Delete', 'Remove attachment: '.$name, $login ? $login->id : 0);

            return $this->successResponse(null, 'Deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ช่วงเวลาที่เปิดรับสมัคร ไม่ได้กำหนดวันไว้ = เปิดตลอด
     *
     * @return bool
     */
    protected static function isOpen()
    {
        if (empty(self::$cfg->enroll_begin) || empty(self::$cfg->enroll_end)) {
            return true;
        }
        $today = time();

        return $today >= self::$cfg->enroll_begin && $today <= self::$cfg->enroll_end;
    }

    /**
     * ตรวจสิทธิ์สมัครใหม่ คืนค่า true หรือข้อความผิดพลาด
     *
     * @param bool $canManage
     *
     * @return true|string
     */
    protected static function canRegister($canManage)
    {
        if ($canManage || self::isOpen()) {
            return true;
        }

        return Language::get('Applications are not open at this time');
    }

    /**
     * ตรวจสิทธิ์แก้ไขใบสมัครเดิม คืนค่า true หรือข้อความผิดพลาด
     *
     * ผู้สมัครแก้ได้เมื่อถือ link, อยู่ในช่วงรับสมัคร และสถานะยังแก้ไขได้
     * ผู้ดูแลไม่ติดเรื่องช่วงเวลา แต่ยังติดเรื่องสถานะเหมือนระบบเดิม
     *
     * @param object $enroll
     * @param bool $canManage
     *
     * @return true|string
     */
    protected static function canEdit($enroll, $canManage)
    {
        $editable = self::$cfg->enroll_editable;
        if (!is_array($editable)) {
            $editable = [];
        }
        if (!in_array((int) $enroll->result_status, array_map('intval', $editable), true)) {
            return Language::get('This item cannot be edited');
        }
        if ($canManage || self::isOpen()) {
            return true;
        }

        return Language::get('Applications are not open at this time');
    }

    /**
     * ช่องเลือกแผนการเรียน ตามจำนวนที่ตั้งค่าไว้
     *
     * @param array $choices
     *
     * @return array
     */
    protected static function planFields($choices)
    {
        $count = max(1, (int) self::$cfg->enroll_study_plan_count);
        $values = array_values($choices);
        $fields = [];
        for ($i = 0; $i < $count; $i++) {
            $fields[] = [
                'no' => $i,
                'label' => Language::get('Study plan').($count === 1 ? '' : ' '.($i + 1)),
                'value' => isset($values[$i]) ? (string) $values[$i] : ''
            ];
        }

        return $fields;
    }

    /**
     * ช่องกรอกข้อมูลผู้ปกครอง ตามรายการภาษา PARENT_LIST
     *
     * @param object|null $enroll
     *
     * @return array
     */
    protected static function parentFields($enroll)
    {
        $saved = $enroll && !empty($enroll->parent) ? json_decode($enroll->parent, true) : [];
        if (!is_array($saved)) {
            $saved = [];
        }
        $fields = [];
        foreach (Language::get('PARENT_LIST', []) as $key => $label) {
            $fields[] = [
                'key' => $key,
                'label' => $label,
                'name' => isset($saved[$key]['name']) ? $saved[$key]['name'] : '',
                'phone' => isset($saved[$key]['phone']) ? $saved[$key]['phone'] : '',
                'comment' => $key === 'parent' ? Language::get('If living with someone other than the parent while studying') : ''
            ];
        }

        return $fields;
    }

    /**
     * ช่องกรอกผลการเรียน ตามรายการภาษา ACADEMIC_RESULTS
     *
     * @param object|null $enroll
     *
     * @return array
     */
    protected static function academicFields($enroll)
    {
        $saved = $enroll && !empty($enroll->academic_results) ? json_decode($enroll->academic_results, true) : [];
        if (!is_array($saved)) {
            $saved = [];
        }
        $fields = [];
        foreach (Language::get('ACADEMIC_RESULTS', []) as $key => $label) {
            $fields[] = [
                'key' => $key,
                'label' => $label,
                'value' => isset($saved[$key]) ? $saved[$key] : ''
            ];
        }

        return $fields;
    }

    /**
     * รูปนักเรียนในรูปแบบที่ช่องอัปโหลดไฟล์เข้าใจ
     *
     * @param object|null $enroll
     *
     * @return array
     */
    protected static function thumbnailField($enroll)
    {
        $url = $enroll ? \Enroll\Enroll\Model::pictureUrl($enroll->id) : null;
        if ($url === null) {
            return [];
        }

        return [
            [
                'url' => $url,
                'name' => Language::get('Picture of student')
            ]
        ];
    }

    /**
     * อ่านข้อมูลผู้ปกครองจากฟอร์ม
     *
     * @param Request $request
     *
     * @return array
     */
    protected static function readParents(Request $request)
    {
        $names = $request->post('parent_name', [])->topic();
        $phones = $request->post('parent_phone', [])->number();
        $result = [];
        foreach (Language::get('PARENT_LIST', []) as $key => $label) {
            $result[$key] = [
                'name' => isset($names[$key]) ? $names[$key] : '',
                'phone' => isset($phones[$key]) ? $phones[$key] : ''
            ];
        }

        return $result;
    }

    /**
     * อ่านผลการเรียนจากฟอร์ม (สูงสุด 100 เหมือนระบบเดิม)
     *
     * @param Request $request
     *
     * @return array
     */
    protected static function readAcademicResults(Request $request)
    {
        $values = $request->post('academic', [])->toFloat();
        $result = [];
        foreach (Language::get('ACADEMIC_RESULTS', []) as $key => $label) {
            $result[$key] = isset($values[$key]) ? min(100, $values[$key]) : 0;
        }

        return $result;
    }
}
