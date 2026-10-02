<?php
/**
 * @filesource modules/enroll/models/enroll.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Enroll;

use Kotchasan\Database\Sql;
use Kotchasan\File;

/**
 * ใบสมัครของผู้สมัคร
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านใบสมัครจาก id (ตัวเลข) หรือ link (32 ตัวอักษร)
     * link คือกุญแจที่ผู้สมัครใช้กลับมาแก้ไขหรือดูผล จึงรับได้ทั้งสองแบบ
     *
     * @param int|string $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        if (preg_match('/^[a-z0-9]{32}$/', (string) $id)) {
            $where = ['E.link', $id];
        } elseif ((int) $id > 0) {
            $where = ['E.id', (int) $id];
        } else {
            return null;
        }

        return static::createQuery()
            ->select('E.*', 'P.province', 'A.amphur', 'D.district')
            ->from('enroll E')
            ->join('province P', [['P.id', 'E.provinceID']], 'LEFT')
            ->join('amphur A', [['A.country', 'P.country'], ['A.id', 'E.amphurID']], 'LEFT')
            ->join('district D', [['D.country', 'P.country'], ['D.id', 'E.districtID']], 'LEFT')
            ->where($where)
            ->first();
    }

    /**
     * ค้นใบสมัครจากเลขประจำตัวประชาชนและวันเกิด (ผู้สมัครใช้ดูผลการสมัคร)
     *
     * @param string $id_card
     * @param string $birthday
     *
     * @return object|null
     */
    public static function getByIdCard($id_card, $birthday)
    {
        return static::createQuery()
            ->select('id', 'link')
            ->from('enroll')
            ->where([
                ['id_card', $id_card],
                ['birthday', $birthday]
            ])
            ->first();
    }

    /**
     * ตรวจเลขประจำตัวประชาชนซ้ำ
     *
     * @param string $id_card
     * @param int $exceptId ไม่นับใบสมัครนี้ (ตอนแก้ไข)
     *
     * @return bool
     */
    public static function idCardExists($id_card, $exceptId = 0)
    {
        $where = [['id_card', $id_card]];
        if ($exceptId > 0) {
            $where[] = ['id', '!=', (int) $exceptId];
        }

        return \Kotchasan\DB::create()->exists('enroll', $where);
    }

    /**
     * แผนการเรียนที่ผู้สมัครเลือกไว้ เรียงตามลำดับที่เลือก
     *
     * @param int $enroll_id
     *
     * @return array [no => plan_id]
     */
    public static function choices($enroll_id)
    {
        if (empty($enroll_id)) {
            return [];
        }
        $query = static::createQuery()
            ->select('no', 'plan_id')
            ->from('enroll_choices')
            ->where(['enroll_id', (int) $enroll_id])
            ->orderBy('no');
        $result = [];
        foreach ($query->fetchAll() as $item) {
            $result[(int) $item->no] = (int) $item->plan_id;
        }

        return $result;
    }

    /**
     * บันทึกใบสมัคร คืนค่า id ของใบสมัคร
     *
     * @param int $id 0 = ใบสมัครใหม่
     * @param array $save
     * @param array $planIds แผนการเรียนที่เลือก เรียงตามลำดับ
     *
     * @return int
     */
    public static function save($id, array $save, array $planIds)
    {
        $db = \Kotchasan\DB::create();
        $id = (int) $id;

        if ($id === 0) {
            $save['link'] = \Kotchasan\Password::uniqid(32);
            $save['created_at'] = date('Y-m-d H:i:s');
            $id = (int) $db->insert('enroll', $save);
        } else {
            $db->update('enroll', ['id', $id], $save);
        }

        // แผนที่เลือกเป็นชุด แทนที่ทั้งหมดทุกครั้งเหมือนระบบเดิม
        $db->delete('enroll_choices', ['enroll_id', $id], 0);
        foreach (array_values($planIds) as $no => $plan_id) {
            $db->insert('enroll_choices', [
                'enroll_id' => $id,
                'no' => $no,
                'plan_id' => (int) $plan_id
            ]);
        }

        return $id;
    }

    /**
     * กำหนดเลขประจำตัวผู้สมัคร ถ้ายังไม่มี
     *
     * @param int $id
     * @param int $level
     *
     * @return string
     */
    public static function makeEnrollNo($id, $level)
    {
        // %s ใน enroll_prefix แทนด้วยปีการศึกษา 2 หลักท้าย ต่อด้วยรหัสระดับชั้น
        $prefix = \Kotchasan\Number::printf(
            self::$cfg->enroll_prefix,
            0,
            substr((string) self::$cfg->school_year, 2, 2).$level
        );

        return \Index\Number\Model::get($id, self::$cfg->enroll_no, 'enroll', 'enroll_no', $prefix);
    }

    /**
     * ลบใบสมัคร พร้อมแผนที่เลือก รูปนักเรียน และไฟล์แนบ
     *
     * @param array $ids
     *
     * @return int จำนวนที่ลบ
     */
    public static function remove(array $ids)
    {
        if (empty($ids)) {
            return 0;
        }
        $db = \Kotchasan\DB::create();
        $db->delete('enroll_choices', ['enroll_id', $ids], 0);
        $count = $db->delete('enroll', ['id', $ids], 0);

        foreach ($ids as $id) {
            foreach (self::pictureExtensions() as $ext) {
                $image = ROOT_PATH.DATA_FOLDER.'enroll/'.(int) $id.$ext;
                if (is_file($image)) {
                    unlink($image);
                }
            }
            File::removeDirectory(ROOT_PATH.DATA_FOLDER.'enroll/'.(int) $id.'/');
        }

        return $count;
    }

    /**
     * ล้างข้อมูลการสมัครทั้งหมด (ปุ่มล้างฐานข้อมูลในหน้าตั้งค่า)
     *
     * @return void
     */
    public static function reset()
    {
        $db = \Kotchasan\DB::create();
        $db->emptyTable('enroll');
        $db->emptyTable('enroll_choices');
        $db->emptyTable('number');
        File::removeDirectory(ROOT_PATH.DATA_FOLDER.'enroll/');
    }

    /**
     * จำนวนผู้สมัครแยกตามระดับชั้น
     *
     * @return array [level => count]
     */
    public static function countByLevel()
    {
        $query = static::createQuery()
            ->select('level', Sql::COUNT('id', 'count'))
            ->from('enroll')
            ->groupBy('level');
        $result = [];
        foreach ($query->fetchAll() as $item) {
            $result[(int) $item->level] = (int) $item->count;
        }

        return $result;
    }

    /**
     * URL รูปนักเรียน ไม่มีคืนค่า null
     *
     * @param int $id
     *
     * @return string|null
     */
    public static function pictureUrl($id)
    {
        foreach (self::pictureExtensions() as $ext) {
            $file = DATA_FOLDER.'enroll/'.(int) $id.$ext;
            if (is_file(ROOT_PATH.$file)) {
                return WEB_URL.$file.'?'.filemtime(ROOT_PATH.$file);
            }
        }

        return null;
    }

    /**
     * นามสกุลของรูปนักเรียนที่เป็นไปได้
     * ระบบเดิมเก็บเป็น .jpg เสมอ ระบบใหม่ใช้ stored_img_type ของเฟรมเวิร์ก
     * ใบสมัครที่ย้ายมาจากระบบเดิมจึงยังเป็น .jpg อยู่
     *
     * @return array
     */
    protected static function pictureExtensions()
    {
        $exts = [self::$cfg->stored_img_type];
        if (!in_array('.jpg', $exts, true)) {
            $exts[] = '.jpg';
        }

        return $exts;
    }

    /**
     * รายการไฟล์แนบของใบสมัคร
     *
     * @param int $id
     *
     * @return array [['url' => ..., 'name' => ...], ...]
     */
    public static function attachments($id)
    {
        $files = [];
        $dir = ROOT_PATH.DATA_FOLDER.'enroll/'.(int) $id.'/';
        File::listFiles($dir, $files);
        sort($files);

        $result = [];
        foreach ($files as $file) {
            $result[] = [
                'url' => str_replace(ROOT_PATH, WEB_URL, $file),
                'name' => basename($file)
            ];
        }

        return $result;
    }

    /**
     * สร้างบาร์โค้ดเลขประจำตัวผู้สมัครเป็นรูป PNG
     *
     * Kotchasan\Barcode ตั้งฟอนต์เริ่มต้นไว้ที่ skin/fonts/ ซึ่งเป็นโครงของ Gcms
     * รุ่นเก่า ไม่มีใน adminframework ตัวเลขใต้บาร์โค้ดจึงไม่ถูกวาดและ GD เตือนทุกครั้ง
     * จึงต้องชี้ฟอนต์ที่มีอยู่จริงเอง
     *
     * @param string $code
     * @param int $height
     * @param int $fontSize 0 = ไม่ใส่ตัวเลขใต้บาร์โค้ด
     *
     * @return string ข้อมูลรูป PNG
     */
    public static function barcodePng($code, $height = 40, $fontSize = 9)
    {
        $font = ROOT_PATH.'Now/css/fonts/leelawad.ttf';
        if (!is_file($font)) {
            $fontSize = 0;
        }
        $barcode = \Kotchasan\Barcode::create((string) $code, $height, $fontSize);
        if ($fontSize > 0) {
            $barcode->font = $font;
        }

        return $barcode->toPng();
    }
}
