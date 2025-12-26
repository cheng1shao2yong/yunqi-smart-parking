<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\controller\BaseController;
use app\common\library\ParkingTestAccount;
use app\common\model\manage\Parking;
use think\annotation\route\Get;
use think\facade\Cache;

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

    }
}