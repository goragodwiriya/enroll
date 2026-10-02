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
            ->select('id', 'level_id', 'topic', 'sort', 'is_active')
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
                'is_active' => empty($row['is_active']) ? 0 : 1
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
