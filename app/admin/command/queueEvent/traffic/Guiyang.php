<?php
declare(strict_types=1);
namespace app\admin\command\queueEvent\traffic;

use app\common\library\Http;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingTraffic;
use app\common\model\PayUnion;
use Rtgm\sm\RtSm2;
use Rtgm\sm\RtSm4;

//停车场配置地址 https://docs.qq.com/sheet/DYmxaSU9YeEtzblBJ?tab=BB08J2
class Guiyang implements BaseTraffic
{
    const APP_ID="";
    //测试地址
    //const URL="http://jieshun.zcreate.com.cn:8888";
    //正式地址
    const URL="http://weixinapp.gyszhjt.com:50001";

    const SM4_KEY='';
    const PUBLIC_KEY='';
    const PRIVATE_KEY='';

    const PLATE_COLOR=[
        'blue'=>1,
        'yellow'=>2,
        'white'=>3,
        'black'=>4,
        'green'=>5,
    ];

    public function heartbeat(ParkingTraffic $traffic)
    {
        $url=self::URL."/open-server/api/mercury/park/heartbeat";
        $package=$this->pack([
            'park_id'=>$traffic->filings_code,
            'total_parking_number'=>$traffic->total_parking_number,
            'remain_parking_number'=>$traffic->remain_parking_number,
            'open_parking_number'=>$traffic->open_parking_number,
            'reserved_parking_number'=>$traffic->reserved_parking_number,
            'event_time'=>date('Y-m-d H:i:s',time()),
        ]);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if(!$response->isSuccess()){
            throw new \Exception($response->errorMsg);
        }
    }

