<?php
declare(strict_types=1);

namespace app\common\service\charge;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingCharge;
use app\common\service\BaseService;

class XinDiantu extends BaseService {

    const KEY='rDSe1oDg37gSUIY4';

    protected function init()
    {

    }

    private static function checkSign(array $postdata):bool{
        $sign=$postdata['sign'];
        unset($postdata['sign']);
        ksort($postdata);
        $str='';
        foreach ($postdata as $key=>$value){
            $str.=$key.'='.$value.'&';
        }
        $str.='key='.self::KEY;
        if(strtoupper(md5($str))==$sign){
            return true;
        }
        return false;
    }

    public static function run(array $chargeinfo)
    {
        if(!self::checkSign($chargeinfo)){
            throw new \Exception('签名错误');
        }
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
        $fee=intval($chargeinfo['totalMoney'])/100;
        $kwh=1;
        $time=strtotime($chargeinfo['endTime'])-strtotime($chargeinfo['startTime']);
        $charge->send($platenumber,$fee,$kwh,$time);
    }
}