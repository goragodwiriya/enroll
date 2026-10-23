<?php
/**
 * @filesource modules/enroll/models/plan.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Plan;

use Kotchasan\Database\Sql;
use Kotchasan\Language;

/**
 * แผนการเรียนของแต่ละระดับชั้น
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านแผนการเรียนของระดับชั้นที่กำหนด
     *
     * @param int $level_id
     * @param bool $activeOnly ใช้กับหน้าของผู้สมัครเท่านั้น
     *
     * @return array
     */
    public static function byLevel($level_id, $activeOnly = false)
    {
        if (empty($level_id)) {
            return [];
        }
        $query = static::createQuery()
            ->select('id', 'level_id', 'topic', 'sort', 'is_active', 'capacity')
            ->from('enroll_plans')
            ->where(['level_id', (int) $level_id]);
        if ($activeOnly) {
            $query->where(['is_active', 1]);
        }

        return $query->orderBy('sort')->orderBy('id')->cacheOn()->fetchAll();
    }

    /**
     * แผนการเรียนในรูป [id => topic]
     *
     * @param int $level_id
     * @param bool $activeOnly
     *
     * @return array
     */
    public static function toArray($level_id, $activeOnly = false)
    {
        $result = [];
        foreach (self::byLevel($level_id, $activeOnly) as $item) {
            $result[$item->id] = $item->topic;
        }

        return $result;
    }

    /**
     * แผนการเรียนทุกระดับในรูป [id => topic]
     * ใช้แปลงค่าเป็นข้อความในหน้าที่แสดงผู้สมัครหลายระดับพร้อมกัน
     *
     * @return array
     */
    public static function toArrayAll()
    {
        $query = static::createQuery()
            ->select('id', 'topic')
            ->from('enroll_plans')
            ->orderBy('level_id')
            ->orderBy('sort')
            ->cacheOn();
        $result = [];
        foreach ($query->fetchAll() as $item) {
            $result[$item->id] = $item->topic;
        }

        return $result;
    }

    /**
     * แผนการเรียนในรูปตัวเลือกของ select
     *
     * @param int $level_id
     * @param bool $activeOnly
     *
     * @return array
     */
    public static function toOptions($level_id, $activeOnly = false)
    {
        $options = [];
        foreach (self::byLevel($level_id, $activeOnly) as $item) {
            $options[] = [
                'value' => (string) $item->id,
                'text' => $item->topic
            ];
        }

        return $options;
    }

    /**
     * สถานะผลการสมัครที่ไม่นับในจำนวนที่รับ (ตั้งค่าได้ ค่าเริ่มต้น = ไม่อนุมัติ)
     *
     * @return array
     */
    public static function uncountedStatuses()
    {
        $statuses = self::$cfg->enroll_capacity_exclude_statuses ?? [3];

        return array_values(array_map('intval', is_array($statuses) ? $statuses : []));
    }

    /**
     * จำนวนผู้สมัครที่เลือกแต่ละแผนเป็นอันดับแรก (ใช้เทียบกับจำนวนที่รับ)
     *
     * ไม่นับใบที่ติดป้ายต้องตรวจสอบ (สงสัยว่าเป็นสแปม) และใบที่มีสถานะใน uncountedStatuses()
     * ใบเหล่านี้จึงไม่กินที่นั่งของผู้สมัครจริง เจ้าหน้าที่ตรวจแล้ว (ล้างป้าย) จึงนับ
     *
     * @param int $exceptEnrollId ไม่นับใบสมัครนี้ (ตอนแก้ไขใบเดิม)
     *
     * @return array [plan_id => จำนวน]
     */
    public static function firstChoiceCounts($exceptEnrollId = 0)
    {
        $query = static::createQuery()
            ->select('C.plan_id', Sql::COUNT('C.enroll_id', 'count'))
            ->from('enroll_choices C')
            ->join('enroll E', [['E.id', 'C.enroll_id']], 'INNER')
            ->where([['C.no', 0], ['E.review', '']]);
        $excluded = self::uncountedStatuses();
        if (!empty($excluded)) {
            $query->where(['E.result_status', '!=', $excluded]);
        }
        if ($exceptEnrollId > 0) {
            $query->where(['C.enroll_id', '!=', (int) $exceptEnrollId]);
        }
        $result = [];
        foreach ($query->groupBy('C.plan_id')->fetchAll() as $row) {
            $result[(int) $row->plan_id] = (int) $row->count;
        }

        return $result;
    }

    /**
     * แผนที่รับครบแล้ว (จำนวนที่รับ > 0 และผู้เลือกเป็นอันดับแรกถึงจำนวนนั้นแล้ว)
     *
     * @param int $level_id
     * @param int $exceptEnrollId
     *
     * @return array [plan_id => true]
     */
    public static function fullPlans($level_id, $exceptEnrollId = 0)
    {
        $counts = self::firstChoiceCounts($exceptEnrollId);
        $result = [];
        foreach (self::byLevel($level_id, true) as $item) {
            if ((int) $item->capacity > 0 && ($counts[(int) $item->id] ?? 0) >= (int) $item->capacity) {
                $result[(int) $item->id] = true;
            }
        }

        return $result;
    }

    /**
     * ระดับชั้นที่รับครบแล้ว = มีแผนที่เปิดรับ และทุกแผนกำหนดจำนวนที่รับไว้และรับครบแล้ว
     * (มีแผนที่ไม่จำกัดจำนวนหรือยังไม่ครบอยู่แผนเดียว ระดับชั้นนั้นก็ยังรับต่อ)
     *
     * @param int $exceptEnrollId
     *
     * @return array [level_id => true]
     */
    public static function fullLevels($exceptEnrollId = 0)
    {
        $counts = self::firstChoiceCounts($exceptEnrollId);
        $result = [];
        foreach (\Enroll\Level\Model::all(true) as $level) {
            $plans = self::byLevel($level->id, true);
            if (empty($plans)) {
                continue;
            }
            $full = true;
            foreach ($plans as $item) {
                if ((int) $item->capacity === 0 || ($counts[(int) $item->id] ?? 0) < (int) $item->capacity) {
                    $full = false;
                    break;
                }
            }
            if ($full) {
                $result[(int) $level->id] = true;
            }
        }

        return $result;
    }

    /**
     * ตัวเลือกระดับชั้นในแบบฟอร์มสมัคร ระดับชั้นที่รับครบแล้วต่อท้ายว่า (เต็ม)
     * และ full = true ให้หน้าฟอร์มปิดไว้ (ยกเว้นระดับชั้นที่ใบนี้สมัครไว้แล้ว)
     *
     * @param int $keepLevelId ระดับชั้นเดิมของใบที่กำลังแก้ไข
     * @param int $exceptEnrollId
     *
     * @return array [['value', 'text', 'full'?], ...]
     */
    public static function levelOptions($keepLevelId = 0, $exceptEnrollId = 0)
    {
        $full = self::fullLevels($exceptEnrollId);
        $options = [];
        foreach (\Enroll\Level\Model::toOptions(true) as $option) {
            if (isset($full[(int) $option['value']]) && (int) $option['value'] !== (int) $keepLevelId) {
                $option['text'] .= ' ('.Language::get('Full').')';
                $option['full'] = true;
            }
            $options[] = $option;
        }

        return $options;
    }

    /**
     * ระดับชั้นแรกที่ยังรับสมัคร (ค่าเริ่มต้นของใบสมัครใหม่) ทุกระดับเต็ม = ระดับชั้นแรก
     *
     * @return int
     */
    public static function firstOpenLevelId()
    {
        $full = self::fullLevels();
        foreach (\Enroll\Level\Model::all(true) as $level) {
            if (!isset($full[(int) $level->id])) {
                return (int) $level->id;
            }
        }

        return \Enroll\Level\Model::firstId(true);
    }

    /**
     * ตัวเลือกแผนการเรียนในแบบฟอร์มสมัคร แผนที่เต็มเลือกไม่ได้ (ยกเว้นแผนที่ใบนี้เลือกไว้แล้ว)
     *
     * @param int $level_id
     * @param int $keepPlanId แผนอันดับแรกเดิมของใบที่กำลังแก้ไข
     * @param int $exceptEnrollId
     *
     * @return array [['value', 'text', 'disabled'?], ...]
     */
    public static function registerOptions($level_id, $keepPlanId = 0, $exceptEnrollId = 0)
    {
        $full = self::fullPlans($level_id, $exceptEnrollId);
        $options = [];
        foreach (self::byLevel($level_id, true) as $item) {
            $option = [
                'value' => (string) $item->id,
                'text' => $item->topic
            ];
            if (isset($full[(int) $item->id]) && (int) $item->id !== (int) $keepPlanId) {
                $option['text'] .= ' ('.Language::get('Full').')';
                $option['full'] = true;
            }
            $options[] = $option;
        }

        return $options;
    }

    /**
     * ระดับชั้นและแผนการเรียนที่เปิดรับ พร้อมจำนวนที่รับ/ที่สมัครแล้ว (หน้าแรกของผู้สมัคร)
     *
     * @return array
     */
    public static function summary()
    {
        $counts = self::firstChoiceCounts();
        $result = [];
        foreach (\Enroll\Level\Model::all(true) as $level) {
            $plans = [];
            foreach (self::byLevel($level->id, true) as $item) {
                $capacity = (int) $item->capacity;
                $count = $counts[(int) $item->id] ?? 0;
                $plans[] = [
                    'topic' => $item->topic,
                    'capacity' => $capacity,
                    'count' => $count,
                    'full' => $capacity > 0 && $count >= $capacity,
                    // ข้อความสำเร็จรูป เทมเพลตไม่ต้องคำนวณเอง
                    'seats' => $capacity > 0 ? $count.'/'.$capacity : ''
                ];
            }
            if (!empty($plans)) {
                $capacities = array_column($plans, 'capacity');
                $short = \Enroll\Level\Model::shortName($level->topic);
                $result[] = [
                    'id' => (int) $level->id,
                    'topic' => $level->topic,
                    // การ์ดหน้าแรก: ชื่อย่อตัวใหญ่ (ม.1) และชื่ออังกฤษตัวเล็ก
                    'short' => $short,
                    'english' => \Enroll\Level\Model::englishName($short),
                    'plan_count' => count($plans),
                    // จำนวนที่รับรวม แสดงเมื่อทุกแผนกำหนดจำนวนไว้ (มีแผนไม่จำกัด = 0 ไม่แสดง)
                    'seats' => in_array(0, $capacities, true) ? 0 : array_sum($capacities),
                    // ทุกแผนรับครบ = ระดับชั้นนี้เต็ม (แผนที่ไม่จำกัดจำนวน full เป็น false เสมอ)
                    'full' => !in_array(false, array_column($plans, 'full'), true),
                    'plans' => $plans
                ];
            }
        }

        return $result;
    }

    /**
     * ทุกแผนที่เปิดรับมีจำนวนที่รับกำหนดไว้ และรับครบทุกแผนแล้ว = ปิดรับสมัครใหม่อัตโนมัติ
     * (มีแผนที่ไม่จำกัดจำนวนอยู่แผนเดียวก็ยังรับต่อ)
     *
     * @return bool
     */
    public static function allFull()
    {
        $counts = self::firstChoiceCounts();
        $any = false;
        foreach (\Enroll\Level\Model::all(true) as $level) {
            foreach (self::byLevel($level->id, true) as $item) {
                $any = true;
                if ((int) $item->capacity === 0 || ($counts[(int) $item->id] ?? 0) < (int) $item->capacity) {
                    return false;
                }
            }
        }

        return $any;
    }

    /**
     * แผนการเรียนที่ถูกอ้างถึงอยู่ ลบไม่ได้
     * ทั้งแผนที่ผู้สมัครเลือก และแผนที่เป็นผลการคัดเลือก
     *
     * @param array $ids
     *
     * @return array [id => จำนวนที่อ้างถึง]
     */
    public static function inUse($ids)
    {
        if (empty($ids)) {
            return [];
        }
        $result = [];

        $query = static::createQuery()
            ->select('plan_id', Sql::COUNT('plan_id', 'count'))
            ->from('enroll_choices')
            ->where(['plan_id', $ids])
            ->groupBy('plan_id');
        foreach ($query->fetchAll() as $item) {
            $result[(int) $item->plan_id] = (int) $item->count;
        }

        $query = static::createQuery()
            ->select('result_plan', Sql::COUNT('id', 'count'))
            ->from('enroll')
            ->where(['result_plan', $ids])
            ->groupBy('result_plan');
        foreach ($query->fetchAll() as $item) {
            $id = (int) $item->result_plan;
            $result[$id] = ($result[$id] ?? 0) + (int) $item->count;
        }

        return $result;
    }

    /**
     * บันทึกแผนการเรียนของระดับชั้นหนึ่ง
     * แผนที่ไม่ได้ส่งมาจะถูกลบ (ผู้เรียกต้องตรวจ inUse() มาก่อน)
     *
     * @param int $level_id
     * @param array $rows [['id' => int|0, 'topic' => string, 'is_active' => int], ...] เรียงตามลำดับที่ต้องการ
     *
     * @return void
     */
    public static function save($level_id, $rows)
    {
        $db = \Kotchasan\DB::create();
        $level_id = (int) $level_id;
        $keep = [];
        $sort = 0;
        foreach ($rows as $row) {
            $save = [
                'level_id' => $level_id,
                'topic' => $row['topic'],
                'sort' => ++$sort,
                'is_active' => empty($row['is_active']) ? 0 : 1,
                'capacity' => max(0, (int) ($row['capacity'] ?? 0))
            ];
            $id = (int) $row['id'];
            // id ของแผนการเรียนเป็น AUTO_INCREMENT ระดับระบบ แถวใหม่จึงส่ง id = 0 มา
            if ($id > 0 && $db->first('enroll_plans', [['id', $id], ['level_id', $level_id]])) {
                $db->update('enroll_plans', ['id', $id], $save);
                $keep[] = $id;
            } else {
                $keep[] = (int) $db->insert('enroll_plans', $save);
            }
        }

        $where = [['level_id', $level_id]];
        if (!empty($keep)) {
            $where[] = ['id', '!=', $keep];
        }
        $db->delete('enroll_plans', $where, 0);

        // ตัวเลือกแผนการเรียนถูก cache ไว้ (cacheOn) ต้องล้างเมื่อมีการแก้ไข
        \Index\Cache\Controller::clearCache(['query']);
    }
}
