<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\controller\BaseController;
use app\common\library\Http;
use app\common\library\ParkingTestAccount;
use app\common\model\parking\ParkingBarrier;
use app\common\service\barrier\Utils;
use app\common\service\pay\Shouqianba;
use app\common\service\PayService;
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

    #[Get('/test1')]
    public function test1()
    {

    }

    #[Get('/test2')]
    public function test2()
    {
        Utils::test();
    }
}