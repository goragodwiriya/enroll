<?php
/**
 * @filesource modules/enroll/models/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Setup;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=enroll-setup
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = array(
            array('E.level', $params['level'])
        );
        if ($params['plan'] > 0) {
            $where[] = array('E.result_plan', $params['plan']);
        }
        if ($params['status'] > -1) {
            $where[] = array('E.result_status', $params['status']);
        }
        return static::createQuery()
            ->select('E.enroll_no', 'E.create_date', 'E.name', 'E.id', Sql::GROUP_CONCAT('Q.value', 'plan', ',', null, 'Q.no'),
                'E.academic_results', 'E.link', 'E.result_plan', 'E.result_status')
            ->from('enroll E')
            ->join('enroll_plan Q', 'LEFT', array('Q.enroll_id', 'E.id'))
            ->where($where)
            ->groupBy('E.id');
    }

    /**
     * รับค่าจาก action (setup.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, can_manage_enroll
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_manage_enroll')) {
                // รับค่าจากการ POST
                $action = $request->post('action')->toString();
                $table_enroll = $this->getTableName('enroll');
                $db = $this->db();
                // id ที่ส่งมา
                if (preg_match_all('/,?([0-9]+),?/', $request->post('id', '')->toString(), $match)) {
                    if ($action === 'delete') {
                        // ลบ
                        $db->delete($table_enroll, array('id', $match[1]), 0);
                        $db->delete($this->getTableName('enroll_plan'), array('enroll_id', $match[1]), 0);
                        // ลบไฟล์
                        foreach ($match[1] as $id) {
                            // ลบรูปนักเรียน
                            if (is_file(ROOT_PATH.DATA_FOLDER.'enroll/'.$id.'.jpg')) {
                                unlink(ROOT_PATH.DATA_FOLDER.'enroll/'.$id.'.jpg');
                            }
                            // ลบไดเร็คทอรี่
                            File::removeDirectory(ROOT_PATH.DATA_FOLDER.'enroll/'.$id.'/');
                        }
                        // Log
                        \Index\Log\Model::add(0, 'enroll', 'Delete', Language::trans('{LNG_Delete} {LNG_Enroll} ID : '.implode(', ', $match[1])), $login['id']);
                        // reload
                        $ret['location'] = 'reload';
                    } else {
                        // plan, status
                        $actions = array(
                            'plan' => 'result_plan',
                            'status' => 'result_status'
                        );
                        if (array_key_exists($action, $actions)) {
                            $this->db()->update($table_enroll, (int) $match[1][0], array(
                                $actions[$action] => $request->post('value')->toInt()
                            ));
                            $ret['save'] = true;
                            // Log
                            \Index\Log\Model::add($match[1][0], 'enroll', 'Delete', ucfirst($actions[$action]).' ID : '.$match[1][0], $login['id']);
                        }
                    }
                } elseif ($action === 'reset') {
                    // ล้างฐานข้อมูล
                    $db->emptyTable($table_enroll);
                    $db->emptyTable($this->getTableName('enroll_plan'));
                    $db->emptyTable($this->getTableName('number'));
                    // ลบไดเร็คทอรี่
                    File::removeDirectory(ROOT_PATH.DATA_FOLDER.'enroll/');
                    // Log
                    \Index\Log\Model::add(0, 'enroll', 'Save', Language::get('Reset database'), $login['id']);
                    // คืนค่า
                    $ret['alert'] = Language::get('Saved successfully');
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่า JSON
        echo json_encode($ret);
    }

    /**
     * ส่งออกข้อมูล
     *
     * @param array $params
     *
     * @return array
     */
    public static function export($params)
    {
        $where = [];
        if ($params['level'] > 0) {
            $where[] = array('E.level', $params['level']);
        }
        $q1 = \Kotchasan\Model::createQuery()
            ->select('enroll_id', Sql::GROUP_CONCAT('N.topic', 'plan'))
            ->from('enroll_plan D')
            ->join('enroll E', 'INNER', array('E.id', 'D.enroll_id'))
            ->join('category N', 'LEFT', array(array('N.type', 'enroll'), array('N.category_id', 'D.value'), array('N.sub_category', 'E.level')))
            ->groupBy('D.enroll_id');
        return \Kotchasan\Model::createQuery()
            ->select('E.level', 'N.plan', 'E.title', 'E.name', 'E.id_card', 'E.birthday', 'E.phone', 'E.email', 'E.nationality', 'E.religion', 'E.address', 'D.district', 'A.amphur', 'P.province', 'E.zipcode', 'E.parent', 'E.original_school', 'E.academic_results')
            ->from('enroll E')
            ->join(array($q1, 'N'), 'LEFT', array('N.enroll_id', 'E.id'))
            ->join('province P', 'LEFT', array('P.id', 'E.provinceID'))
            ->join('amphur A', 'LEFT', array(array('A.id', 'E.amphurID'), array('A.province_id', 'P.id')))
            ->join('district D', 'LEFT', array(array('D.id', 'E.districtID'), array('D.amphur_id', 'A.id')))
            ->where($where)
            ->order($params['sort'])
            ->cacheOn()
            ->execute();
    }
}
