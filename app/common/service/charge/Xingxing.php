<?php

declare(strict_types=1);
namespace app\common\service\charge;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingCharge;
use app\common\service\BaseService;

class Xingxing extends BaseService {

    protected function init()
    {

    }

    public static function run($chargeinfo)
    {
        $parking=Parking::where('uniqid',$chargeinfo['ParkID'])->find();
        if(!$parking){
            throw new \Exception('停车场不存在');
        }
        /* @var ParkingCharge $charge*/
        $charge=ParkingCharge::where('parking_id',$parking->id)->find();
        if(!$charge){
            throw new \Exception('未配置收费规则');
        }
        $platenumber=$chargeinfo['Plate'];
        $fee=floatval($chargeinfo['TotalMoney']);
        $kwh=floatval($chargeinfo['TotalPower']);
        $time=strtotime($chargeinfo['EndTime'])-strtotime($chargeinfo['StartTime']);
        $charge->send($platenumber,$fee,$kwh,$time);
    }
}