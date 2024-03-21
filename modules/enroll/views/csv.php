<?php
/**
 * @filesource modules/enroll/views/csv.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Csv;

use Gcms\Login;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=enroll-export&typ=csv
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Kotchasan\KBase
{
    /**
     * export to CSV
     *
     * @param Request $request
     */
    public static function execute(Request $request)
    {
        // สามารถจัดการรายการลงทะเบียนได้
        if (Login::checkPermission(Login::isMember(), 'can_manage_enroll')) {
            $params = array(
                'level' => $request->get('level')->toInt(),
                'plan' => $request->get('plan')->toInt(),
                'status' => $request->get('status')->toInt(),
                'sort' => []
            );
            if (preg_match_all('/(name|create_date|academic_results|result_plan|result_status)((\s(asc|desc))|)/', $request->get('sort')->toString(), $match, PREG_SET_ORDER)) {
                foreach ($match as $item) {
                    $params['sort'][] = $item[0];
                }
            }
            if (empty($params['sort'])) {
                $params['sort'][] = 'create_date asc';
            }
            $lng = Language::getItems(array(
                'Applicant ID',
                'Education level',
                'Study plan',
                'Title',
                'Name',
                'Identification No.',
                'Birthday',
                'Phone',
                'Email',
                'Nationality',
                'Religion',
                'Address',
                'District',
                'Amphur',
                'Province',
                'Zipcode',
                'Original school',
                'TITLES',
                'ACADEMIC_RESULTS',
                'PARENT_LIST',
                'Result',
                'REGISTER_STATUS'
            ));
            $header = array(
                $lng['Applicant ID'],
                $lng['Education level']
            );
            for ($i = 0; $i < self::$cfg->enroll_study_plan_count; $i++) {
                $header[] = $lng['Study plan'].' '.($i + 1);
            }
            $header[] = $lng['Title'];
            $header[] = $lng['Name'];
            $header[] = $lng['Identification No.'];
            $header[] = $lng['Birthday'];
            $header[] = $lng['Phone'];
            $header[] = $lng['Email'];
            $header[] = $lng['Nationality'];
            $header[] = $lng['Religion'];
            $header[] = $lng['Address'];
            $header[] = $lng['District'];
            $header[] = $lng['Amphur'];
            $header[] = $lng['Province'];
            $header[] = $lng['Zipcode'];
            if (is_array($lng['PARENT_LIST'])) {
                foreach ($lng['PARENT_LIST'] as $key => $label) {
                    $header[] = $lng['Name'].' '.$label;
                    $header[] = $lng['Phone'];
                }
            }
            $header[] = $lng['Original school'];
            if (is_array($lng['ACADEMIC_RESULTS'])) {
                foreach ($lng['ACADEMIC_RESULTS'] as $key => $label) {
                    $header[] = $label;
                }
            }
            $header[] = $lng['Study plan'];
            $header[] = $lng['Result'];
            // Education level
            $level = \Enroll\Level\Model::toSelect();
            // Education plan
            $planning = \Enroll\Plan\Model::toSelect($params['level']);
            $datas = [];
            foreach (\Enroll\Export\Model::csv($params) as $item) {
                $result = array(
                    $item->enroll_no,
                    isset($level[$item->level]) ? $level[$item->level] : ''
                );
                $plan = explode(',', $item->plan);
                for ($i = 0; $i < self::$cfg->enroll_study_plan_count; $i++) {
                    $result[] = isset($plan[$i]) ? $planning[$plan[$i]] : '';
                }
                $result[] = $lng['TITLES'][$item->title];
                $result[] = $item->name;
                $result[] = $item->id_card;
                $result[] = Date::format($item->birthday, 'd M Y');
                $result[] = $item->phone;
                $result[] = $item->email;
                $result[] = $item->nationality;
                $result[] = $item->religion;
                $result[] = $item->address;
                $result[] = $item->district;
                $result[] = $item->amphur;
                $result[] = $item->province;
                $result[] = $item->zipcode;
                if (is_array($lng['PARENT_LIST'])) {
                    $parent = json_decode($item->parent, true);
                    foreach ($lng['PARENT_LIST'] as $k => $v) {
                        $result[] = empty($parent[$k]['name']) ? '' : $parent[$k]['name'];
                        $result[] = empty($parent[$k]['phone']) ? '' : $parent[$k]['phone'];
                    }
                }
                $result[] = $item->original_school;
                if (is_array($lng['ACADEMIC_RESULTS'])) {
                    $academic_results = json_decode($item->academic_results, true);
                    foreach ($lng['ACADEMIC_RESULTS'] as $k => $v) {
                        if (isset($academic_results[$k])) {
                            $result[] = $academic_results[$k];
                        } else {
                            $result[] = '';
                        }
                    }
                }
                $result[] = isset($planning[$item->result_plan]) ? $planning[$item->result_plan] : '';
                $result[] = isset($lng['REGISTER_STATUS'][$item->result_status]) ? $lng['REGISTER_STATUS'][$item->result_status] : '';
                $datas[] = $result;
            }
            // export to CSV
            return \Kotchasan\Csv::send('enroll', $header, $datas, self::$cfg->enroll_csv_language);
        }
        return false;
    }
}
