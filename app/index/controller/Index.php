<?php
declare(strict_types=1);

namespace app\index\controller;

use app\admin\command\queueEvent\traffic\Hangzhou;
use app\common\controller\BaseController;
use app\common\library\ParkingTestAccount;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingContactless;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingTraffic;
use app\common\model\parking\ParkingRecords;
use app\common\model\PayUnion;
use app\common\service\ContactlessService;
use think\annotation\route\Get;

class Index extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
    }

    #[Get('/')]
    public function index()
    {

    }

    #[Get('/test')]
    public function test()
    {
        $json='{"plateNo":"浙ACT1772","parkingCode":"A20251022100946","billID":"2025111010540024417","orderCode":"2025111022001498321424639709","payType":101,"payStatus":1,"paidType":3,"billTime":"2025-11-10 10:54:00","shouldPay":10,"dealTime":"2025-11-10 10:54:04","askOrderTime":"2025-11-10 10:54:00","discountAmount":0,"actualPay":10,"updateTime":"2025-11-10 10:54:04"}';
        $json=json_decode($json,true);
        $service=ContactlessService::getService("\\app\\common\\service\\contactless\\Hzbrain");
        $service->payResult($json);
    }
}