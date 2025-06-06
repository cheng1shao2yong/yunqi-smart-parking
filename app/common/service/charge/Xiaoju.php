<?php

declare(strict_types=1);
namespace app\common\service\charge;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingCharge;
use app\common\service\BaseService;

class Xiaoju extends BaseService {

    protected function init()
    {

    }

    public static function run($chargeinfo)
    {
        $parking=Parking::where('uniqid',$chargeinfo['merchId'])->find();
        if(!$parking){
            throw new \Exception('停车场不存在');
        }
        /* @var ParkingCharge $charge*/
        $charge=ParkingCharge::where('parking_id',$parking->id)->find();
        if(!$charge){
            throw new \Exception('未配置收费规则');
        }
        $platenumber=$chargeinfo['plateNo'];
        $fee=1;
        $kwh=1;
        $time=strtotime($chargeinfo['stopChargingTime'])-strtotime($chargeinfo['startChargingTime']);
        $charge->send($platenumber,$fee,$kwh,$time);
    }
}