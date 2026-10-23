<?php
/**
 * @filesource modules/enroll/controllers/announce.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Announce;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * ประกาศผลการสมัคร (หน้าสาธารณะ)
 *
 * ปิดอยู่จนกว่าผู้ดูแลจะเปิด (enroll_announce) และแสดงเฉพาะสถานะที่เลือกไว้
 * (enroll_announce_statuses) ข้อมูลที่แสดงมีแค่เลขประจำตัวผู้สมัคร ชื่อ อักษรแรกของ
 * นามสกุล ระดับชั้น แผนการเรียนที่ได้ และผล — ไม่มีเลขบัตร เบอร์โทร หรือลิงก์ใบสมัคร
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * จำนวนรายชื่อสูงสุดต่อการเรียกหนึ่งครั้ง
     */
    const LIMIT = 2000;

    /**
     * GET api/enroll/announce/get?level=&search=
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
            if (empty(self::$cfg->enroll_announce)) {
                return $this->successResponse([
                    'data' => $info + ['enabled' => false, 'rows' => [], 'level' => '', 'search' => '']
                ], 'Not announced');
            }

            $level = $request->get('level')->toInt();
            $search = trim($request->get('search')->topic());
            $statuses = array_map('intval', is_array(self::$cfg->enroll_announce_statuses) ? self::$cfg->enroll_announce_statuses : []);

            $rows = [];
            if (!empty($statuses)) {
                $where = [['result_status', $statuses]];
                if ($level > 0) {
                    $where[] = ['level', $level];
                }
                $query = \Kotchasan\Model::createQuery()
                    ->select('enroll_no', 'title', 'name', 'level', 'result_plan', 'result_status')
                    ->from('enroll')
                    ->where($where);
                if ($search !== '') {
                    $query->where([
                        ['enroll_no', 'LIKE', '%'.$search.'%'],
                        ['name', 'LIKE', '%'.$search.'%']
                    ], 'OR');
                }
                $levels = \Enroll\Level\Model::toArray();
                $plans = \Enroll\Plan\Model::toArrayAll();
                $status = Language::get('REGISTER_STATUS', []);
                foreach ($query->orderBy('level')->orderBy('enroll_no')->limit(self::LIMIT)->fetchAll() as $item) {
                    $rows[] = [
                        'enroll_no' => $item->enroll_no,
                        'name' => self::maskName(Language::get('TITLES', [], $item->title).$item->name),
                        'level' => $levels[$item->level] ?? '',
                        'plan' => $plans[$item->result_plan] ?? '',
                        'status' => $status[$item->result_status] ?? '',
                        'status_class' => 'term'.(int) $item->result_status
                    ];
                }
            }

            return $this->successResponse([
                'data' => $info + [
                    'enabled' => true,
                    'rows' => $rows,
                    'count' => count($rows),
                    'level' => $level > 0 ? (string) $level : '',
                    'search' => $search
                ],
                'options' => [
                    'level' => \Enroll\Level\Model::toOptions(true)
                ]
            ], 'Announcement loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ชื่อสำหรับประกาศ: ชื่อเต็ม ส่วนที่เหลือ (นามสกุล) เหลือแค่อักษรแรก
     * "เด็กชายธนกร ใจดี" → "เด็กชายธนกร ใ."
     *
     * @param string $name
     *
     * @return string
     */
    public static function maskName($name)
    {
        $parts = preg_split('/\s+/u', trim((string) $name));
        $result = array_shift($parts);
        foreach ($parts as $part) {
            if ($part !== '') {
                $result .= ' '.mb_substr($part, 0, 1, 'UTF-8').'.';
            }
        }

        return (string) $result;
    }
}
