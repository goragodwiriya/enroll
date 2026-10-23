<?php
/**
 * @filesource modules/enroll/controllers/applicants.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Applicants;

use Kotchasan\Database\Sql;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API รายชื่อผู้ลงทะเบียน (อ่านอย่างเดียว สำหรับสมาชิกทั่วไป)
 *
 * ตรงกับหน้า module=enroll ของระบบเดิม ที่สมาชิกเปิดดูได้แต่แก้ไขอะไรไม่ได้
 * จึงไม่แสดงเลขบัตร เบอร์โทร หรือลิงก์แก้ไข
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
    protected $allowedSortColumns = ['created_at', 'name'];

    /**
     * ตัวกรองเพิ่มเติม
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

        return ['level' => $level];
    }

    /**
     * เงื่อนไขการกรอง
     *
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable(array $params, $login = null)
    {
        $query = \Kotchasan\Model::createQuery()
            ->select('E.id', 'E.created_at', 'E.name', 'E.level', 'E.result_status')
            ->from('enroll E')
            ->where([['E.level', (int) $params['level']]]);

        if (!empty($params['search'])) {
            $query->where([['E.name', 'LIKE', '%'.$params['search'].'%']]);
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
        return \Kotchasan\Model::createQuery()
            ->select('E.id', 'E.created_at', 'E.name', 'E.result_status',
                Sql::GROUP_CONCAT(['C.plan_id'], 'plan_ids', ',', false, 'C.no'))
            ->from([$inner, 'E'])
            ->join('enroll_choices C', [['C.enroll_id', 'E.id']], 'LEFT')
            ->groupBy('E.id');
    }

    /**
     * จัดรูปแบบข้อมูล
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
            $names = [];
            foreach (explode(',', (string) $row->plan_ids) as $plan_id) {
                if (isset($plans[$plan_id])) {
                    $names[] = $plans[$plan_id];
                }
            }
            $result[] = (object) [
                'id' => (int) $row->id,
                'created_at' => $row->created_at,
                'created_at_text' => Date::format($row->created_at, 'd M Y'),
                'name' => $row->name,
                'plan_text' => implode(', ', $names),
                'result_status' => (int) $row->result_status,
                'result_status_text' => $status[$row->result_status] ?? '',
                'status_class' => 'term'.(int) $row->result_status
            ];
        }

        return $result;
    }

    /**
     * ตัวเลือกของตัวกรอง
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters(array $params, $login = null)
    {
        return [
            'level' => \Enroll\Level\Model::toOptions()
        ];
    }
}
