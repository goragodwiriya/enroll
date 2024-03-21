<?php
/**
 * @filesource modules/enroll/models/login.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Login;

use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=enroll-result
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ตรวจสอบข้อมูลนักเรียน (login.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token
        if ($request->initSession() && $request->isSafe()) {
            // ค่าที่ส่งมา
            $id_card = $request->post('id_card')->number();
            $birthday = $request->post('birthday')->date();
            if ($id_card == '') {
                $ret['ret_id_card'] = 'Please fill in';
            } elseif ($birthday == '') {
                $ret['ret_birthday'] = 'Please fill in';
            } else {
                $enroll = $this->db()->first($this->getTableName('enroll'), array(
                    array('id_card', $id_card),
                    array('birthday', $birthday)
                ));
                if ($enroll) {
                    // reload
                    $ret['url'] = WEB_URL.'index.php?module=enroll-result&id='.$enroll->link;
                    // เคลียร์
                    $request->removeToken();
                } else {
                    // ข้อผิดพลาด
                    $ret['alert'] = Language::get('Incorrect information, please check.');
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
