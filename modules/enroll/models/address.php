<?php
/**
 * @filesource modules/enroll/models/address.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Address;

/**
 * ค้นหา ตำบล/อำเภอ/จังหวัด สำหรับ autocomplete ของฟอร์มสมัคร
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * จำนวนผลลัพธ์สูงสุดต่อการค้นหาหนึ่งครั้ง
     */
    const LIMIT = 50;

    /**
     * ค้นหาจากชื่อตำบล คืนค่าพร้อมอำเภอและจังหวัดของตำบลนั้น
     *
     * @param string $country
     * @param string $keyword
     *
     * @return array
     */
    public static function byDistrict($country, $keyword)
    {
        return self::query($country, ['D.district', 'LIKE', $keyword.'%'], true);
    }

    /**
     * ค้นหาจากชื่ออำเภอ
     *
     * @param string $country
     * @param string $keyword
     * @param bool $withDistrict รวมตำบลในผลลัพธ์ด้วยหรือไม่
     *
     * @return array
     */
    public static function byAmphur($country, $keyword, $withDistrict = true)
    {
        return self::query($country, ['A.amphur', 'LIKE', $keyword.'%'], $withDistrict);
    }

    /**
     * ค้นหาจากชื่อจังหวัด
     *
     * @param string $country
     * @param string $keyword
     * @param bool $withDistrict
     *
     * @return array
     */
    public static function byProvince($country, $keyword, $withDistrict = true)
    {
        return self::query($country, ['P.province', 'LIKE', $keyword.'%'], $withDistrict);
    }

    /**
     * อ่านที่อยู่จากรหัสที่บันทึกไว้ ใช้เติมฟอร์มตอนแก้ไขใบสมัคร
     *
     * @param int $districtID
     * @param int $amphurID
     * @param int $provinceID
     *
     * @return object|null
     */
    public static function get($districtID, $amphurID, $provinceID)
    {
        if (empty($provinceID)) {
            return null;
        }
        $query = static::createQuery()
            ->select('P.id provinceID', 'P.province', 'A.id amphurID', 'A.amphur', 'D.id districtID', 'D.district')
            ->from('province P')
            ->join('amphur A', [['A.country', 'P.country'], ['A.province_id', 'P.id']], 'LEFT')
            ->join('district D', [['D.country', 'A.country'], ['D.amphur_id', 'A.id']], 'LEFT')
            ->where([
                ['P.id', (int) $provinceID],
                ['A.id', (int) $amphurID],
                ['D.id', (int) $districtID]
            ])
            ->cacheOn();

        return $query->first();
    }

    /**
     * Query ร่วมของทั้งสามการค้นหา
     *
     * ผลลัพธ์แนบทั้งชื่อและรหัสของทุกระดับมาด้วย เพราะฝั่ง JS ใช้เติมช่อง
     * ที่มี id ตรงกับชื่อฟิลด์ให้อัตโนมัติ (ตำบล อำเภอ จังหวัด และ hidden id ทั้งสาม)
     *
     * @param string $country
     * @param array $where
     * @param bool $withDistrict
     *
     * @return array
     */
    protected static function query($country, $where, $withDistrict)
    {
        $select = ['P.id provinceID', 'P.province', 'A.id amphurID', 'A.amphur'];
        $query = static::createQuery()
            ->from('province P')
            ->join('amphur A', [['A.country', 'P.country'], ['A.province_id', 'P.id']], 'INNER');

        if ($withDistrict) {
            $query->join('district D', [['D.country', 'A.country'], ['D.amphur_id', 'A.id']], 'INNER');
            $select[] = 'D.id districtID';
            $select[] = 'D.district';
        }

        return $query
            ->select(...$select)
            ->where([['P.country', $country], $where])
            ->orderBy($withDistrict ? 'D.district' : 'A.amphur')
            ->limit(self::LIMIT)
            ->cacheOn()
            ->fetchAll();
    }
}
