<?php
/**
 * @filesource modules/enroll/controllers/plans.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Plans;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * API จัดการแผนการเรียนของแต่ละระดับชั้น (ตารางแก้ไขในที่)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/plans/get
     * อ่านแผนการเรียนของระดับชั้นที่เลือก
     *
     * @param Request $request
     *
     * @return Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_manage_enroll')) {
                return $this->errorResponse('Permission required', 403);
            }

            $level = $request->get('level')->toInt();
            if (!\Enroll\Level\Model::exists($level)) {
                $level = \Enroll\Level\Model::firstId();
            }

            $data = [];
            foreach (\Enroll\Plan\Model::byLevel($level) as $item) {
                $data[] = [
                    // แผนการเรียนใช้ id ที่ระบบออกให้ จึงไม่มีคอลัมน์ id ในตาราง
                    // ใส่ id ไว้ใน __rowKey แทน ตารางจะตั้งชื่อ input เป็น topic[<id>]
                    // ทำให้ save() รู้ว่าแถวไหนคือแผนเดิม (คีย์ตัวเลข) แถวไหนเพิ่งเพิ่ม (row_N)
                    '__rowKey' => (string) $item->id,
                    'topic' => $item->topic,
                    'is_active' => (int) $item->is_active
                ];
            }
            if (empty($data)) {
                $data[] = [
                    'topic' => '',
                    'is_active' => 1
                ];
            }

            return $this->successResponse([
                'data' => [
                    'level' => (string) $level,
                    // ใช้ชื่อ 'table' ไม่ใช่ 'options' เพราะ FormManager อ่าน data.options
                    // เป็นตัวเลือกของ select (data-options-key) — ที่นี่ต้องใช้ทั้งสองอย่าง
                    // คือตัวเลือกระดับชั้น (root options) และคอลัมน์ของตาราง
                    'table' => [
                        'columns' => self::getColumns(),
                        'data' => $data
                    ]
                ],
                'options' => [
                    'levels' => \Enroll\Level\Model::toOptions()
                ]
            ], 'Study plans retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/enroll/plans/save
     * บันทึกแผนการเรียนของระดับชั้นหนึ่ง
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

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_manage_enroll'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $level = $request->post('level')->toInt();
            if (!\Enroll\Level\Model::exists($level)) {
                return $this->errorResponse('No data available', 404);
            }

            $topics = $request->post('topic', [])->topic();
            $actives = $request->post('is_active', [])->toInt();

            $errors = [];
            $rows = [];
            $kept = [];
            foreach ($topics as $key => $topic) {
                if ($topic === '') {
                    $errors['topic['.$key.']'] = 'Please fill in';
                    continue;
                }
                // คีย์ที่เป็นตัวเลขคือ id ของแผนเดิม คีย์อื่น (row_N) คือแถวที่เพิ่งเพิ่ม
                $id = ctype_digit((string) $key) ? (int) $key : 0;
                if ($id > 0) {
                    $kept[$id] = true;
                }
                $rows[] = [
                    'id' => $id,
                    'topic' => $topic,
                    'is_active' => empty($actives[$key]) ? 0 : 1
                ];
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors);
            }

            // แผนที่ถูกเอาออกจากตาราง ต้องไม่มีใบสมัครใดอ้างถึงอยู่
            // ถ้าต้องการหยุดใช้แผนโดยไม่ลบ ให้ปิดสวิตช์ใช้งานแทน
            $removed = array_diff(array_keys(\Enroll\Plan\Model::toArray($level)), array_keys($kept));
            $inUse = \Enroll\Plan\Model::inUse($removed);
            if (!empty($inUse)) {
                $names = \Enroll\Plan\Model::toArray($level);
                $messages = [];
                foreach ($inUse as $id => $count) {
                    $messages[] = ($names[$id] ?? $id).' ('.$count.')';
                }

                return $this->errorResponse(Language::replace('Unable to delete :name because it is in use', [
                    ':name' => implode(', ', $messages)
                ]), 400);
            }

            \Enroll\Plan\Model::save($level, $rows);

            \Index\Log\Model::add(0, 'enroll', 'Save', 'Study plans saved for level '.$level.' ('.count($rows).' rows)', $login->id);

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * คอลัมน์ของตารางแก้ไข
     * แผนการเรียนใช้ id ที่ระบบออกให้ ผู้ใช้จึงไม่ต้องกรอกเอง (ต่างจากระดับชั้น)
     *
     * @return array
     */
    protected static function getColumns()
    {
        return [
            [
                'field' => 'topic',
                'label' => '{LNG_Detail}',
                'cellElement' => 'text',
                'maxLength' => 150
            ],
            [
                'field' => 'is_active',
                'label' => '{LNG_Active}',
                'cellElement' => 'switch'
            ]
        ];
    }
}
