<?php
declare(strict_types=1);

namespace app\index\controller;

use app\admin\command\queueEvent\traffic\Hangzhou;
use app\common\controller\BaseController;
use app\common\library\Http;
use app\common\library\ParkingTestAccount;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingContactless;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingTraffic;
use app\common\model\parking\ParkingRecords;
use app\common\model\PayUnion;
use app\common\service\contactless\Hzbrain;
use app\common\service\ContactlessService;
use think\annotation\route\Get;
use think\facade\Env;

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
        $traffic=ParkingTraffic::find(2);
        $pdata=[
            'parkingCode'=>$traffic->filings_code,
            'plateNo'=>'浙AEM3061',
            'checkBillTime'=>'2025-11-11',
        ];
        $privatekey=Env::get('TRAFFIC_PRIVATE_KEY');
        $accessid=Env::get('TRAFFIC_ACCESSID');
        $package=Hzbrain::pack($pdata,$privatekey);
        $url="http://220.191.209.248:8990/api/v2/cp/checkDailyBill";
        $data=[
            'accessID'=>$accessid,
            'sign'=>Hzbrain::sign($package,$privatekey),
            'cipher'=>$package
        ];
        $response=Http::post($url,$data);
        if($response->isSuccess()){
            $content=$response->content;
            print_r($content);
        }else{
            throw new \Exception($response->errorMsg);
        }
    }
}