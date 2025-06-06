<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\controller\BaseController;
use app\common\library\AliyunOss;
use app\common\library\ParkingTestAccount;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingCharge;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingTemporary;
use app\common\service\barrier\Utils;
use app\common\service\ParkingService;
use app\common\service\PayService;
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
        print_r($this->run());
    }
}