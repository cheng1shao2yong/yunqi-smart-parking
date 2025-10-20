<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\controller\BaseController;
use app\common\library\ParkingTestAccount;
use app\common\model\parking\ParkingBarrier;
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
    
    #[Get('/index/info')]
    public function info()
    {
        echo 111;
        exit;
    }


    #[Get('/test')]
    public function test()
    {
        $barrier=ParkingBarrier::find(26);
        //$barrier=ParkingBarrier::find(11);
        Utils::test($barrier);
    }
}