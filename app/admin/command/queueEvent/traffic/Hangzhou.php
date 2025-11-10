<?php
declare(strict_types=1);
namespace app\admin\command\queueEvent\traffic;

use app\common\library\Http;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingTraffic;
use app\common\model\PayUnion;
use app\common\service\contactless\Hzbrain;
use think\facade\Env;

class Hangzhou implements BaseTraffic
{
    const URL="http://220.191.209.248:8990";

    const PLATE_COLOR=[
        'blue'=>1,
        'yellow'=>2,
        'yellow-green'=>5,
        'white'=>3,
        'black'=>4,
        'green'=>5,
    ];

    private $accessid;
    private $privatekey;
    private $output;

    public function __construct($output)
    {
        $this->accessid=Env::get('TRAFFIC_ACCESSID');
        $this->privatekey=Env::get('TRAFFIC_PRIVATE_KEY');
        $this->output=$output;
    }

    public function heartbeat(ParkingTraffic $traffic)
    {
        $now=time();
        $pdata=[
            'parkingCode'=>$traffic->filings_code,
            'uploadTime'=>date('Y-m-d H:i:s',$now),
        ];
        $package=Hzbrain::pack($pdata,$this->privatekey);
        $url=self::URL."/api/v2/tcjg/uploadHeartbeat";
        $data=[
            'accessID'=>$this->accessid,
            'sign'=>Hzbrain::sign($package,$this->privatekey),
            'cipher'=>$package
        ];
        $response=Http::post($url,$data);
        if(!$response->isSuccess()){
            throw new \Exception($response->errorMsg);
        }
    }

