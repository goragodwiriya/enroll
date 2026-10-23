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
 * เปิดให้ผู้ที่ไม่ใช่สมาชิกใช้งานได้ กุญแจเข้าถึงใบสมัครเดิมคือ link 32 ตัวอักษรที่สุ่มตอนสมัคร
 * ทุกคำขอที่นี่ใช้กติกาของผู้สมัครเสมอ (ช่วงรับสมัคร จำนวนที่รับ กันสแปม) แม้ผู้ที่เข้าระบบไว้จะเป็นเจ้าหน้าที่
 * งานของเจ้าหน้าที่อยู่ที่ \Enroll\Enroll\Controller (api/enroll/enroll) ซึ่งสืบทอดคลาสนี้
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * จำนวนใบสมัครสูงสุดต่อเบอร์โทรศัพท์ (เผื่อพี่น้องใช้เบอร์ผู้ปกครองร่วมกัน)
     * ปรับได้ด้วย enroll_phone_limit ใน settings/config.php (0 = ไม่จำกัด)
     */
    const PHONE_LIMIT = 5;

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
            $canManage = static::isStaff($login);

            $key = $request->get('id')->filter('a-z0-9');
            $enroll = $key === '' ? null : \Enroll\Enroll\Model::get($key);

            if ($key !== '' && !$enroll) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $allowed = $enroll === null
                ? self::canRegister($canManage)
                : self::canEdit($enroll, $canManage);
            if ($allowed !== true) {
                // ปิดรับสมัคร หรือใบสมัครแก้ไขไม่ได้แล้ว: แสดงข้อความในหน้านี้แทนฟอร์ม
                // ไม่ตอบ 403 เพราะ FormManager จะพาไปหน้า /403 ซึ่งต้องเข้าระบบ
                // ผู้สมัครที่ไม่ใช่สมาชิกจึงถูกส่งไปหน้า login
                // array_merge ไม่ใช่ + : schoolInfo() มี closed_message ของตัวเอง (เหตุที่ไม่รับใบใหม่)
                // ข้อความเฉพาะของคำขอนี้ต้องทับ
                return $this->successResponse([
                    'data' => array_merge(self::schoolInfo(), [
                        'closed' => true,
                        'closed_message' => $allowed,
                        'enroll_no' => $enroll ? $enroll->enroll_no : '',
                        'result_url' => $enroll ? '/enroll-result?id='.$enroll->link : '/enroll-result'
                    ])
                ], $allowed);
            }

            return $this->successResponse($this->formPayload($request, $enroll, $canManage), 'Registration form loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ข้อมูลและตัวเลือกของแบบฟอร์มใบสมัคร (ใช้ร่วมกับหน้าของเจ้าหน้าที่)
     *
     * @param Request $request
     * @param object|null $enroll ใบสมัครเดิม หรือ null = ใบใหม่
     * @param bool $canManage กติกาของเจ้าหน้าที่ (เลือกระดับชั้น/แผนที่เต็มได้)
     *
     * @return array ['data' => [...], 'options' => [...]]
     */
    protected function formPayload(Request $request, $enroll, $canManage)
    {
        // ใบใหม่: ระดับชั้นที่เลือกจากการ์ดหน้าแรก (?level=) ถ้ายังรับอยู่ ไม่งั้นระดับชั้นแรกที่ยังรับสมัคร
        $level = $enroll ? (int) $enroll->level : \Enroll\Plan\Model::firstOpenLevelId();
        $requested = $enroll ? 0 : $request->get('level')->toInt();
        if ($requested > 0 && isset(\Enroll\Level\Model::toArray(true)[$requested])
            && ($canManage || !isset(\Enroll\Plan\Model::fullLevels()[$requested]))) {
            $level = $requested;
        }
        $choices = $enroll ? \Enroll\Enroll\Model::choices($enroll->id) : [];
        // ระดับชั้นที่รับครบแล้วขึ้นว่า (เต็ม) ผู้สมัครเลือกไม่ได้ เจ้าหน้าที่ยังเลือกได้ (บันทึกเกินจำนวนได้)
        $levelOptions = \Enroll\Plan\Model::levelOptions($enroll ? (int) $enroll->level : 0, $enroll ? (int) $enroll->id : 0);
        $hasFullLevel = false;
        foreach ($levelOptions as $i => $option) {
            if (!empty($option['full'])) {
                $hasFullLevel = true;
                $levelOptions[$i]['disabled'] = !$canManage;
            }
        }

        $data = array_merge(self::schoolInfo(), [
            'closed' => false,
            'closed_message' => '',
            // รหัสของการเปิดแบบฟอร์มครั้งนี้ (กันสแปม ใช้บันทึกใบสมัครใหม่ได้ใบเดียว) — เฉพาะใบใหม่ของผู้สมัคร
            'form_token' => $enroll || $canManage ? '' : \Enroll\Formguard\Model::issue(),
            // ตรวจหลักตรวจสอบของเลขบัตรประชาชนไทยฝั่งเบราว์เซอร์ด้วย
            'id_card_checksum' => self::checksumEnabled(),
            'id' => $enroll ? (int) $enroll->id : 0,
            'link' => $enroll ? $enroll->link : '',
            'enroll_no' => $enroll ? $enroll->enroll_no : '',
            // ใบสมัครเดิมพิมพ์และดูผลได้จากหน้านี้เลย ไม่ต้องย้อนไปหน้าผลการสมัคร
            'print_url' => $enroll ? WEB_URL.'api/enroll/printform?id='.$enroll->link : '',
            'result_url' => $enroll ? '/enroll-result?id='.$enroll->link : '',
            // แทน :type ในคำแนะนำใต้ช่องรูปนักเรียน
            'img_types' => implode(', ', self::$cfg->img_typies),
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
            'has_full_level' => $hasFullLevel,
            // ส่วนที่จำนวนช่องขึ้นกับค่ากำหนดและรายการภาษา สร้างโดย initEnrollRegister
            'plan_fields' => self::planFields($choices),
            'parent_fields' => self::parentFields($enroll),
            'academic_fields' => self::academicFields($enroll),
            'attach_accept' => implode(',', self::$cfg->enroll_attach_file_typies),
            'thumbnail' => self::thumbnailField($enroll),
            'attachments' => $enroll ? \Enroll\Enroll\Model::attachments($enroll->id) : []
        ]);

        return [
            'data' => $data,
            'options' => [
                'levels' => $levelOptions,
                // แผนที่รับครบแล้วเลือกเป็นอันดับแรกไม่ได้ (คงแผนเดิมของใบนี้ไว้)
                'plans' => \Enroll\Plan\Model::registerOptions($level, (int) (array_values($choices)[0] ?? 0), $enroll ? (int) $enroll->id : 0),
                'title' => \Gcms\Controller::arrayToOptions(Language::get('TITLES', []))
            ]
        ];
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
                \Enroll\Plan\Model::registerOptions($level),
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
            $canManage = static::isStaff($login);

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

            // กันสแปมใบสมัครใหม่จากผู้สมัคร โดยไม่ใช้ captcha และไม่อิง IP
            // (ผู้สมัครมักกรอกที่เครื่องของโรงเรียน IP และเบราว์เซอร์เดียวกัน)
            // รหัสแบบฟอร์มที่ปลอม หมดอายุ หรือใช้ไปแล้ว = ไม่ได้มาจากแบบฟอร์มจริง ปฏิเสธ
            // ช่องดักบอตถูกกรอก หรือส่งเร็วผิดธรรมชาติ = อาจผิดพลาดได้ บันทึกแต่ติดป้ายให้ตรวจ
            $guard = null;
            $review = [];
            if ($id === 0 && !$canManage) {
                $guard = \Enroll\Formguard\Model::check($request->post('form_token')->toString());
                if ($guard['error'] === 'used') {
                    return $this->errorResponse(Language::get('This form has already been submitted, please open a new form'), 400);
                }
                if ($guard['error'] !== null) {
                    return $this->errorResponse(Language::get('This form has expired, please reload the page'), 400);
                }
                if ($request->post('website')->toString() !== '') {
                    $review[] = 'honeypot';
                }
                if ($guard['elapsed'] < \Enroll\Formguard\Model::MIN_SECONDS) {
                    $review[] = 'fast';
                }
            }

            $save = [
                'level' => $request->post('level')->toInt(),
                'title' => $request->post('title')->toInt(),
                'name' => $request->post('name')->topic(),
                'id_card' => $request->post('id_card')->number(),
                'birthday' => $request->post('birthday')->date(),
                'phone' => $request->post('phone')->number(),
                // ->url() ของ Kotchasan ตรวจรูปแบบ URL อีเมลจึงกลายเป็นค่าว่างทุกครั้ง
                'email' => $request->post('email')->email(),
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
            // ระดับชั้นที่รับครบทุกแผนแล้วสมัครไม่ได้ ใบเดิมคงระดับชั้นเดิมได้ เจ้าหน้าที่ไม่ติด
            $levelFull = !$canManage && ($enroll === null || (int) $enroll->level !== (int) $save['level'])
                && isset(\Enroll\Plan\Model::fullLevels($id)[(int) $save['level']]);
            if ($levelFull) {
                $errors['level'] = Language::get('This education level is full');
            }
            // แผนอันดับแรกที่รับครบแล้วเลือกไม่ได้ ใบเดิมคงแผนที่เลือกไว้ได้ เจ้าหน้าที่ไม่ติด
            // (ระดับชั้นเต็มแจ้งที่ระดับชั้นแล้ว ไม่ต้องแจ้งซ้ำที่แผน)
            if (!$canManage && !$levelFull && !empty($planIds) && !isset($errors['plan[0]'])) {
                $oldFirst = $enroll ? (int) (array_values(\Enroll\Enroll\Model::choices($enroll->id))[0] ?? 0) : 0;
                if ((int) $planIds[0] !== $oldFirst && isset(\Enroll\Plan\Model::fullPlans($save['level'], $id)[(int) $planIds[0]])) {
                    $errors['plan[0]'] = Language::get('This study plan is full');
                }
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
            $idCardChanged = $enroll === null || (string) $save['id_card'] !== (string) $enroll->id_card;
            if (!preg_match('/^[0-9]{13}$/', (string) $save['id_card'])) {
                $errors['id_card'] = Language::replace('Invalid :name', [':name' => Language::get('Identification No.')]);
            } elseif ($idCardChanged && self::checksumEnabled() && !\Enroll\Enroll\Model::validThaiId($save['id_card'])) {
                // ตรวจเฉพาะเลขที่เพิ่งกรอก ใบเดิมที่ย้ายมาจากระบบเดิมยังบันทึกต่อได้
                $errors['id_card'] = Language::replace('Invalid :name', [':name' => Language::get('Identification No.')]);
            } elseif (\Enroll\Enroll\Model::idCardExists($save['id_card'], $id)) {
                $errors['id_card'] = Language::replace('This :name already exist', [':name' => Language::get('Identification No.')]);
            }
            $phoneLimit = isset(self::$cfg->enroll_phone_limit) ? (int) self::$cfg->enroll_phone_limit : self::PHONE_LIMIT;
            if (!$canManage && $phoneLimit > 0 && !empty($save['phone'])
                && ($enroll === null || (string) $save['phone'] !== (string) $enroll->phone)
                && \Enroll\Enroll\Model::countByPhone($save['phone'], $id) >= $phoneLimit) {
                $errors['phone'] = Language::replace('This phone number has already been used for :count applications', [':count' => $phoneLimit]);
            }
            if ($save['email'] !== '' && filter_var($save['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = Language::replace('Invalid :name', [':name' => Language::get('Email')]);
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

            // ยินยอมให้เก็บและใช้ข้อมูลส่วนบุคคล (PDPA) ก่อนส่งใบสมัครใหม่
            // เจ้าหน้าที่กรอกแทนจากใบสมัครกระดาษได้โดยไม่ติ๊ก (ความยินยอมอยู่ที่เอกสาร)
            $consent = $request->post('consent')->toInt() === 1;
            if ($id === 0 && !$canManage && !$consent) {
                $errors['consent'] = Language::get('Please accept the privacy notice');
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors);
            }

            $save['parent'] = json_encode(self::readParents($request), JSON_UNESCAPED_UNICODE);
            $save['academic_results'] = json_encode(self::readAcademicResults($request), JSON_UNESCAPED_UNICODE);
            if ($enroll) {
                $save['enroll_no'] = $enroll->enroll_no;
            }

            if ($guard) {
                $save['form_nonce'] = $guard['nonce'];
                $save['review'] = implode(',', $review);
            }
            if ($id === 0 && $consent) {
                $save['consent_at'] = date('Y-m-d H:i:s');
            }
            try {
                $id = \Enroll\Enroll\Model::save($id, $save, $planIds);
            } catch (\Exception $e) {
                // ส่งแบบฟอร์มเดียวกันพร้อมกันสองครั้ง ดัชนี UNIQUE ของ form_nonce รับไว้ใบเดียว
                if ($guard && \Enroll\Formguard\Model::used($guard['nonce'])) {
                    return $this->errorResponse(Language::get('This form has already been submitted, please open a new form'), 400);
                }
                throw $e;
            }

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
                $message = Language::get('Saved successfully');
                // ส่งลิงก์ใบสมัครให้ผู้สมัครเก็บไว้ ใบที่ระบบกันสแปมติดป้ายไม่ส่ง
                // (กันคนใช้ฟอร์มนี้ส่งอีเมล/SMS ไปหาผู้อื่น)
                if ($enrollRow->review === '') {
                    $sent = \Enroll\Notify\Model::applicationSaved($enrollRow);
                    if ($sent['email'] === true) {
                        $message .= ' '.Language::replace('The application link has been sent to :email', [':email' => $enrollRow->email]);
                    }
                }

                return $this->savedResponse($enrollRow, $message);
            }

            return $this->savedResponse($enrollRow, Language::get('Saved successfully'));
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
            $canManage = static::isStaff($login);

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
     * ใช้กติกาของเจ้าหน้าที่หรือไม่ — หน้าของผู้สมัครไม่ใช้เสมอ (\Enroll\Enroll\Controller เปลี่ยนเป็นตรวจสิทธิ์)
     *
     * @param object|null $login
     *
     * @return bool
     */
    protected static function isStaff($login)
    {
        return false;
    }

    /**
     * ตอบกลับเมื่อบันทึกสำเร็จ: ผู้สมัครไปหน้าผลการสมัคร/พิมพ์ ของใบนั้น
     * (ทั้งใบใหม่และแก้ไข — reload จะพาฟอร์มทีละขั้นกลับไปขั้นแรก ดูเหมือนไม่มีอะไรเกิดขึ้น)
     *
     * @param object $enroll ใบสมัครที่เพิ่งบันทึก
     * @param string $message
     *
     * @return Response
     */
    protected function savedResponse($enroll, $message)
    {
        return $this->redirectResponse('/enroll-result?id='.$enroll->link, $message, 200, 1000);
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
        $closed = self::closedReason();

        return $canManage || $closed === null ? true : $closed;
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
     * ตรวจหลักตรวจสอบของเลขประจำตัวประชาชนไทย เฉพาะโรงเรียนในประเทศไทย
     * (enroll_country = LA ใช้เลขบัตรแบบอื่น)
     *
     * @return bool
     */
    protected static function checksumEnabled()
    {
        return strtoupper((string) self::$cfg->enroll_country) === 'TH';
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
