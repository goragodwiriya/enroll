<?php
/**
 * @filesource modules/enroll/controllers/enrolls.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Enrolls;

use Gcms\Api as ApiController;
use Kotchasan\Database\Sql;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * API ตารางจัดการผู้สมัคร (สำหรับผู้ดูแล)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Tablebase\Controller
{
    /**
     * คอลัมน์ที่เรียงลำดับได้
     *
     * @var array
     */
    protected $allowedSortColumns = ['enroll_no', 'created_at', 'name', 'academic_value', 'result_plan', 'result_status'];

    /**
     * เฉพาะผู้ที่จัดการการรับสมัครได้
     *
     * @param Request $request
     * @param object $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, 'can_manage_enroll')) {
            return $this->errorResponse('Forbidden', 403);
        }

        return true;
    }

    /**
     * GET api/enroll/enrolls/summary
     * จำนวนผู้สมัครแยกตามระดับชั้น
     *
     * ระบบเดิมแสดงเป็นการ์ดบนหน้าแรก แต่หน้าแรกของ adminframework เป็นไฟล์กลาง
     * ที่โมดูลเสียบการ์ดเข้าไปไม่ได้ จึงย้ายมาไว้หัวหน้ารายการผู้สมัครแทน
     *
     * @param Request $request
     *
     * @return Response
     */
    public function summary(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $counts = \Enroll\Enroll\Model::countByLevel();
            $cards = [];
            $total = 0;
            foreach (\Enroll\Level\Model::all() as $level) {
                $count = $counts[$level->id] ?? 0;
                $total += $count;
                $cards[] = [
                    'id' => (int) $level->id,
                    'topic' => $level->topic,
                    'count' => $count,
                    'count_text' => number_format($count),
                    'url' => '/enroll?level='.$level->id
                ];
            }

            return $this->successResponse([
                'data' => [
                    'levels' => $cards,
                    'total' => $total,
                    'total_text' => number_format($total)
                ]
            ], 'Summary retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ตัวกรองเพิ่มเติมของตาราง
     *
     * ตารางแสดงทีละระดับชั้นเสมอ เพราะแผนการเรียนของแต่ละระดับเป็นคนละชุดกัน
     * ไม่ระบุมา (หรือระบุระดับที่ไม่มีอยู่) ให้ใช้ระดับแรก
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        $level = $request->get('level')->toInt();
        if (!\Enroll\Level\Model::exists($level)) {
            $level = \Enroll\Level\Model::firstId();
        }

        $academic = array_keys(Language::get('ACADEMIC_RESULTS', []));
        $result = $request->get('result')->filter('a-zA-Z0-9_');
        if (!in_array($result, $academic, true)) {
            $result = empty($academic) ? '' : $academic[0];
        }

        // ตัวกรองแผน/สถานะมีตัวเลือก "ทั้งหมด" เป็นค่าว่าง
        // (TableManager คงตัวเลือกแรกของ select ไว้ได้ก็ต่อเมื่อ value เป็นค่าว่าง)
        $status = $request->get('status')->toString();

        return [
            'level' => $level,
            'plan' => $request->get('plan')->toInt(),
            'status' => $status === '' ? -1 : (int) $status,
            'result' => $result
        ];
    }

    /**
     * เงื่อนไขการกรอง (ใช้กับการนับจำนวนด้วย)
     *
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable(array $params, $login = null)
    {
        $where = [
            ['E.level', (int) $params['level']]
        ];
        if (!empty($params['plan'])) {
            $where[] = ['E.result_plan', (int) $params['plan']];
        }
        if (isset($params['status']) && $params['status'] > -1) {
            $where[] = ['E.result_status', (int) $params['status']];
        }

        $query = \Kotchasan\Model::createQuery()
            ->select('E.*')
            ->from('enroll E')
            ->where($where);

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['E.name', 'LIKE', $search],
                ['E.enroll_no', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }

    /**
     * Query สำหรับดึงข้อมูลแสดงผล
     *
     * @param \Kotchasan\QueryBuilder\QueryBuilderInterface $inner
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toJoinQuery($inner, array $params, $login)
    {
        $query = \Kotchasan\Model::createQuery()
            ->select(
                'E.id',
                'E.link',
                'E.enroll_no',
                'E.created_at',
                'E.name',
                'E.result_plan',
                'E.result_status',
                'E.academic_results',
                Sql::GROUP_CONCAT(['C.plan_id'], 'plan_ids', ',', false, 'C.no')
            )
            ->from([$inner, 'E'])
            ->join('enroll_choices C', [['C.enroll_id', 'E.id']], 'LEFT');

        // ค่าผลการเรียนที่เลือกดู ดึงออกมาเป็นคอลัมน์เพื่อให้เรียงลำดับด้วย SQL ได้
        // (ระบบเดิมเรียงจากข้อความ JSON ทั้งก้อน ซึ่งไม่ได้ลำดับที่มีความหมาย)
        if ($params['result'] !== '') {
            // ใส่ชื่อคีย์ลงใน SQL ตรง ๆ ไม่ผูกเป็นพารามิเตอร์ เพราะ QueryBuilder
            // ใช้ named parameter ให้ WHERE อยู่แล้ว การผสม ? เข้าไปทำให้ PDO ปฏิเสธทั้ง statement
            // ค่านี้ปลอดภัย: getCustomParams() กรองเหลือ a-zA-Z0-9_ และต้องเป็นคีย์ที่มีอยู่ใน ACADEMIC_RESULTS
            $query->selectRaw(
                'CAST(JSON_UNQUOTE(JSON_EXTRACT(E.`academic_results`, \'$."'.$params['result'].'"\')) AS DECIMAL(10,2)) AS `academic_value`'
            );
        } else {
            $query->selectRaw('NULL AS `academic_value`');
        }

        return $query->groupBy('E.id');
    }

    /**
     * จัดรูปแบบข้อมูลก่อนส่งให้ตาราง
     *
     * @param array $datas
     * @param object $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        $plans = \Enroll\Plan\Model::toArrayAll();
        $status = Language::get('REGISTER_STATUS', []);

        $result = [];
        foreach ($datas as $row) {
            $row->barcode = 'data:image/png;base64,'.base64_encode(\Enroll\Enroll\Model::barcodePng($row->enroll_no));
            $row->created_at_text = Date::format($row->created_at, 'd M Y');

            $names = [];
            foreach (explode(',', (string) $row->plan_ids) as $plan_id) {
                if (isset($plans[$plan_id])) {
                    $names[$plan_id] = $plans[$plan_id];
                }
            }
            $row->plan_text = implode(', ', $names);
            // ตัวเลือกแผนของแถวนี้ = เฉพาะแผนที่ผู้สมัครคนนี้เลือกไว้ (เหมือนระบบเดิม)
            $row->plan_options = $names;
            $row->result_status_text = $status[$row->result_status] ?? '';
            $row->academic_value = $row->academic_value === null ? '' : (float) $row->academic_value;
            $row->print_url = WEB_URL.'api/enroll/printform?id='.$row->link;
            $row->edit_url = '/enroll-edit?id='.$row->link;
            unset($row->academic_results);

            $result[] = $row;
        }

        return $result;
    }

    /**
     * ตัวเลือกของตัวกรองด้านบนตาราง
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters(array $params, $login = null)
    {
        return [
            'level' => \Enroll\Level\Model::toOptions(),
            'plan' => \Enroll\Plan\Model::toOptions($params['level']),
            'status' => \Gcms\Controller::arrayToOptions(Language::get('REGISTER_STATUS', [])),
            'result' => \Gcms\Controller::arrayToOptions(Language::get('ACADEMIC_RESULTS', []))
        ];
    }

    /**
     * ตัวเลือกที่คอลัมน์ในตารางใช้
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getOptions(array $params, $login)
    {
        return [
            'result_status' => \Gcms\Controller::arrayToOptions(Language::get('REGISTER_STATUS', [])),
            'result_plan' => \Enroll\Plan\Model::toOptions($params['level']),
            // หัวคอลัมน์ผลการเรียนเปลี่ยนตามรายการที่เลือกดู
            'academic_label' => Language::get('ACADEMIC_RESULTS', [])[$params['result']] ?? Language::get('Academic result')
        ];
    }

    /**
     * ลบใบสมัครที่เลือก
     *
     * @param Request $request
     * @param object $login
     *
     * @return Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_enroll'])) {
            return $this->errorResponse('Permission required', 403);
        }

        $ids = $request->post('ids', [])->toInt();
        if (empty($ids)) {
            $id = $request->post('id')->toInt();
            $ids = $id > 0 ? [$id] : [];
        }
        if (empty($ids)) {
            return $this->errorResponse('No items selected', 400);
        }

        $count = \Enroll\Enroll\Model::remove($ids);

        \Index\Log\Model::add(0, 'enroll', 'Delete', 'Delete enroll ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$count.' item(s) successfully', 200, 0, 'table');
    }

    /**
     * เปลี่ยนแผนการเรียนที่ได้รับของผู้สมัครหนึ่งคน
     *
     * @param Request $request
     * @param object $login
     *
     * @return Response
     */
    protected function handlePlanAction(Request $request, $login)
    {
        return $this->updateColumn($request, $login, 'result_plan');
    }

    /**
     * เปลี่ยนผลการสมัครของผู้สมัครหนึ่งคน
     *
     * @param Request $request
     * @param object $login
     *
     * @return Response
     */
    protected function handleStatusAction(Request $request, $login)
    {
        return $this->updateColumn($request, $login, 'result_status');
    }

    /**
     * อัปเดตคอลัมน์เดียวของแถวเดียว ใช้ร่วมกันระหว่าง plan และ status
     *
     * @param Request $request
     * @param object $login
     * @param string $column
     *
     * @return Response
     */
    protected function updateColumn(Request $request, $login, $column)
    {
        if (!ApiController::canModify($login, ['can_manage_enroll'])) {
            return $this->errorResponse('Permission required', 403);
        }

        $id = $request->post('id')->toInt();
        $value = $request->post('value')->toInt();
        if ($id < 1) {
            return $this->errorResponse('No items selected', 400);
        }

        $enroll = \Kotchasan\DB::create()->first('enroll', ['id', $id]);
        if (!$enroll) {
            return $this->errorResponse('No data available', 404);
        }

        if ($column === 'result_plan' && $value > 0) {
            // แผนที่กำหนดให้ ต้องเป็นแผนของระดับชั้นที่ผู้สมัครคนนี้สมัครไว้
            if (!isset(\Enroll\Plan\Model::toArray($enroll->level)[$value])) {
                return $this->errorResponse('Invalid value', 400);
            }
        } elseif ($column === 'result_status' && !array_key_exists($value, Language::get('REGISTER_STATUS', []))) {
            return $this->errorResponse('Invalid value', 400);
        }

        \Kotchasan\DB::create()->update('enroll', ['id', $id], [$column => $value]);

        \Index\Log\Model::add($id, 'enroll', 'Save', ucfirst($column).' of enroll ID : '.$id.' = '.$value, $login->id);

        return $this->notificationResponse(Language::get('Saved successfully'));
    }

    /**
     * GET api/enroll/enrolls/export?type=csv
     * ส่งออกรายชื่อผู้สมัครตามตัวกรองที่ใช้อยู่
     *
     * ลิงก์ดาวน์โหลดเป็น <a href> ธรรมดา จึงยืนยันตัวตนด้วย cookie auth_token
     * ที่ ApiController::getAccessToken() รองรับอยู่แล้ว
     *
     * @param Request $request
     * @param object $login
     *
     * @return void
     */
    protected function handleCsvExport(Request $request, $login)
    {
        $params = $this->parseParams($request, $login);

        $lng = Language::getItems([
            'Applicant ID', 'Education level', 'Study plan', 'Title', 'Name', 'Identification No.',
            'Birthday', 'Phone', 'Email', 'Nationality', 'Religion', 'Address', 'District', 'Amphur',
            'Province', 'Zipcode', 'Original school', 'Result',
            'TITLES', 'ACADEMIC_RESULTS', 'PARENT_LIST', 'REGISTER_STATUS'
        ]);
        $titles = is_array($lng['TITLES']) ? $lng['TITLES'] : [];
        $academic = is_array($lng['ACADEMIC_RESULTS']) ? $lng['ACADEMIC_RESULTS'] : [];
        $parents = is_array($lng['PARENT_LIST']) ? $lng['PARENT_LIST'] : [];
        $statuses = is_array($lng['REGISTER_STATUS']) ? $lng['REGISTER_STATUS'] : [];

        $planCount = max(1, (int) self::$cfg->enroll_study_plan_count);
        $levels = \Enroll\Level\Model::toArray();
        $plans = \Enroll\Plan\Model::toArrayAll();

        $headers = [$lng['Applicant ID'], $lng['Education level']];
        for ($i = 0; $i < $planCount; $i++) {
            $headers[] = $lng['Study plan'].' '.($i + 1);
        }
        foreach (['Title', 'Name', 'Identification No.', 'Birthday', 'Phone', 'Email', 'Nationality',
            'Religion', 'Address', 'District', 'Amphur', 'Province', 'Zipcode'] as $key) {
            $headers[] = $lng[$key];
        }
        foreach ($parents as $label) {
            $headers[] = $lng['Name'].' '.$label;
            $headers[] = $lng['Phone'];
        }
        $headers[] = $lng['Original school'];
        foreach ($academic as $label) {
            $headers[] = $label;
        }
        $headers[] = $lng['Study plan'];
        $headers[] = $lng['Result'];

        $query = \Kotchasan\Model::createQuery()
            ->select('E.*', 'P.province', 'A.amphur', 'D.district',
                Sql::GROUP_CONCAT(['C.plan_id'], 'plan_ids', ',', false, 'C.no'))
            ->from([$this->toDataTable($params, $login), 'E'])
            ->join('enroll_choices C', [['C.enroll_id', 'E.id']], 'LEFT')
            ->join('province P', [['P.id', 'E.provinceID']], 'LEFT')
            ->join('amphur A', [['A.country', 'P.country'], ['A.id', 'E.amphurID']], 'LEFT')
            ->join('district D', [['D.country', 'P.country'], ['D.id', 'E.districtID']], 'LEFT')
            ->groupBy('E.id');

        $sortData = $this->parseSort($params['sort'] ?? '');
        foreach ($sortData['columns'] as $key => $sort) {
            if ($sort !== 'academic_value') {
                $query->orderBy('E.'.$sort, $sortData['directions'][$key] ?? 'asc');
            }
        }
        if (empty($sortData['columns'])) {
            $query->orderBy('E.created_at', 'asc');
        }

        $rows = [];
        foreach ($query->fetchAll() as $item) {
            $row = [
                $item->enroll_no,
                $levels[$item->level] ?? ''
            ];
            $chosen = array_values(array_filter(explode(',', (string) $item->plan_ids), 'strlen'));
            for ($i = 0; $i < $planCount; $i++) {
                $row[] = isset($chosen[$i]) ? ($plans[$chosen[$i]] ?? '') : '';
            }
            $row[] = $titles[$item->title] ?? '';
            $row[] = $item->name;
            $row[] = $item->id_card;
            $row[] = Date::format($item->birthday, 'd M Y');
            $row[] = $item->phone;
            $row[] = $item->email;
            $row[] = $item->nationality;
            $row[] = $item->religion;
            $row[] = $item->address;
            $row[] = $item->district;
            $row[] = $item->amphur;
            $row[] = $item->province;
            $row[] = $item->zipcode;

            $parentDatas = json_decode((string) $item->parent, true);
            foreach ($parents as $key => $label) {
                $row[] = $parentDatas[$key]['name'] ?? '';
                $row[] = $parentDatas[$key]['phone'] ?? '';
            }
            $row[] = $item->original_school;

            $academicDatas = json_decode((string) $item->academic_results, true);
            foreach ($academic as $key => $label) {
                $row[] = $academicDatas[$key] ?? '';
            }
            $row[] = $plans[$item->result_plan] ?? '';
            $row[] = $statuses[$item->result_status] ?? '';

            $rows[] = $row;
        }

        \Kotchasan\Csv::send('enroll', $headers, $rows, self::$cfg->enroll_csv_language);
        exit;
    }
}
