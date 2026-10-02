<?php
/**
 * @filesource modules/enroll/controllers/address.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Address;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API autocomplete ตำบล/อำเภอ/จังหวัด ของฟอร์มสมัคร
 *
 * เปิดให้เรียกโดยไม่ต้องเข้าระบบ เพราะฟอร์มสมัครเป็นหน้าสาธารณะ
 * ข้อมูลที่คืนเป็นข้อมูลอ้างอิงเชิงภูมิศาสตร์ ไม่ใช่ข้อมูลของผู้สมัคร
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Enroll\Base\Controller
{
    /**
     * GET api/enroll/address/district?q=
     *
     * @param Request $request
     *
     * @return Response
     */
    public function district(Request $request)
    {
        return $this->search($request, 'district');
    }

    /**
     * GET api/enroll/address/amphur?q=
     *
     * @param Request $request
     *
     * @return Response
     */
    public function amphur(Request $request)
    {
        return $this->search($request, 'amphur');
    }

    /**
     * GET api/enroll/address/province?q=
     *
     * @param Request $request
     *
     * @return Response
     */
    public function province(Request $request)
    {
        return $this->search($request, 'province');
    }

    /**
     * ค้นหาและจัดรูปแบบผลลัพธ์
     *
     * คืนค่าเป็น array ตรง ๆ ตามที่ autocomplete ฝั่ง JS ต้องการ
     * (ห้ามห่อ key เพิ่มอีกชั้น) โดย value คือค่าที่จะใส่ลงในช่องที่พิมพ์
     * และฟิลด์ที่เหลือจะถูกนำไปเติมช่องที่มี id ตรงกันโดยอัตโนมัติ
     *
     * @param Request $request
     * @param string $field
     *
     * @return Response
     */
    protected function search(Request $request, $field)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $keyword = $request->get('q')->topic();
            if ($keyword === '') {
                return $this->successResponse([], 'Search completed');
            }

            $country = self::$cfg->enroll_country;
            if (!preg_match('/^[A-Z]{2}$/', (string) $country)) {
                $country = 'TH';
            }

            // ฟอร์มบางแบบไม่มีช่องตำบล ให้ค้นระดับอำเภอขึ้นไปแทน
            $withDistrict = $request->get('nodistrict')->toInt() ? false : true;

            if ($field === 'district') {
                $rows = Model::byDistrict($country, $keyword);
                $withDistrict = true;
            } elseif ($field === 'amphur') {
                $rows = Model::byAmphur($country, $keyword, $withDistrict);
            } else {
                $rows = Model::byProvince($country, $keyword, $withDistrict);
            }

            $result = [];
            foreach ($rows as $row) {
                $parts = [];
                if ($withDistrict) {
                    $parts[] = $row->district;
                }
                $parts[] = $row->amphur;
                $parts[] = $row->province;

                $item = [
                    'value' => $row->$field,
                    'text' => implode(' / ', $parts),
                    'amphur' => $row->amphur,
                    'amphurID' => (int) $row->amphurID,
                    'province' => $row->province,
                    'provinceID' => (int) $row->provinceID
                ];
                if ($withDistrict) {
                    $item['district'] = $row->district;
                    $item['districtID'] = (int) $row->districtID;
                }
                $result[] = $item;
            }

            return $this->successResponse($result, 'Search completed');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
