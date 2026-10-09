<?php
/**
 * @filesource modules/enroll/controllers/pages.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Pages;

use Gcms\Api as ApiController;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * แก้ไขเนื้อหาหน้าแรกและหน้าผลการสมัคร แยกตามภาษา
 *
 * เทียบกับเมนู "รายละเอียดของหน้า" (module=write) ของระบบเดิม
 * ใช้ได้เฉพาะผู้ดูแลระบบ (status 1 หรือ id 1) และไม่ใช่โหมดตัวอย่าง เหมือนระบบเดิม (Login::isAdmin)
 * เพราะเนื้อหาเป็น HTML ที่แสดงให้ผู้เข้าชมทุกคนเห็น
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/pages/get?src=&language=
     *
     * @param Request $request
     *
     * @return Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!self::canEditPages($login)) {
                return $this->errorResponse('Permission required', 403);
            }

            list($src, $language) = self::target($request->get('src')->filter('a-z'), $request->get('language')->filter('a-z'));

            $languages = [];
            foreach (Language::installedLanguage() as $lng) {
                $languages[] = ['value' => $lng, 'text' => strtoupper($lng)];
            }

            return $this->successResponse([
                'data' => [
                    'src' => $src,
                    'language' => $language,
                    'detail' => self::pageContent($src, $language),
                    // ดูหน้าจริง (/home ไม่พาผู้ที่เข้าระบบแล้วไปหน้ารายชื่อผู้สมัคร)
                    'view_url' => ['dashboard' => '/home', 'result' => '/enroll-result', 'privacy' => '/enroll-privacy'][$src]
                ],
                'options' => [
                    'src' => \Gcms\Controller::arrayToOptions(self::pages()),
                    'language' => $languages
                ]
            ], 'Page loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/enroll/pages/save
     *
     * @param Request $request
     *
     * @return Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!self::canEditPages($login) || !ApiController::isNotDemoMode($login)) {
                return $this->errorResponse('Permission required', 403);
            }

            $src = $request->post('src')->filter('a-z');
            $language = $request->post('language')->filter('a-z');
            if (!isset(self::pages()[$src]) || !in_array($language, Language::installedLanguage(), true)) {
                return $this->errorResponse('Invalid data', 400);
            }
            // HTML จากตัวแก้ไขของผู้ดูแล เก็บตามที่เขียน (ตัดเฉพาะโค้ด PHP และวงเล็บปีกกา)
            // เหมือนระบบเดิม
            $detail = $request->post('detail')->detail();

            $dir = ROOT_PATH.DATA_FOLDER.'pages/';
            if (!File::makeDirectory($dir)) {
                return $this->errorResponse(Language::replace('Directory %s cannot be created or is read-only.', DATA_FOLDER.'pages/'), 500);
            }
            if (file_put_contents($dir.$src.'_'.$language.'.html', $detail) === false) {
                return $this->errorResponse(Language::replace('File %s cannot be created or is read-only.', DATA_FOLDER.'pages/'.$src.'_'.$language.'.html'), 500);
            }

            \Index\Log\Model::add(0, 'enroll', 'Save', Language::get('Page details').' '.$src.'_'.$language, $login->id);

            return $this->successResponse(null, 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * ผู้ดูแลระบบ (status 1) หรือผู้ดูแลสูงสุด (id 1) — เทียบ Login::isAdmin() ของระบบเดิม
     *
     * @param object|null $login
     *
     * @return bool
     */
    public static function canEditPages($login)
    {
        return ApiController::isSuperAdmin($login) || ApiController::isAdmin($login);
    }

    /**
     * หน้าและภาษาที่ขอ ถ้าไม่ถูกต้องใช้หน้าแรกและภาษาที่ใช้อยู่
     *
     * @param string $src
     * @param string $language
     *
     * @return array [src, language]
     */
    protected static function target($src, $language)
    {
        if (!isset(self::pages()[$src])) {
            $src = 'dashboard';
        }
        $installed = Language::installedLanguage();
        if (!in_array($language, $installed, true)) {
            $language = in_array(Language::name(), $installed, true) ? Language::name() : 'th';
        }

        return [$src, $language];
    }
}
