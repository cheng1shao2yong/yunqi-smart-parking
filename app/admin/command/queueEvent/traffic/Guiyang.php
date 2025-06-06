<?php
declare(strict_types=1);
namespace app\admin\command\queueEvent\traffic;

use app\common\library\Http;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingTraffic;

//停车场配置地址 https://docs.qq.com/sheet/DYmxaSU9YeEtzblBJ?tab=BB08J2
class Guiyang implements BaseTraffic
{
    const APP_ID="";
    //测试地址
    //const URL="http://jieshun.zcreate.com.cn:8888";
    //正式地址
    const URL="http://222.85.190.117:60004";
    const AES_KEY="==";
    const PRIVATE_KEY="";

    const PLATE_COLOR=[
        'blue'=>1,
        'yellow'=>2,
        'white'=>3,
        'black'=>4,
        'green'=>5,
    ];


    public function heartbeat(ParkingTraffic $traffic)
    {
        $url=self::URL."/api/mercury/park/heartbeat";
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
        $url=self::URL."/api/mercury/park/inrecord";
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
            'image'=>$records->entry_photo
        ];
        $package=$this->pack($data);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if($response->isSuccess()){
            return true;
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function outrecord(ParkingTraffic $traffic,ParkingRecords $records):bool
    {
        $url=self::URL."/api/mercury/park/outrecord";
        $barrier=ParkingBarrier::find($records->exit_barrier);
        $data=[
            'order_no'=>$this->getOrderNo($records),
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
            'image'=>$records->exit_photo
        ];
        $package=$this->pack($data);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if($response->isSuccess()){
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
        $image='';
        if(isset($data['image']) && $data['image']){
            try{
                $image=base64_encode(file_get_contents($data['image']));
            }catch (\Exception $e){

            }
            unset($data['image']);
        }
        $package=[
            'app_id'=>self::APP_ID,
            'version'=>'1.0.0',
            'timestamp'=>date('YmdHis',time()),
            'data'=>$this->aes128Encrypt($data),
        ];
        $package['sign']=$this->sign($package);
        $package['image']=$image;
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
        $privateString=self::PRIVATE_KEY;
        $private_key = <<<EOT
-----BEGIN RSA PRIVATE KEY-----
{$privateString}
-----END RSA PRIVATE KEY-----
EOT;
        $signature = '';
        if (!openssl_sign($str, $signature, $private_key, 'SHA256')) {
            throw new \Exception('Signature failed');
        }
        $r=base64_encode($signature);
        return $r;
    }

    private function aes128Encrypt($plaintext) {
        $plaintext=json_encode($plaintext,JSON_UNESCAPED_UNICODE);
        $key=base64_decode(self::AES_KEY);
        if (strlen($key) != 16) {
            throw new Exception("Key must be exactly 16 bytes long.");
        }
        $iv = str_repeat("\0", 16);
        $ciphertext = openssl_encrypt($plaintext, "aes-128-cbc", $key, OPENSSL_PKCS1_PADDING, $iv);
        if ($ciphertext === false) {
            throw new \Exception("Encryption failed: " . openssl_error_string());
        }
        return base64_encode($ciphertext);
    }
}