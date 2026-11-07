<?php
/**
 * @filesource modules/enroll/controllers/examcard.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Examcard;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * บัตรประจำตัวผู้สอบ
 *
 * เรียกด้วย api/enroll/examcard?id=<link> เหมือนหน้าพิมพ์ใบสมัคร (กุญแจคือ link)
 * พิมพ์ได้เมื่อผู้ดูแลเปิด enroll_exam_card และสถานะของผู้สมัครอยู่ใน
 * enroll_exam_statuses (ว่าง = ทุกสถานะ) เลขที่นั่งสอบ = เลขประจำตัวผู้สมัคร
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/examcard?id=<link>
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
            if (!self::allowed($enroll)) {
                return $this->errorResponse(Language::get('Admission cards are not available'), 403);
            }

            $levels = \Enroll\Level\Model::toArray();
            $plans = \Enroll\Plan\Model::toArray($enroll->level);
            if (!empty($enroll->result_plan) && isset($plans[$enroll->result_plan])) {
                $plan = $plans[$enroll->result_plan];
            } else {
                $first = array_values(\Enroll\Enroll\Model::choices($enroll->id))[0] ?? 0;
                $plan = $plans[$first] ?? '';
            }
            $logo = self::logoUrl();
            $picture = \Enroll\Enroll\Model::pictureUrl($enroll->id);

            $body = strtr(file_get_contents(ROOT_PATH.'templates/enroll/examcard.html'), [
                '%LOGO%' => $logo === null ? '' : '<img class="logo" src="'.self::escape($logo).'" alt="">',
                '%PICTURE%' => $picture === null ? '' : '<img src="'.self::escape($picture).'" alt="">',
                '%BARCODE%' => base64_encode(\Enroll\Enroll\Model::barcodePng($enroll->enroll_no, 45, 9)),
                '%SCHOOL_NAME%' => self::escape(self::$cfg->school_name),
                '%YEAR%' => self::escape(self::$cfg->school_year),
                '%ENROLL_NO%' => self::escape($enroll->enroll_no),
                '%NAME%' => self::escape(Language::get('TITLES', [], $enroll->title).$enroll->name),
                '%LEVEL%' => self::escape($levels[$enroll->level] ?? ''),
                '%PLAN%' => self::escape($plan),
                '%EXAM_DATE%' => self::escape(self::$cfg->enroll_exam_date ?? ''),
                '%EXAM_PLACE%' => self::escape(self::$cfg->enroll_exam_place ?? ''),
                '%EXAM_NOTE%' => nl2br(self::escape(self::$cfg->enroll_exam_note ?? ''))
            ]);

            $toolbar = '<div class="print-bar noprint"><div class="enroll-print-actions">'
                .'<button type="button" class="print-button" onclick="window.print()">{LNG_Print}</button>'
                .'<button type="button" class="print-close" onclick="window.close()">{LNG_Close}</button>'
                .'</div></div>';

            return \Export\Export\Controller::printHtml(
                Language::get('Admission card').' '.$enroll->enroll_no,
                $toolbar.$body,
                [
                    'body_class' => 'enroll-print',
                    'page_style' => \Export\Export\Controller::pageStyle(\Export\Export\Controller::paper('a4')),
                    'stylesheets' => ['templates/enroll/print.css', 'templates/enroll/examcard.css']
                ]
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ใบสมัครนี้พิมพ์บัตรประจำตัวผู้สอบได้หรือไม่
     *
     * @param object $enroll
     *
     * @return bool
     */
    public static function allowed($enroll)
    {
        if (empty(self::$cfg->enroll_exam_card)) {
            return false;
        }
        $statuses = array_map('intval', is_array(self::$cfg->enroll_exam_statuses) ? self::$cfg->enroll_exam_statuses : []);

        return empty($statuses) || in_array((int) $enroll->result_status, $statuses, true);
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    protected static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
