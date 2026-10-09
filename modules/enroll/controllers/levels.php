<?php
/**
 * @filesource modules/enroll/controllers/levels.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Levels;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * API จัดการระดับชั้นที่เปิดรับสมัคร (ตารางแก้ไขในที่)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/levels/get
     * อ่านระดับชั้นทั้งหมดสำหรับตารางแก้ไข
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

            $data = [];
            foreach (\Enroll\Level\Model::all() as $item) {
                $data[] = [
                    // __rowKey กำหนดชื่อ input ที่ตารางสร้าง (topic[<key>]) และเป็น
                    // คีย์ที่ save() ใช้จับคู่คอลัมน์ในแถวเดียวกัน ระบุเองเพื่อให้
                    // ข้อความผิดพลาดชี้กลับมาที่ช่องเดิมได้
                    '__rowKey' => (string) $item->id,
                    'id' => (int) $item->id,
                    'topic' => $item->topic,
                    'is_active' => (int) $item->is_active
                ];
            }
            // ตารางว่างให้แถวเปล่าไว้ 1 แถวเหมือนระบบเดิม จะได้กรอกได้ทันที
            if (empty($data)) {
                $data[] = [
                    '__rowKey' => '1',
                    'id' => 1,
                    'topic' => '',
                    'is_active' => 1
                ];
            }

            return $this->successResponse([
                'data' => [
                    // ใช้ชื่อ 'table' ไม่ใช่ 'options' เพราะ FormManager อ่าน data.options
                    // เป็นตัวเลือกของ select (data-options-key) ถ้าใช้ชื่อเดียวกัน
                    // คอลัมน์ของตารางจะถูกเข้าใจผิดว่าเป็นตัวเลือก
                    'table' => [
                        'columns' => self::getColumns(),
                        'data' => $data
                    ]
                ]
            ], 'Education levels retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/enroll/levels/save
     * บันทึกระดับชั้นทั้งชุด
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

            $ids = $request->post('id', [])->toInt();
            $topics = $request->post('topic', [])->topic();
            $actives = $request->post('is_active', [])->toInt();

            $errors = [];
            $rows = [];
            $seen = [];
            foreach ($ids as $key => $id) {
                $topic = isset($topics[$key]) ? $topics[$key] : '';
                if ($id < 1) {
                    $errors['id['.$key.']'] = Language::replace('Invalid :name', [':name' => Language::get('ID')]);
                } elseif (isset($seen[$id])) {
                    $errors['id['.$key.']'] = Language::replace('This :name already exist', [':name' => 'ID']);
                } else {
                    $seen[$id] = true;
                }
                if ($topic === '') {
                    $errors['topic['.$key.']'] = 'Please fill in';
                }
                if (empty($errors)) {
                    $rows[] = [
                        'id' => $id,
                        'topic' => $topic,
                        'is_active' => empty($actives[$key]) ? 0 : 1
                    ];
                }
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors);
            }

            // ระดับชั้นที่ถูกเอาออกจากตาราง จะถูกลบพร้อมแผนการเรียนของระดับนั้น
            // ถ้ายังมีผู้สมัครอยู่ ต้องหยุดไว้ก่อน มิฉะนั้นใบสมัครจะกลายเป็นระดับที่ไม่มีอยู่จริง
            $removed = array_diff(array_keys(\Enroll\Level\Model::toArray()), array_keys($seen));
            $inUse = \Enroll\Level\Model::inUse($removed);
            if (!empty($inUse)) {
                $names = \Enroll\Level\Model::toArray();
                $messages = [];
                foreach ($inUse as $id => $count) {
                    $messages[] = ($names[$id] ?? $id).' ('.$count.')';
                }

                return $this->errorResponse(Language::replace('Unable to delete :name because it is in use', [
                    ':name' => implode(', ', $messages)
                ]), 400);
            }

            \Enroll\Level\Model::save($rows);

            \Index\Log\Model::add(0, 'enroll', 'Save', 'Education levels saved ('.count($rows).' rows)', $login->id);

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * คอลัมน์ของตารางแก้ไข
     *
     * @return array
     */
    protected static function getColumns()
    {
        return [
            [
                'field' => 'id',
                'label' => '{LNG_ID}',
                'cellElement' => 'number',
                'min' => 1,
                'size' => 5
            ],
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
