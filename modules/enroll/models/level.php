<?php
/**
 * @filesource modules/enroll/models/level.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Level;

use Kotchasan\Database\Sql;

/**
 * ระดับชั้นที่เปิดรับสมัคร
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านระดับชั้นทั้งหมด เรียงตามลำดับที่กำหนด
     *
     * $activeOnly ใช้กับหน้าของผู้สมัครเท่านั้น ฝั่งผู้ดูแลต้องเห็นระดับที่ปิดรับด้วย
     * มิฉะนั้นใบสมัครเก่าจะแสดงระดับชั้นเป็นค่าว่าง
     *
     * @param bool $activeOnly
     *
     * @return array
     */
    public static function all($activeOnly = false)
    {
        $query = static::createQuery()
            ->select('id', 'topic', 'sort', 'is_active')
            ->from('enroll_levels');
        if ($activeOnly) {
            $query->where(['is_active', 1]);
        }

        return $query->orderBy('sort')->orderBy('id')->cacheOn()->fetchAll();
    }

    /**
     * ระดับชั้นในรูป [id => topic] สำหรับแปลงค่าเป็นข้อความ
     *
     * @param bool $activeOnly
     *
     * @return array
     */
    public static function toArray($activeOnly = false)
    {
        $result = [];
        foreach (self::all($activeOnly) as $item) {
            $result[$item->id] = $item->topic;
        }

        return $result;
    }

    /**
     * ระดับชั้นในรูปตัวเลือกของ select
     *
     * @param bool $activeOnly
     *
     * @return array
     */
    public static function toOptions($activeOnly = false)
    {
        $options = [];
        foreach (self::all($activeOnly) as $item) {
            $options[] = [
                'value' => (string) $item->id,
                'text' => $item->topic
            ];
        }

        return $options;
    }

    /**
     * ระดับชั้นแรก ใช้เป็นค่าเริ่มต้นเมื่อไม่ได้ระบุมา
     *
     * @param bool $activeOnly
     *
     * @return int
     */
    public static function firstId($activeOnly = false)
    {
        foreach (self::all($activeOnly) as $item) {
            return (int) $item->id;
        }

        return 0;
    }

    /**
     * ตรวจสอบว่ามีระดับชั้นนี้อยู่
     *
     * @param int $id
     *
     * @return bool
     */
    public static function exists($id)
    {
        return isset(self::toArray()[$id]);
    }

    /**
     * ระดับชั้นที่มีผู้สมัครอยู่แล้ว ลบไม่ได้
     *
     * @param array $ids
     *
     * @return array [id => จำนวนผู้สมัคร]
     */
    public static function inUse($ids)
    {
        if (empty($ids)) {
            return [];
        }
        $query = static::createQuery()
            ->select('level', Sql::COUNT('id', 'count'))
            ->from('enroll')
            ->where(['level', $ids])
            ->groupBy('level');
        $result = [];
        foreach ($query->fetchAll() as $item) {
            $result[(int) $item->level] = (int) $item->count;
        }

        return $result;
    }

    /**
     * บันทึกระดับชั้นทั้งชุด
     * ระดับชั้นที่ไม่ได้ส่งมาจะถูกลบพร้อมแผนการเรียนของระดับนั้น
     *
     * @param array $rows [['id' => int, 'topic' => string, 'is_active' => int], ...] เรียงตามลำดับที่ต้องการ
     *
     * @return void
     */
    public static function save($rows)
    {
        $db = \Kotchasan\DB::create();
        $keep = [];
        $sort = 0;
        foreach ($rows as $row) {
            $keep[] = $row['id'];
            $save = [
                'topic' => $row['topic'],
                'sort' => ++$sort,
                'is_active' => empty($row['is_active']) ? 0 : 1
            ];
            if ($db->first('enroll_levels', ['id', $row['id']])) {
                $db->update('enroll_levels', ['id', $row['id']], $save);
            } else {
                $save['id'] = $row['id'];
                $db->insert('enroll_levels', $save);
            }
        }

        // ลบระดับที่ถูกเอาออกจากตาราง พร้อมแผนการเรียนที่ห้อยอยู่กับระดับนั้น
        $where = empty($keep) ? [] : [['id', '!=', $keep]];
        $removed = $db->select('enroll_levels', $where, [], ['id']);
        if (!empty($removed)) {
            $remove_ids = [];
            foreach ($removed as $item) {
                $remove_ids[] = (int) $item->id;
            }
            $db->delete('enroll_plans', ['level_id', $remove_ids], 0);
            $db->delete('enroll_levels', ['id', $remove_ids], 0);
        }

        // ตัวเลือกระดับชั้นถูก cache ไว้ (cacheOn) ต้องล้างเมื่อมีการแก้ไข
        \Index\Cache\Controller::clearCache(['query']);
    }
}
