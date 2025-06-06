<?php

declare(strict_types=1);
namespace app\common\service\charge;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingCharge;
use app\common\service\BaseService;

class FastCloudCharge extends BaseService {

    protected function init()
    {

    }

    public static function run($chargeinfo)
    {
        $parking=Parking::where('uniqid',$chargeinfo['parkId'])->find();
        if(!$parking){
            throw new \Exception('停车场不存在');
        }
        /* @var ParkingCharge $charge*/
        $charge=ParkingCharge::where('parking_id',$parking->id)->find();
        if(!$charge){
            throw new \Exception('未配置收费规则');
        }
        $platenumber=$chargeinfo['plateNo'];
        $fee=$chargeinfo['totalMoney'];
        $kwh=$chargeinfo['power'];
        $time=strtotime($chargeinfo['endTime'])-strtotime($chargeinfo['startTime']);
        $charge->send($platenumber,$fee,$kwh,$time);
    }
}