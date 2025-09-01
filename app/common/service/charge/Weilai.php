<?php
declare(strict_types=1);

namespace app\common\service\charge;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingCharge;
use app\common\service\BaseService;

class Weilai extends BaseService {

    const KEY='rDSe1oDg37gSUIY4';

    const PARKID=[
        'CS-YW-d5447fff-fb588fe7'=>'md8kaw7a611k',
        'CS-NIO-639f4d50-8fc17ef3'=>'md8kaw7a611k',
        'PS-NIO-5ba6f7e2-42dd60e6'=>'md8kaw7a611k'
    ];

    protected function init()
    {

    }

    private static function checkSign(array $postdata):bool{
        $str='deduct_content={"type":"'.$postdata['deduct_content']['type'].'","value":"'.$postdata['deduct_content']['value'].'"}&resource_id='.$postdata['resource_id'].'&start_charge_seq='.$postdata['start_charge_seq'].'&vehicle_plate_number='.$postdata['vehicle_plate_number'].'&key='.self::KEY;
        if(strtoupper(md5($str))==$postdata['sign']){
            return true;
        }
        return false;
    }

    public static function run(array $chargeinfo)
    {
        if(!self::checkSign($chargeinfo)){
            throw new \Exception('签名错误');
        }
        $parking=Parking::where('uniqid',self::PARKID[$chargeinfo['resource_id']])->find();
        if(!$parking){
            throw new \Exception('停车场不存在');
        }
        /* @var ParkingCharge $charge*/
        $charge=ParkingCharge::where('parking_id',$parking->id)->find();
        if(!$charge){
            throw new \Exception('未配置收费规则');
        }
        $platenumber=$chargeinfo['vehicle_plate_number'];
        $fee=1;
        $kwh=1;
        if(isset($chargeinfo['charge_end_time']) && isset($chargeinfo['charge_start_time'])){
            $time=intval(($chargeinfo['charge_end_time']-$chargeinfo['charge_start_time'])/1000);
        }else{
            $time=15*60;
        }
        $charge->send($platenumber,$fee,$kwh,$time);
    }
}