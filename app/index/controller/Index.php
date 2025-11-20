<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\controller\BaseController;
use app\common\library\ParkingTestAccount;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRules;
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
        $barrier=ParkingBarrier::find(19);
        $records=ParkingRecords::find(7005);
        $barrier->barrier_type='entry';
        $plate=ParkingPlate::with(['cars'])->find(2141);
        $recordspay=ParkingRecordsPay::find(1502);
        Utils::insufficientBalance($barrier,'贵A678M5');
    }
}