<?php
/**
 * @filesource modules/enroll/models/register.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Register;

use Gcms\Login;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=enroll-register
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลสมาชิกที่ $id
     * $id = 0 ลงทะเบียน
     * คืนค่าข้อมูล object ไม่พบคืนค่า false
     *
     * @param int $id
     *
     * @return object|bool
     */
    public static function get($id)
    {
        if (empty($id)) {
            return (object) array(
                'id' => 0
            );
        } else {
            if (preg_match('/^[a-z0-9]{32,32}$/', $id)) {
                $where = array('E.link', $id);
            } else {
                $where = array('E.id', (int) $id);
            }
            return static::createQuery()
                ->from('enroll E')
                ->join('province P', 'LEFT', array('P.id', 'E.provinceID'))
                ->join('amphur A', 'LEFT', array(array('A.country', 'P.country'), array('A.id', 'E.amphurID'), array('A.province_id', 'P.id')))
                ->join('district D', 'LEFT', array(array('D.country', 'P.country'), array('D.id', 'E.districtID'), array('D.amphur_id', 'A.id')))
                ->where($where)
                ->first('E.*', 'P.province', 'A.amphur', 'D.district');
        }
    }

    /**
     * คืนค่าแผนการเรียนตามที่เลือก
     *
     * @param int $enroll_id
     *
     * @return object|bool
     */
    public static function plan($enroll_id)
    {
        $query = static::createQuery()
            ->select('no', 'value')
            ->from('enroll_plan')
            ->where(array('enroll_id', $enroll_id))
            ->order('no');
        $result = [];
        foreach ($query->execute() as $item) {
            $result[$item->no] = $item->value;
        }
        return $result;
    }

    /**
     * บันทึกข้อมูล (enroll.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token
        if ($request->initSession() && $request->isSafe()) {
            // วันนี้
            $today = time();
            if (empty(self::$cfg->enroll_begin) || empty(self::$cfg->enroll_end) || ($today >= self::$cfg->enroll_begin && $today <= self::$cfg->enroll_end)) {
                try {
                    // รับค่าจากการ POST
                    $save = array(
                        'level' => $request->post('register_level')->toInt(),
                        'title' => $request->post('register_title')->toInt(),
                        'name' => $request->post('register_name')->topic(),
                        'id_card' => $request->post('register_id_card')->number(),
                        'birthday' => $request->post('register_birthday')->date(),
                        'phone' => $request->post('register_phone')->number(),
                        'email' => $request->post('register_email')->url(),
                        'nationality' => $request->post('register_nationality')->topic(),
                        'religion' => $request->post('register_religion')->topic(),
                        'address' => $request->post('register_address')->topic(),
                        'districtID' => $request->post('register_districtID')->number(),
                        'amphurID' => $request->post('register_amphurID')->number(),
                        'provinceID' => $request->post('register_provinceID')->number(),
                        'zipcode' => $request->post('register_zipcode')->number(),
                        'original_school' => $request->post('register_original_school')->topic()
                    );
                    $datas = [];
                    foreach ($request->post('register_plan', [])->toInt() as $k => $value) {
                        if ($value > 0) {
                            $datas['plan'][] = $value;
                        } elseif ($k == 0) {
                            $ret['ret_register_plan0'] = 'Please select';
                        }
                    }
                    $parent = [];
                    foreach (Language::get('PARENT_LIST', []) as $key => $label) {
                        $parent[$key] = array(
                            'name' => $request->post('register_'.$key)->topic(),
                            'phone' => $request->post('register_'.$key.'_phone')->number()
                        );
                    }
                    if (defined('JSON_UNESCAPED_UNICODE')) {
                        $save['parent'] = json_encode($parent, JSON_UNESCAPED_UNICODE);
                    } else {
                        $save['parent'] = json_encode($parent);
                    }
                    $academic_results = [];
                    foreach (Language::get('ACADEMIC_RESULTS', []) as $key => $label) {
                        $academic_results[$key] = min(100, $request->post('register_'.$key)->toFloat());
                    }
                    $save['academic_results'] = json_encode($academic_results);
                    // ชื่อตาราง enroll
                    $table_enroll = $this->getTableName('enroll');
                    $table_enroll_plan = $this->getTableName('enroll_plan');
                    // database connection
                    $db = $this->db();
                    // ตรวจสอบค่าที่ส่งมา
                    $user = self::get($request->post('register_id')->toInt());
                    // สมาชิก
                    $login = Login::isMember();
                    if (!$login) {
                        // ไม่ใช่สมาชิกตรวจสอบว่าเป็นคนลงทะเบียนหรือเปล่า
                        $login = isset($_SESSION['enroll']) ? $_SESSION['enroll'] : null;
                    }
                    // สามารถจัดการรายการลงทะเบียนได้
                    $can_manage_enroll = Login::checkPermission($login, 'can_manage_enroll');
                    // ใหม่ หรือแก้ไขโดยผู้ดูแล
                    if ($user && ($user->id == 0 || ($login && $user->link == $login['id']) || $can_manage_enroll)) {
                        foreach (array('name', 'birthday', 'phone', 'nationality', 'religion', 'address', 'zipcode', 'original_school') as $k) {
                            if (empty($save[$k])) {
                                // ไม่ได้กรอก $k
                                $ret['ret_register_'.$k] = 'Please fill in';
                            }
                        }
                        if (!preg_match('/[0-9]{13,13}/', $save['id_card'])) {
                            // ไม่ได้กรอก id_card หรือ ไม่ถูกต้อง
                            $ret['ret_register_id_card'] = Language::replace('Invalid :name', array(':name' => Language::get('Identification No.')));
                        } else {
                            // ตรวจสอบ idcard ซ้ำ
                            $search = $db->first($table_enroll, array('id_card', $save['id_card']));
                            if ($search && ($user->id == 0 || $user->id != $search->id)) {
                                $ret['ret_register_id_card'] = Language::replace('This :name already exist', array(':name' => Language::get('Identification No.')));
                            }
                        }
                        foreach (array('districtID', 'amphurID', 'provinceID') as $k) {
                            if (empty($save[$k])) {
                                // ไม่ได้กรอก $k
                                $ret['ret_register_'.str_replace('ID', '', $k)] = 'Please fill in';
                            }
                        }
                        if (empty($ret)) {
                            // ID
                            if ($user->id == 0) {
                                $save['id'] = $db->getNextId($table_enroll);
                            } else {
                                $save['id'] = $user->id;
                                $save['enroll_no'] = $user->enroll_no;
                            }
                            // ไดเร็คทอรี่
                            $dir = ROOT_PATH.DATA_FOLDER.'enroll/';
                            if (!File::makeDirectory($dir)) {
                                // ไดเรคทอรี่ไม่สามารถสร้างได้
                                $ret['ret_thumbnail'] = Language::replace('Directory %s cannot be created or is read-only.', DATA_FOLDER.'enroll/');
                            } else {
                                // อัปโหลดไฟล์
                                foreach ($request->getUploadedFiles() as $item => $file) {
                                    if ($item == 'thumbnail') {
                                        /* @var $file \Kotchasan\Http\UploadedFile */
                                        if ($file->hasUploadFile()) {
                                            // อัปโหลด
                                            try {
                                                $file->resizeImage(array('jpg', 'jpeg', 'png'), $dir, $save['id'].'.jpg', self::$cfg->enroll_w);
                                            } catch (\Exception $exc) {
                                                // ไม่สามารถอัปโหลดได้
                                                $ret['ret_'.$item] = Language::get($exc->getMessage());
                                            }
                                        } elseif ($file->hasError()) {
                                            // ข้อผิดพลาดการอัปโหลด
                                            $ret['ret_'.$item] = Language::get($file->getErrorMessage());
                                        } elseif ($user->id == 0) {
                                            // ใหม่ ต้องอัปโหลดไฟล์
                                            $ret['ret_'.$item] = Language::get('Please upload pictures of students');
                                        }
                                    }
                                }
                            }
                        }
                        if (empty($ret)) {
                            // อัปโหลดไฟล์แนบ
                            \Download\Upload\Model::execute($ret, $request, $save['id'], 'enroll', self::$cfg->enroll_attach_file_typies);
                        }
                        // บันทึก
                        if (empty($ret)) {
                            if (empty($save['enroll_no'])) {
                                $prefix = \Kotchasan\Number::printf(self::$cfg->enroll_prefix, 0, substr(self::$cfg->school_year, 2, 2).$save['level']);
                                $save['enroll_no'] = \Index\Number\Model::get($save['id'], 'enroll_no', $table_enroll, 'enroll_no', $prefix);
                            }
                            if ($user->id == 0) {
                                // ใหม่
                                $save['link'] = \Kotchasan\Password::uniqid(32);
                                $save['create_date'] = date('Y-m-d H:i:s');
                                $db->insert($table_enroll, $save);
                            } else {
                                // แก้ไข
                                $db->update($table_enroll, $user->id, $save);
                            }
                            // datas
                            $db->delete($table_enroll_plan, array('enroll_id', $save['id']), 0);
                            foreach ($datas as $items) {
                                foreach ($items as $no => $value) {
                                    $db->insert($table_enroll_plan, array(
                                        'enroll_id' => $save['id'],
                                        'no' => $no,
                                        'value' => $value
                                    ));
                                }
                            }
                            // Log
                            \Index\Log\Model::add($save['id'], 'enroll', 'Save', Language::get('Registration form').' ID : '.$save['id'], $login ? $login['id'] : 0);
                            if ($user->id == 0) {
                                // กลับไปหน้ารายการการลงทะเบียน
                                $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'enroll-result', 'id' => $save['link']));
                            } else {
                                // กลับไปหน้าก่อนหน้า
                                $ret['location'] = 'back';
                            }
                            // คืนค่า
                            $ret['alert'] = Language::get('Saved successfully');
                            // เคลียร์
                            $request->removeToken();
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
