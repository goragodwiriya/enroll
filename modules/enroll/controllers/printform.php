<?php
/**
 * @filesource modules/enroll/controllers/printform.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

// ไม่ใช้ชื่อ Enroll\Print เพราะ print เป็นคำสงวนของ PHP
// ใช้ได้เฉพาะ PHP 8 ขึ้นไป จะพังทันทีบนเซิร์ฟเวอร์ PHP 7
namespace Enroll\Printform;

use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * หน้าพิมพ์ใบแจ้งความจำนง
 *
 * เรียกด้วย api/enroll/printform?id=<link> เปิดได้โดยไม่ต้องเข้าระบบ
 * เพราะผู้สมัครไม่ใช่สมาชิก กุญแจคือ link 32 ตัวอักษรของใบสมัครนั้น
 * (export.php ของเฟรมเวิร์กบังคับตรวจ token จึงใช้ทางนั้นไม่ได้)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/printform?id=<link>
     *
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $key = $request->get('id')->filter('a-z0-9');
            $enroll = $key === '' ? null : \Enroll\Enroll\Model::get($key);
            if (!$enroll || $enroll->link !== $key) {
                return $this->errorResponse('No data available', 404);
            }

            $levels = \Enroll\Level\Model::toArray();
            $plans = \Enroll\Plan\Model::toArray($enroll->level);
            $chosen = [];
            foreach (\Enroll\Enroll\Model::choices($enroll->id) as $plan_id) {
                if (isset($plans[$plan_id])) {
                    $chosen[] = $plans[$plan_id];
                }
            }

            $logo = is_file(ROOT_PATH.DATA_FOLDER.'images/logo.png')
                ? WEB_URL.DATA_FOLDER.'images/logo.png'
                : WEB_URL.'images/no-image.webp';
            $picture = \Enroll\Enroll\Model::pictureUrl($enroll->id);

            $body = file_get_contents(ROOT_PATH.'templates/enroll/print.html');
            $body = strtr($body, [
                '%BARCODE%' => base64_encode(\Enroll\Enroll\Model::barcodePng($enroll->enroll_no, 50, 9)),
                '%LOGO%' => $logo,
                '%PICTURE%' => $picture === null ? WEB_URL.'images/no-image.webp' : $picture,
                '%DATE%' => Date::format($enroll->created_at, 'd M Y'),
                '%ENROLL_NO%' => self::blockNumber($enroll->enroll_no),
                '%LEVEL%' => self::escape($levels[$enroll->level] ?? ''),
                '%SCHOOL_NAME%' => self::escape(self::$cfg->school_name),
                '%YEAR%' => self::escape(self::$cfg->school_year),
                '%PLAN%' => self::escape(implode(', ', $chosen)),
                '%TITLE%' => self::escape(Language::get('TITLES', [], $enroll->title)),
                '%NAME%' => self::escape($enroll->name),
                '%ID_CARD%' => self::blockNumber($enroll->id_card, 13),
                '%BIRTHDAY%' => Date::format($enroll->birthday, 'd M Y'),
                '%PHONE%' => self::escape($enroll->phone),
                '%EMAIL%' => self::escape($enroll->email),
                '%NATIONALITY%' => self::escape($enroll->nationality),
                '%RELIGION%' => self::escape($enroll->religion),
                '%ADDRESS%' => self::escape($enroll->address),
                '%DISTRICT%' => self::escape($enroll->district),
                '%AMPHUR%' => self::escape($enroll->amphur),
                '%PROVINCE%' => self::escape($enroll->province),
                '%ZIPCODE%' => self::escape($enroll->zipcode),
                '%ORIGINAL_SCHOOL%' => self::escape($enroll->original_school),
                '%PARENT%' => self::parentRows($enroll),
                '%ACADEMIC_RESULTS%' => self::academicRows($enroll)
            ]);

            return \Export\Export\Controller::printHtml([
                '/%CONTENT%/' => $body,
                '/%TITLE%/' => self::escape(Language::get('Registration form').' '.$enroll->name)
            ], [
                'paper' => 'A4',
                'orientation' => 'portrait',
                'extra_head' => '<link rel="stylesheet" href="'.WEB_URL.'templates/enroll/print.css">'
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * แถวข้อมูลผู้ปกครอง 2 คนต่อแถว
     *
     * @param object $enroll
     *
     * @return string
     */
    protected static function parentRows($enroll)
    {
        $list = Language::get('PARENT_LIST', []);
        if (empty($list)) {
            return '';
        }
        $datas = json_decode((string) $enroll->parent, true);
        if (!is_array($datas)) {
            $datas = [];
        }
        $phone = Language::get('Phone');
        $html = '<tr>';
        $i = 0;
        foreach ($list as $key => $label) {
            if ($i > 0 && $i % 2 == 0) {
                $html .= '</tr><tr>';
            }
            $i++;
            $html .= '<td>'.self::escape($label).' : '.self::escape($datas[$key]['name'] ?? '').'</td>';
            $html .= '<td>'.self::escape($phone).' : '.self::escape($datas[$key]['phone'] ?? '').'</td>';
        }

        return $html.'</tr>';
    }

    /**
     * แถวผลการเรียน 4 ช่องต่อแถว
     *
     * @param object $enroll
     *
     * @return string
     */
    protected static function academicRows($enroll)
    {
        $list = Language::get('ACADEMIC_RESULTS', []);
        if (empty($list)) {
            return '';
        }
        $datas = json_decode((string) $enroll->academic_results, true);
        if (!is_array($datas)) {
            $datas = [];
        }
        $html = '<tr>';
        $i = 0;
        foreach ($list as $key => $label) {
            if ($i > 0 && $i % 4 == 0) {
                $html .= '</tr><tr>';
            }
            $i++;
            $html .= '<td>'.self::escape($label).' : '.self::escape(empty($datas[$key]) ? '' : $datas[$key]).'</td>';
        }

        return $html.'</tr>';
    }

    /**
     * แสดงตัวเลขทีละหลักในช่องสี่เหลี่ยม
     *
     * @param string $value
     * @param int $digit จำนวนช่องอย่างน้อย
     *
     * @return string
     */
    protected static function blockNumber($value, $digit = 0)
    {
        $array = preg_match_all('/(.)/u', (string) $value, $match) ? $match[1] : [];
        $max = max($digit, count($array));
        $result = '';
        for ($i = 0; $i < $max; $i++) {
            $result .= '<i>'.(isset($array[$i]) ? self::escape($array[$i]) : '&nbsp;').'</i>';
        }

        return $result;
    }

    /**
     * ข้อมูลที่ผู้สมัครกรอกเองทั้งหมดต้อง escape ก่อนใส่ลงหน้าพิมพ์
     *
     * @param mixed $value
     *
     * @return string
     */
    protected static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
