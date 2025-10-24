<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\controller\BaseController;
use app\common\library\ParkingTestAccount;
use app\common\model\parking\ParkingBarrier;
use app\common\service\barrier\Utils;
use app\common\service\pay\Shouqianba;
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

    #[Get('/test1')]
    public function test1()
    {
        $str='{"sn":"7895351986804375","tsn":"7895351986804375","client_sn":"2025102415224990690","client_tsn":"2025102415224990690","ctime":"1761290577265","status":"FAIL_CANCELED","payway":"3","payway_name":"微信","sub_payway":"4","order_status":"PAY_CANCELED","total_amount":"11","net_amount":"0","finish_time":"1761290577642","subject":"贵A45555停车缴费","description":"","store_id":"986c80b6-f8af-42b1-8412-04047921d0ec","terminal_id":"e508f253-34bc-48ee-a39e-a0d70984d788","operator":"mp-plugin","reflect":"","payment_list":[]}';
        $data = json_decode($str, true);
        print_r($data);
    }

    #[Get('/test2')]
    public function test2()
    {
        Utils::test();
    }
}