    public function inrecord(ParkingTraffic $traffic,ParkingRecords $records):bool
    {
        $pdata=[
            'uid'=>$traffic->filings_code.$records->entry_barrier.date('YmdHis',$records->entry_time),
            'arriveID'=>$this->getOrderNo($records),
            'plateNo'=>$records->plate_number,
            'parkingCode'=>$traffic->filings_code,
            'entryNum'=>(string)$records->entry_barrier,
            'gateNo'=>'无',
            'operatorCode'=>'无',
            'totalBerthNum'=>$traffic->total_parking_number,
            'openBerthNum'=>$traffic->open_parking_number,
            'freeBerthNum'=>$traffic->remain_parking_number,
            'arriveTime'=>date('Y-m-d H:i:s',$records->entry_time),
            'parkingType'=>intval($traffic->parking_type),
            'plateColor'=>self::PLATE_COLOR[$records->plate_type],
            'uploadTime'=>date('Y-m-d H:i:s'),
        ];
        $package=Hzbrain::pack($pdata,$this->privatekey);
        $url=self::URL."/api/v2/cp/uploadCarInData";
        $data=[
            'accessID'=>$this->accessid,
            'sign'=>Hzbrain::sign($package,$this->privatekey),
            'cipher'=>$package
        ];
        $response=Http::post($url,$data);
        if($response->isSuccess()){
            if(intval($response->content['resultCode'])!==200){
                throw new \Exception($response->content['msg']);
            }
            $this->uploadPhoto($traffic,$records,'entry');
            return true;
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function outrecord(ParkingTraffic $traffic,ParkingRecords $records):bool
    {
        $pdata=[
            'uid'=>$traffic->filings_code.$records->entry_barrier.date('YmdHis',$records->entry_time),
            'endID'=>$this->getOrderNo($records),
            'plateNo'=>$records->plate_number,
            'parkingCode'=>$traffic->filings_code,
            'gateNo'=>'无',
            'operatorCode'=>'无',
            'totalBerthNum'=>$traffic->total_parking_number,
            'openBerthNum'=>$traffic->open_parking_number,
            'freeBerthNum'=>$traffic->remain_parking_number,
            'arriveTime'=>date('Y-m-d H:i:s',$records->entry_time),
            'parkingType'=>intval($traffic->parking_type),
            'endTime'=>date('Y-m-d H:i:s',$records->exit_time),
            'entryNum'=>(string)$records->entry_barrier,
            'outNum'=>(string)$records->exit_barrier,
            'plateColor'=>self::PLATE_COLOR[$records->plate_type],
            'uploadTime'=>date('Y-m-d H:i:s'),
        ];
        $package=Hzbrain::pack($pdata,$this->privatekey);
        $url=self::URL."/api/v2/cp/uploadCarOutData";
        $data=[
            'accessID'=>$this->accessid,
            'sign'=>Hzbrain::sign($package,$this->privatekey),
            'cipher'=>$package
        ];
        $response=Http::post($url,$data);
        if($response->isSuccess()){
            if(intval($response->content['resultCode'])!==200){
                throw new \Exception($response->content['msg']);
            }
            $this->uploadPhoto($traffic,$records,'exit');
            return true;
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function order(ParkingTraffic $traffic,ParkingRecords $records,PayUnion $union):bool
    {
        //1：现金；2：市民卡钱包； 3：支付宝；4：微信；5：银联；6：市名卡账户
        $paidType=[
            'underline'=>1,
            'stored'=>4,
            'wechat-miniapp'=>4,
            'pay-qrcode'=>4,
            'etc'=>6,
            'wechat-h5'=>4,
            'alipay'=>3,
            'contactless'=>3,
        ];
        //101：先离场后付费；102：场内提前付费；103：出入口付费
        $payType=103;
        if(!$records->exit_time || $records->exit_time>strtotime($union->pay_time)){
            $payType=102;
        }
        if($union->pay_type=='contactless'){
            $payType=101;
        }
        $pdata=[
            'billID'=>$union->out_trade_no,
            'uid'=>$traffic->filings_code.$records->entry_barrier.date('YmdHis',$records->entry_time),
            'plateNo'=>$records->plate_number,
            'parkingCode'=>$traffic->filings_code,
            'chargeFee'=>intval($records->total_fee*100),
            'shouldPay'=>intval($union->pay_price*100),
            'actualPay'=>intval($union->pay_price*100),
            'billTime'=>$union->createtime.":".substr($union->out_trade_no,12,2),
            'dealTime'=>$union->pay_time,
            'paidType'=>$paidType[$union->pay_type],
            'payType'=>$payType,
            'billWay'=>($union->pay_type=='contactless')?1:0,
            'uploadTime'=>date('Y-m-d H:i:s'),
        ];
        $package=Hzbrain::pack($pdata,$this->privatekey);
        $url=self::URL."/api/v2/cp/uploadPayRecord";
        $data=[
            'accessID'=>$this->accessid,
            'sign'=>Hzbrain::sign($package,$this->privatekey),
            'cipher'=>$package
        ];
        $response=Http::post($url,$data);
        if($response->isSuccess()){
            if(intval($response->content['resultCode'])!==200){
                throw new \Exception($response->content['msg']);
            }
            return true;
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function uploadPhoto(ParkingTraffic $traffic,ParkingRecords $records,string $type)
    {
        $now=time();
        if($type=='entry'){
            $uid=$traffic->filings_code.$records->entry_barrier.date('YmdHis',$records->entry_time);
            $photoID=md5($records->entry_photo);
            $time=date('Y-m-d H:i:s',$records->entry_time);
            $phototype=1;
            $photo=$records->entry_photo;
        }
        if($type=='exit'){
            $uid=$traffic->filings_code.$records->entry_barrier.date('YmdHis',$records->entry_time);
            $photoID=md5($records->exit_photo);
            $time=date('Y-m-d H:i:s',$records->exit_time);
            $phototype=2;
            $photo=$records->exit_photo;
        }
        if(!$photo){
            return;
        }
        try{
            $file=file_get_contents($photo.'?x-oss-process=image/resize,w_800/format,jpg');
            $file=base64_encode($file);
        }catch (\Exception $e) {
            $this->output->error($e->getMessage());
            return;
        }
        $pdata=[
            'parkingCode'=>$traffic->filings_code,
            'uid'=>$uid,
            'photoID'=>$photoID,
            'time'=>$time,
            'type'=>$phototype,
            'name'=>basename($photo),
            'file'=>$file,
            'uploadTime'=>date('Y-m-d H:i:s',$now),
        ];
        $package=Hzbrain::pack($pdata,$this->privatekey);
        $url=self::URL."/api/v2/cp/uploadPhoto";
        $data=[
            'accessID'=>$this->accessid,
            'sign'=>Hzbrain::sign($package,$this->privatekey),
            'cipher'=>$package
        ];
        $response=Http::post($url,$data);
        if(!$response->isSuccess()){
            $this->output->error($response->errorMsg);
        }
    }

    public function restberth(ParkingTraffic $traffic){
        $now=time();
        $pdata=[
            'parkingCode'=>$traffic->filings_code,
            'totalBerthNum'=>$traffic->total_parking_number,
            'openBerthNum'=>$traffic->open_parking_number,
            'freeBerthNum'=>$traffic->remain_parking_number,
            'uploadTime'=>date('Y-m-d H:i:s',$now),
        ];
        $package=Hzbrain::pack($pdata,$this->privatekey);
        $url=self::URL."/api/v2/cp/uploadParkingState";
        $data=[
            'accessID'=>$this->accessid,
            'sign'=>Hzbrain::sign($package,$this->privatekey),
            'cipher'=>$package
        ];
        $response=Http::post($url,$data);
        if(!$response->isSuccess()){
            throw new \Exception($response->errorMsg);
        }
    }

    //停车规则
    public function ruleinfo(ParkingTraffic $traffic){

    }

    private function getOrderNo(ParkingRecords $records)
    {
        $words = [0,1,2,3,4,5,6,7,8,9,'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z'];
        $num = $records->id;
        $result = '';
        if ($num == 0) {
            return '0';
        }
        while ($num > 0) {
            $remainder = $num % 36;
            $result = $words[$remainder] . $result;
            $num = floor($num / 36);
        }
        return $result;
    }
}