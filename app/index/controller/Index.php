<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\controller\BaseController;
use app\common\library\ParkingTestAccount;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecords;
use app\common\service\barrier\Utils;
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
        $barrier=ParkingBarrier::find(6);
        Utils::open($barrier,ParkingRecords::RECORDSTYPE('自动识别'));
    }
}