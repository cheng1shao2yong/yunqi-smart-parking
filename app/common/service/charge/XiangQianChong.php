<?php

declare(strict_types=1);
namespace app\common\service\charge;

use app\common\model\parking\ParkingCharge;
use app\common\service\BaseService;

class XiangQianChong extends BaseService {

    protected function init()
    {

    }

    public static function run($chargeinfo)
    {
        /* @var ParkingCharge $charge*/
        $charge=ParkingCharge::where(['code'=>$chargeinfo['stationId'],"channel"=>"xiang-qian-chong"])->find();
        if(!$charge){
            throw new \Exception('未配置收费规则');
        }
        $platenumber=$chargeinfo['plateNo'];
        $fee=intval($chargeinfo['totalMoney'])/100;
        $kwh=floatval($chargeinfo['power']);
        $time=strtotime($chargeinfo['endTime'])-strtotime($chargeinfo['startTime']);
        $charge->send($platenumber,$fee,$kwh,$time);
    }
}