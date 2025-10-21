<?php
declare(strict_types=1);

namespace app\index\controller;

use app\admin\command\queueEvent\traffic\Hangzhou;
use app\common\controller\BaseController;
use app\common\library\ParkingTestAccount;
use app\common\model\parking\ParkingTraffic;
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
        $traffic=new ParkingTraffic();
        $traffic->filings_code='01001';
        (new Hangzhou())->heartbeat($traffic);
    }
}