<?php

namespace app\common\library;

use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecords;
use app\common\model\PayUnion;

class Etc
{
    //测试环境
    const URL='https://xlapi-etc-dev.xlkeji.net/xl-api/common/gateway';
    //正式环境
    //const URL='https://xlapi.etcsd.cn:8093/xl-api/common/gateway';

    const PLATE_COLOR=[
        'blue'=>0,
        'yellow'=>1,
        'black'=>2,
        'white'=>3,
        'green'=>4,
        'yellow-green'=>5,
        'blue-white'=>6,
    ];

    private $appid;

    public function setAppId(string $appid)
    {
        $this->appid=$appid;
        return $this;
    }

    public function pay(ParkingBarrier $barrier,ParkingRecords $records,PayUnion $payUnion)
    {
        $out_trade_no=create_out_trade_no();
        $time=time();
        $data=[
            'biz_id'=>'etc.parking.cloud.fee',
            'waste_sn'=>$out_trade_no,
            'params'=>[
                'lane_id'=>substr($barrier->serialno,0,8),
                'lane_name'=>$barrier->title,
                'pay_serial_no'=>$payUnion->out_trade_no,
                'trans_amount'=>intval($payUnion->pay_price*100),
                'vehplate_color'=>self::PLATE_COLOR[$records->plate_type],
                'vehplate_no'=>$records->plate_number,
                'park_time'=>$this->formatTime($time-$records->entry_time),
                'enter_time'=>date('YmdHis',$records->entry_time),
                'leave_time'=>date('YmdHis',$time),
                'plate_type_code'=>0,
                'parking_type_code'=>11
            ]
        ];
        $data=json_encode($data,JSON_UNESCAPED_UNICODE);
        $sign=$this->sign($data);
        $response=Http::post(self::URL,[
            'versions'=>'1.0',
            'appid'=>$this->appid,
            'data'=>$data,
            'sign'=>$sign
        ]);
        if($response->isSuccess()){
            print_r($response->content);
        }else{
            print_r($response->errorMsg);
        }
    }

    private function formatTime($number){
        if($number<60){
            return $number.'秒';
        }else if($number>60 && $number<3600){
            $min=floor($number/60);
            $sec=$number%60;
            return $min.'分'.$sec.'秒';
        }else if($number>3600 && $number<86400){
            $hour=floor($number/3600);
            $min=floor(($number%3600)/60);
            $sec=($number%3600)%60;
            return $hour.'时'.$min.'分'.$sec.'秒';
        }else if($number>86400 && $number<2592000){
            $day=floor($number/86400);
            $hour=floor(($number%86400)/3600);
            $min=floor(($number%86400%3600)/60);
            $sec=($number%86400%3600)%60;
            return $day.'天'.$hour.'时'.$min.'分'.$sec.'秒';
        }
    }

    private function sign($data)
    {
        $privateString=site_config("etc.private_key");
        $data=base64_encode($data);
        $private_key = <<<EOT
-----BEGIN RSA PRIVATE KEY-----
{$privateString}
-----END RSA PRIVATE KEY-----
EOT;
        $signature = '';
        if (!openssl_sign($data, $signature, $private_key, 'SHA256')) {
            throw new Exception('Signature failed');
        }
        $r=base64_encode($signature);
        return $r;
    }
}