    public function inrecord(ParkingTraffic $traffic,ParkingRecords $records):bool
    {
        $url=self::URL."/open-server/api/v1/park/add/inrecord";
        $barrier=ParkingBarrier::find($records->entry_barrier);
        $data=[
            'order_no'=>$this->getOrderNo($records),
            'in_time'=>date('Y-m-d H:i:s',$records->entry_time),
            'park_id'=>$traffic->filings_code,
            'plate_no'=>$records->plate_number,
            'plate_color'=>self::PLATE_COLOR[$records->plate_type],
            'lane_id'=>md5($barrier->serialno),
            'lane_name'=>$barrier->title,
            'type'=>0,
            'car_type'=>1,
            'is_correction'=>0,
            'upload_time'=>date('Y-m-d H:i:s',time()),
            'total_parking_number'=>$traffic->total_parking_number,
            'remain_parking_number'=>$traffic->remain_parking_number,
            'open_parking_number'=>$traffic->open_parking_number,
            'reserved_parking_number'=>$traffic->reserved_parking_number,
            'exception_parking_number'=>0,
        ];
        $package=$this->pack($data);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if($response->isSuccess()){
            if(intval($response->content['code'])!==0){
                throw new \Exception($response->content['message']);
            }
            return true;
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function outrecord(ParkingTraffic $traffic,ParkingRecords $records):bool
    {
        $url=self::URL."/open-server/api/v1/park/add/record";
        $barrier=ParkingBarrier::find($records->exit_barrier);
        $data=[
            'order_no'=>$this->getOrderNo($records),
            'order_real_price'=>intval($records->pay_fee*100),
            'order_price'=>intval($records->total_fee*100),
            'order_pay_status'=>1,
            'order_parking_time'=>$records->exit_time-$records->entry_time,
            'order_pay_time'=>$records->updatetime.':00',
            'in_time'=>date('Y-m-d H:i:s',$records->entry_time),
            'out_time'=>date('Y-m-d H:i:s',$records->exit_time),
            'park_id'=>$traffic->filings_code,
            'plate_no'=>$records->plate_number,
            'plate_color'=>self::PLATE_COLOR[$records->plate_type],
            'lane_id'=>md5($barrier->serialno),
            'lane_name'=>$barrier->title,
            'type'=>0,
            'car_type'=>1,
            'is_correction'=>0,
            'upload_time'=>date('Y-m-d H:i:s',time()),
            'total_parking_number'=>$traffic->total_parking_number,
            'remain_parking_number'=>$traffic->remain_parking_number,
            'open_parking_number'=>$traffic->open_parking_number,
            'reserved_parking_number'=>$traffic->reserved_parking_number,
            'exception_parking_number'=>0
        ];
        $package=$this->pack($data);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if($response->isSuccess()){
            if(intval($response->content['code'])!==0){
                throw new \Exception($response->content['message']);
            }
            return true;
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function order(ParkingTraffic $traffic,ParkingRecords $records,PayUnion $union):bool
    {
        $url=self::URL."/open-server/api/v1/park/add/order";
        $data=[
            'park_id'=>$traffic->filings_code,
            'plate_no'=>$records->plate_number,
            'type'=>0,
            'order_no'=>$this->getOrderNo($records),
            'order_real_price'=>intval($records->pay_fee*100),
            'order_pay_status'=>1,
            'order_parking_time'=>$records->exit_time-$records->entry_time,
            'order_pay_time'=>$records->updatetime.':00',
            'in_time'=>date('Y-m-d H:i:s',$records->entry_time),
            'out_time'=>date('Y-m-d H:i:s',$records->exit_time),
            'car_type'=>1,
            'plate_color'=>self::PLATE_COLOR[$records->plate_type],
            'upload_time'=>date('Y-m-d H:i:s',time())
        ];
        $package=$this->pack($data);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if($response->isSuccess()){
            if(intval($response->content['code'])!==0){
                throw new \Exception($response->content['message']);
            }
            return true;
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function restberth(ParkingTraffic $traffic){

    }

    //停车规则
    public function ruleinfo(ParkingTraffic $traffic){

    }

    private function getOrderNo(ParkingRecords $records)
    {
        $orderNo=md5($records->id.$records->parking_id.$records->rules_id.$records->createtime).date('YmdHis',strtotime($records->createtime));
        return $orderNo;
    }

    private function pack($data)
    {
        $package=[
            'app_id'=>self::APP_ID,
            'timestamp'=>date('yyyyMMddHHmmss',time()),
            'data'=>$this->SM4Encrypt(json_encode($data,JSON_UNESCAPED_UNICODE)),
        ];
        $sign=trim($this->sign($data));
        $package['sign']=$sign;
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
        $str=substr($str,0,-1);
        $sm2 = new RtSm2('base64',true);
        $sign=$sm2->doSignOutKey($str, __DIR__.DIRECTORY_SEPARATOR.'private_pkcs1.pem');
        return $sign;
    }

    private function verifySign($data,$sign)
    {
        ksort($data);
        $str='';
        foreach ($data as $k=>$v){
            $str.=$k.'='.$v.'&';
        }
        //去掉最后一个&
        $str=substr($str,0,-1);
        $sm2 = new RtSm2('base64',true);
        return $sm2->verifySignOutKey($str, $sign, __DIR__.DIRECTORY_SEPARATOR.'public_pkcs1.pem');
    }

    private function SM4Encrypt($plaintext) {
        $plaintext = $this->pkcs7Padding($plaintext);
        $key=base64_decode(self::SM4_KEY);
        $sm4 = new RtSm4($key);
        $iv=str_repeat("\x00", 16);
        $hex = $sm4->encrypt($plaintext,'sm4',$iv,'base64');
        return $hex;
    }

    private function SM4Decrypt($hex) {
        $key=base64_decode(self::SM4_KEY);
        $sm4 = new RtSm4($key);
        $iv=str_repeat("\x00", 16);
        $result = $sm4->decrypt($hex,'sm4',$iv,'base64');
        return $result;
    }

    private function pkcs7Padding($data, $blockSize = 16) {
        $padLength = $blockSize - (strlen($data) % $blockSize);
        if ($padLength == 0) {
            $padLength = $blockSize; // 若长度正好是块大小的倍数，补一个完整块
        }
        return $data . str_repeat(chr($padLength), $padLength);
    }
}