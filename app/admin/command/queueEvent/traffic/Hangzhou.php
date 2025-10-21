<?php
declare(strict_types=1);
namespace app\admin\command\queueEvent\traffic;

use app\common\library\Http;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingTraffic;
use Rtgm\sm\RtSm2;
use Rtgm\sm\RtSm4;

class Hangzhou implements BaseTraffic
{
    const ACCESSID="A00001";
    const URL="http://220.191.209.248:9100";
    const PRIVATE_KEY='MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBALHHnteMDckJNlPxZ7eMzxSDmH4lTMeD6I3EvpVKad8Sw4apQvIG9ZY7ZHeUKKTOlsU0yBOp432BheP74EdU1aljnMqXpFNn+bEgTXpXCzaIdJlij9H4y/2m//mGE9l1OX2EVHZKSmeMY/GihZlMD6tP3yJ8QdolBZI/3CgH7BLDAgMBAAECgYAvkQioBXoeww89MIcerlct1vPzNImxjFKps+2GRk3DeOLF4f3eggwtsSB1ejfRuNDQXQn3cOpER2aKlHbyvvkXkNhrd/lCjpk6wtDYQsq/eeQ7wC8Am6hQ2d8cKySCl5LrpHHzkGkTv1DHw7rNKrMR03ahJWXsyPcqrbhvBMwrMQJBAPlh95E8wPSsqqYA/74o7Iqxa7nq9osXT6t5xrJc2CpI2go4OK1Da1zOI+mCbNpnuA7PnWu9xam2cCmNAsTHGskCQQC2f0L3no9mtGmuB7M7xN4Me5pUlZqVRWzLKDUK3IPEHzUZs7WDQ77RqOJBrvdHxFpY3ZS+bDFYouUbck39vHsrAkEAiIgCKhnA6jO+GbRiT5HILwaDm/3vjKbuj0rUZcI+9qd7+CxfmzxWAzE4qBcn0UsHkdRIszvqg8fGEHmLEoCPQQJAZWT3lBRooCuEu8hTcNXEeTMDYBNuu5jDBWzla49xNjoQiqMqKjAtiNdIPi4z/Y++krkpt1LtZ825dTJg2qUp2QJAMdlZbhYPL99fjhsbUS+xNisTczoi9Y+PEh+expEvfnTIj/YqKHtVdCdIPxktews831vU14GF+UwWFEZQYLt65w==';

    const PLATE_COLOR=[
        'blue'=>1,
        'yellow'=>2,
        'white'=>3,
        'black'=>4,
        'green'=>5,
    ];

    public function heartbeat(ParkingTraffic $traffic)
    {
        $url=self::URL."/tcjg/api/uploadHeartbeat";
        $package=$this->pack([
            'parkingCode'=>$traffic->filings_code,
            'uploadTime'=>date('Y-m-d H:i:s',time()),
        ]);
        $url=$url.'?accessID='.self::ACCESSID.'&sign='.$this->sign($package).'&cipher='.$package;
        $response=Http::post($url);
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
            'exception_parking_number'=>0,
        ];
        $package=$this->pack($data);
        $response=Http::post($url,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if($response->isSuccess()){
            if(intval($response->content['code'])!==0){
                throw new \Exception($response->content['message']);
            }
            if($records->pay_fee){
                $this->order($traffic,$records);
            }
            return true;
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function order(ParkingTraffic $traffic,ParkingRecords $records)
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
        $orderNo=md5($records->id.$records->parking_id.$records->rules_id.$records->createtime);
        return $orderNo;
    }

    private function pack($data)
    {
        return $this->encrypt($data);
    }

    /**
     * 用私钥对信息生成数字签名
     *
     * @param string $content 待签名数据
     * @param string $privateKey 私钥(BASE64编码，PKCS8格式)
     * @return string 签名结果(BASE64编码)
     * @throws \Exception
     */
    private function sign(string $content): string {
        $private_key = "-----BEGIN PRIVATE KEY-----\n" . wordwrap(self::PRIVATE_KEY, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
        $privateKeyId = openssl_pkey_get_private($private_key);
        $signature = '';
        $success = openssl_sign(
            $content,
            $signature,
            $privateKeyId
        );
        if (!$success) {
            throw new \Exception('签名生成失败: ' . openssl_error_string());
        }
        return $this->encryptBASE64($signature);
    }

    /**
     * 私钥加密
     *
     * @param string $content 源数据
     * @param string $privateKey 私钥(BASE64编码，PKCS8格式)
     * @return string 加密结果(BASE64编码)
     * @throws \Exception
     */
    private function encrypt(array $params)
    {
        $data=json_encode($params);
        $private_key = "-----BEGIN PRIVATE KEY-----\n" . wordwrap(self::PRIVATE_KEY, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
        $privateKeyId = openssl_pkey_get_private($private_key);
        $encryptResult = "";
        foreach (str_split($data, 117) as $chunk) {
            $encrypted='';
            $r=openssl_private_encrypt($chunk,$encrypted,$privateKeyId);
            if($r){
                $encryptResult.=$encrypted;
            }else{
                throw new \Exception(openssl_error_string());
            }
        }
        return base64_encode($encryptResult);
    }

    /**
     * BASE64解密
     *
     * @param string $key BASE64编码字符串
     * @return string 解密结果
     * @throws \Exception
     */
    private function decryptBASE64(string $key): string {
        $decoded = base64_decode($key, true) ?? throw new \Exception('BASE64解码失败');
        return $decoded;
    }

    /**
     * BASE64加密
     *
     * @param string $key 原始数据
     * @return string BASE64编码结果
     */
    private function encryptBASE64(string $key): string {
        return base64_encode($key);
    }

    /**
     * 格式化私钥，确保包含PEM头尾部并修正换行
     *
     * @param string $key 解码后的私钥内容
     * @return string 格式化后的PEM私钥
     * @throws \Exception
     */
    private function formatPrivateKey(string $key): string {
        // 移除所有空白字符（处理可能的空格、制表符等）
        $key = preg_replace('/\s+/', '', $key);

        // 检查是否已包含PEM头部，如无则添加（PKCS8格式）
        if (!str_contains($key, '-----BEGIN PRIVATE KEY-----')) {
            $key = "-----BEGIN PRIVATE KEY-----\n" . $key;
        }
        if (!str_contains($key, '-----END PRIVATE KEY-----')) {
            $key = $key . "\n-----END PRIVATE KEY-----";
        }

        // 按PEM格式要求，每64个字符换行（避免过长行导致解析失败）
        $formatted = chunk_split($key, 64, "\n");
        // 移除可能的末尾多余换行
        return trim($formatted);
    }
}