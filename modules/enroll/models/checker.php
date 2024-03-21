<?php
/**
 * @filesource modules/enroll/models/checker.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Checker;

use Kotchasan\Language;

/**
 * ตรวจสอบข้อมูลสมาชิกด้วย Ajax.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ฟังก์ชั่นตรวจสอบความถูกต้องของเลขประชาชน และตรวจสอบเลขประชาชนซ้ำ.
     */
    public function idcard()
    {
        // referer
        if (self::$request->isReferer()) {
            try {
                $id = self::$request->post('id')->toInt();
                $value = self::$request->post('value')->toString();
                if (!preg_match('/[0-9]{13,13}/', $value)) {
                    echo Language::replace('Invalid :name', array(':name' => Language::get('Identification No.')));
                } else {
                    // ตรวจสอบ idcard
                    $model = new static;
                    $search = $model->db()->first($model->getTableName('enroll'), array('id_card', $value));
                    if ($search && ($id == 0 || $id != $search->id)) {
                        echo Language::replace('This :name already exist', array(':name' => Language::get('Identification No.')));
                    }
                }
            } catch (\Kotchasan\InputItemException $e) {
            }
        }
    }
}
