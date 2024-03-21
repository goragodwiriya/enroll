<?php
/**
 * @filesource modules/enroll/controllers/export.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Export;

use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Template;

/**
 * export.php?module=enroll-export&typ=csv|print
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * export
     *
     * @param Request $request
     */
    public function export(Request $request)
    {
        $typ = $request->get('typ')->toString();
        if ($typ === 'csv') {
            // CSV
            return \Enroll\Csv\View::execute($request);
        } elseif ($typ === 'print') {
            // ตรวจสอบรายการที่เลือก
            $enroll = \Enroll\Export\Model::get($request->get('id')->password());
            if ($enroll) {
                // academic_results
                $onet_list = Language::get('ACADEMIC_RESULTS');
                $academic_results = '<tr>';
                if (!empty($onet_list)) {
                    $datas = json_decode($enroll->academic_results, true);
                    $i = 0;
                    foreach ($onet_list as $key => $label) {
                        if ($i > 0 && $i % 4 == 0) {
                            $academic_results .= '</tr><tr>';
                        }
                        $i++;
                        $academic_results .= '<td>'.$label.' : '.(empty($datas[$key]) ? '' : $datas[$key]).'</td>';
                    }
                }
                $academic_results .= '</tr>';
                // parent
                $parent_list = Language::get('PARENT_LIST', []);
                $parent = '<tr>';
                if (!empty($parent_list)) {
                    $datas = json_decode($enroll->parent, true);
                    $i = 0;
                    foreach ($parent_list as $key => $label) {
                        if ($i > 0 && $i % 2 == 0) {
                            $parent .= '</tr><tr>';
                        }
                        $i++;
                        $parent .= '<td>'.$label.' : '.(empty($datas[$key]['name']) ? '' : $datas[$key]['name']).'</td>';
                        $parent .= '<td>โทรศัพท์ : '.(empty($datas[$key]['phone']) ? '' : $datas[$key]['phone']).'</td>';
                    }
                }
                $parent .= '</tr>';
                // logo
                if (is_file(ROOT_PATH.DATA_FOLDER.'images/logo.png')) {
                    $logo = WEB_URL.DATA_FOLDER.'images/logo.png';
                } else {
                    $logo = WEB_URL.'skin/img/blank.gif';
                }
                // พิมพ์
                $content = array(
                    '/{LANGUAGE}/' => Language::name(),
                    '/{CONTENT}/' => file_get_contents(ROOT_PATH.'modules/enroll/template/register.html'),
                    '/{WEBURL}/' => WEB_URL,
                    '/{TITLE}/' => self::$cfg->web_title,
                    '/%LOGO%/' => $logo,
                    '/%PICTURE%/' => WEB_URL.DATA_FOLDER.'enroll/'.$enroll->id.'.jpg?'.time(),
                    '/%DATE%/' => Date::format($enroll->create_date, 'd M Y'),
                    '/%ENROLL_NO%/' => self::blockNumber($enroll->enroll_no),
                    '/%LEVEL%/' => $enroll->level,
                    '/%SCHOOL_NAME%/' => self::$cfg->school_name,
                    '/%YEAR%/' => self::$cfg->school_year,
                    '/%PLAN%/' => $enroll->plan,
                    '/%TITLE%/' => Language::get('TITLES', '', $enroll->title),
                    '/%NAME%/' => $enroll->name,
                    '/%ID_CARD%/' => self::blockNumber($enroll->id_card, 13),
                    '/%BIRTHDAY%/' => Date::format($enroll->birthday, 'd M Y'),
                    '/%PHONE%/' => $enroll->phone,
                    '/%EMAIL%/' => $enroll->email,
                    '/%NATIONALITY%/' => $enroll->nationality,
                    '/%RELIGION%/' => $enroll->religion,
                    '/%ADDRESS%/' => $enroll->address,
                    '/%DISTRICT%/' => $enroll->district,
                    '/%AMPHUR%/' => $enroll->amphur,
                    '/%PROVINCE%/' => $enroll->province,
                    '/%ZIPCODE%/' => $enroll->zipcode,
                    '/%ORIGINAL_SCHOOL%/' => $enroll->original_school,
                    '/%PARENT%/' => $parent,
                    '/%ACADEMIC_RESULTS%/' => $academic_results,
                    '/%BARCODE%/' => base64_encode(\Kotchasan\Barcode::create($enroll->enroll_no, 50, 9)->toPng())
                );
                return self::toPrint($content);
            }
        }
        return false;
    }

    /*
     * ส่งออกข้อมูลเป็น HTML หรือ หน้าสำหรับพิมพ์
     *
     * @param array $content
     */
    /**
     * @param $content
     * @return mixed
     */
    public static function toPrint($content)
    {
        $template = Template::createFromFile(ROOT_PATH.'modules/enroll/template/print.html');
        $template->add(Language::trans($content));
        return $template->render();
    }

    /**
     * @param $value
     * @param $digit
     */
    private static function blockNumber($value, $digit = 0)
    {
        if (preg_match_all('/(.)/', $value, $match)) {
            $array = $match[1];
        } else {
            $array = [];
        }
        $max = max($digit, count($array));
        $result = '';
        for ($i = 0; $i < $max; $i++) {
            if (isset($array[$i])) {
                $result .= '<i>'.$array[$i].'</i>';
            } else {
                $result .= '<i>&nbsp;</i>';
            }
        }
        return $result;
    }
}
