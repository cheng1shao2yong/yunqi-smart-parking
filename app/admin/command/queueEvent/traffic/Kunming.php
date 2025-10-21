<?php
declare(strict_types=1);
namespace app\admin\command\queueEvent\traffic;

use app\common\library\Http;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingTraffic;

//停车场配置地址 https://docs.qq.com/sheet/DYmxaSU9YeEtzblBJ?tab=BB08J2
class Kunming implements BaseTraffic
{
    const APP_ID="";
    //测试地址
    const URL="http://test.kcsmkj.cn";
    //正式地址
    //const URL="http://www.yzparking.cn";
    const AES_KEY="";

    const PLATE_COLOR=[
        'blue'=>1,
        'yellow'=>2,
        'white'=>3,
        'black'=>4,
        'green'=>5,
    ];

    const RULES_TYPE=[
        'provisional'=>'TEMPORARY_CAR',
        'monthly'=>'LONG_RENT_CARD',
        'vip'=>'LONG_RENT_CARD',
        'stored'=>'OTHER',
        'member'=>'OTHER',
        'day'=>'OTHER'
    ];


    public function heartbeat(ParkingTraffic $traffic)
    {
        $url=self::URL."/open/openPlatform/heartBeat";
        $package=$this->pack($traffic,[
            'timestamp'=>date('YmdHis',time())
        ]);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if(!$response->isSuccess()){
            throw new \Exception($response->errorMsg);
        }
        if($response->content['rspData']['code']==500){
            throw new \Exception($response->content['rspData']['message']);
        }
    }

    public function ruleinfo(ParkingTraffic $traffic)
    {
        $url=self::URL."/open/openPlatform/uploadAccountRuleInfo";
        $package=$this->pack($traffic,[
            'ruleInfo'=>$traffic->rule_info,
            'timestamp'=>date('YmdHis',time())
        ]);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if(!$response->isSuccess()){
            throw new \Exception($response->errorMsg);
        }
        if($response->content['rspData']['code']==500){
            throw new \Exception($response->content['rspData']['message']);
        }
    }

    public function restberth(ParkingTraffic $traffic)
    {
        $url=self::URL."/open/openPlatform/uploadRestBerth";
        $package=$this->pack($traffic,[
            'inSpace'=>$traffic->open_parking_number-$traffic->remain_parking_number,
            'restSpace'=>$traffic->remain_parking_number,
            'timestamp'=>date('YmdHis',time())
        ]);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if(!$response->isSuccess()){
            throw new \Exception($response->errorMsg);
        }
        if($response->content['rspData']['code']==500){
            throw new \Exception($response->content['rspData']['message']);
        }
    }

    public function inrecord(ParkingTraffic $traffic,ParkingRecords $records):bool
    {
        $url=self::URL."/open/openPlatform/uploadPassInfo";
        $data=[
            'billAmount'=>'0.00',
            'carPlate'=>$records->plate_number,
            'carStatus'=>self::RULES_TYPE[$records->rules_type],
            'inPassTime'=>date('YmdHis',$records->entry_time),
            'isImmediately'=>1,
            'orderNo'=>create_out_trade_no(),
            'outPassTime'=>date('YmdHis',$records->entry_time),
            'payAmount'=>'0.00',
            'payChannel'=>'CASH',
            'payOffline'=>'0.00',
            'payOnline'=>'0.00',
            'tradeStatus'=>'IN_PARK',
        ];
        $package=$this->pack($traffic,$data);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if(!$response->isSuccess()){
            throw new \Exception($response->errorMsg);
        }
        if($response->content['rspData']['code']==500){
            throw new \Exception($response->content['rspData']['message']);
        }
        return true;
    }

    public function outrecord(ParkingTraffic $traffic,ParkingRecords $records):bool
    {
        $url=self::URL."/open/openPlatform/uploadPassInfo";
        $data=[
            'billAmount'=>$records->total_fee,
            'carPlate'=>$records->plate_number,
            'carStatus'=>self::RULES_TYPE[$records->rules_type],
            'inPassTime'=>date('YmdHis',$records->entry_time),
            'isImmediately'=>1,
            'orderNo'=>create_out_trade_no(),
            'outPassTime'=>date('YmdHis',$records->exit_time),
            'parkTime'=>round($records->exit_time-$records->entry_time/60),
            'payAmount'=>$records->pay_fee,
            'payChannel'=>$records->total_fee>0?'WEIXIN':'CASH',
            'payOffline'=>'0.00',
            'payOnline'=>$records->pay_fee,
            'tradeStatus'=>'CREATE',
        ];
        $package=$this->pack($traffic,$data);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if(!$response->isSuccess()){
            throw new \Exception($response->errorMsg);
        }
        if($response->content['rspData']['code']==500){
            throw new \Exception($response->content['rspData']['message']);
        }
        return true;
    }

    private function pack(ParkingTraffic $traffic,$data)
    {
        $package=[
            'charset'=>'UTF-8',
            'version'=>'v1.0',
            'reqData'=>[
                'parkCode'=>$traffic->filings_code,
                'tenantId'=>self::APP_ID,
                ...$data
            ]
        ];
        $package['sign']=$this->sign($package['reqData']);
        return json_encode($package);
    }

    private function sign($data)
    {
        ksort($data);
        $str='';
        foreach ($data as $k=>$v){
            $str.=$k.'='.$v.'&';
        }
        //去掉最后一个&
        $str.=self::AES_KEY;
        $signature = hash('sha256', $str);
        return $signature;
    }
}