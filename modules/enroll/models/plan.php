<?php
/**
 * @filesource modules/enroll/models/plan.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Plan;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=enroll-plan
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่าน Level สำหรับใส่ลงใน DataTable
     * ถ้าไม่มีคืนค่าข้อมูลเปล่าๆ 1 แถว.
     *
     * @param int $level
     *
     * @return array
     */
    public static function toDataTable($level)
    {
        $result = [];
        if ($level > 0) {
            $query = static::createQuery()
                ->select('category_id', 'topic')
                ->from('category')
                ->where(array(
                    array('type', 'enroll'),
                    array('sub_category', $level)
                ))
                ->order('category_id');

            foreach ($query->execute() as $item) {
                $result[] = array(
                    'category_id' => $item->category_id,
                    'topic' => $item->topic
                );
            }
        }
        if (empty($result)) {
            $result[] = array(
                'category_id' => 1,
                'topic' => ''
            );
        }

        return $result;
    }

    /**
     * ลิสต์รายการ Plan
     * สำหรับใส่ลงใน select
     *
     * @param int $level
     *
     * @return array
     */
    public static function toSelect($level)
    {
        $result = [];
        if ($level > 0) {
            $query = static::createQuery()
                ->select('category_id', 'topic')
                ->from('category')
                ->where(array(
                    array('type', 'enroll'),
                    array('sub_category', $level)
                ))
                ->order('category_id')
                ->cacheOn();
            foreach ($query->execute() as $item) {
                $result[$item->category_id] = $item->topic;
            }
        }

        return $result;
    }

    /**
     * ลิสต์รายการ Plan
     * สำหรับใส่ลงใน select
     * คืนค่าเป็น JSON
     *
     * @param Request $request
     *
     * @return JSON
     */
    public function toJSON(Request $request)
    {
        // session, referer
        if ($request->initSession() && $request->isReferer()) {
            $plan = self::toSelect($request->post('level')->toInt());
            if (!empty($plan)) {
                $plan += array(0 => Language::get('Please select'));
            }
            // คืนค่า JSON
            echo json_encode($plan);
        }
    }

    /**
     * ลิสต์รายการ Plan
     * สำหรับใส่ลงใน select (setup.php)
     * คืนค่าเป็น JSON
     *
     * @param Request $request
     *
     * @return JSON
     */
    public function toSetup(Request $request)
    {
        // session, referer
        if ($request->initSession() && $request->isReferer()) {
            $plan = self::toSelect($request->post('level')->toInt());
            $plan += array(0 => Language::get('Please select'));
            // คืนค่า JSON
            echo json_encode(array('plan' => $plan));
        }
    }

    /**
     * บันทึก Plan
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, สามารถจัดการการลงทะเบียนได้, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_manage_enroll')) {
                try {
                    // ค่าที่ส่งมา
                    $level = $request->post('level')->toInt();
                    if ($level > 0) {
                        $save = [];
                        $category_exists = [];
                        foreach ($request->post('category_id', [])->toInt() as $key => $value) {
                            if (isset($category_exists[$value])) {
                                $ret['ret_category_id_'.$key] = Language::replace('This :name already exist', array(':name' => 'ID'));
                            } elseif ($value > 0) {
                                $category_exists[$value] = $value;
                                $save[$key]['category_id'] = $value;
                                $save[$key]['sub_category'] = $level;
                                $save[$key]['type'] = 'enroll';
                            }
                        }
                        foreach ($request->post('topic', [])->topic() as $key => $value) {
                            if (isset($save[$key])) {
                                $save[$key]['topic'] = $value;
                            }
                        }
                        if (empty($ret)) {
                            // ชื่อตาราง
                            $table_name = $this->getTableName('category');
                            // db
                            $db = $this->db();
                            // ลบข้อมูลเดิม
                            $db->delete($table_name, array(array('type', 'enroll'), array('sub_category', $level)), 0);
                            // เพิ่มข้อมูลใหม่
                            foreach ($save as $item) {
                                if (isset($item['topic'])) {
                                    $db->insert($table_name, $item);
                                }
                            }
                            // Log
                            \Index\Log\Model::add(0, 'enroll', 'Save', Language::get('Study plan'), $login['id']);
                            // คืนค่า
                            $ret['alert'] = Language::get('Saved successfully');
                            $ret['location'] = 'reload';
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
