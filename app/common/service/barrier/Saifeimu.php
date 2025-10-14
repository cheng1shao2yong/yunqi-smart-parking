<?php
declare(strict_types=1);

namespace app\common\service\barrier;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingScreen;
use app\common\model\PayUnion;
use app\common\service\BarrierService;
use app\common\service\PayService;

defined('DS') or define('DS',DIRECTORY_SEPARATOR);

class Saifeimu extends BarrierService {

    public static function get_keep_alive(string $serialno):array
    {
        return [];
    }

    public static function get_subject(string $serialno):array{
        $arr=[];
        $arr['/gate/push/result']=0;
        return $arr;
    }

    public static function getTopic(ParkingBarrier $barrier,string $name)
    {
        return '/gate/'.$barrier->serialno.'/command';
    }

    public static function getUniqidName(ParkingBarrier $barrier)
    {
        return 'msgId';
    }

     public static function invoke(ParkingBarrier $barrier,array $message)
     {
         $action=$message['actionName'];
         switch ($action){
             case 'uploadScanReadData':
                 return self::uploadScanReadData($message['data']);
             case 'version':
                 return self::version($message['data']);
         }
     }

    private static function version(string $data)
    {
        return true;
    }

    private static function uploadScanReadData(ParkingBarrier $scanbarrier,array $data)
    {
        $barrier=ParkingBarrier::where(['id'=>$scanbarrier->pid,'status'=>'normal'])->find();
        if(!$barrier){
            throw new \Exception('没有找到对应的道闸');
        }
        $mediumNo=$data['qrcodeData'];
        try{
            $service=false;
            $pay=ParkingRecordsPay::with(['records'])->where([
                'barrier_id'=>$barrier->id,
                'parking_id'=>$barrier->parking_id,
            ])->order('id desc')->find();
            if(!$pay){
                throw new \Exception('没有找到支付订单');
            }
            if($pay->pay_id){
                throw new \Exception('订单已经支付');
            }
            if($pay->createtime<=time() - $barrier->limit_pay_time){
                throw new \Exception('订单已经超时');
            }
            $records=$pay->records;
            $parking=Parking::cache('parking_'.$records->parking_id,24*3600)->withJoin(['setting'])->find($records->parking_id);
            $service=PayService::newInstance([
                'pay_type_handle'=>$parking->pay_type_handle,
                'parking_id'=>$parking->id,
                'sub_merch_no'=>$parking->sub_merch_no,
                'split_merch_no'=>$parking->split_merch_no,
                'persent'=>$parking->parking_records_persent,
                'pay_price'=>$pay->pay_price,
                'mediumNo'=>$mediumNo,
                'terminalId'=>$barrier->serialno,
                'order_type'=>PayUnion::ORDER_TYPE('停车缴费'),
                'order_body'=>$records->plate_number.'停车缴费',
                'attach'=>json_encode([
                    'records_pay_id'=>$pay->id,
                    'records_id'=>$records->id,
                    'plate_number'=>$records->plate_number,
                    'parking_title'=>$parking->title
                ],JSON_UNESCAPED_UNICODE)
            ]);
            $service->qrcodePay();
            $service->destroy();
        }catch (\Exception $e){
            if($service){
                $service->destroy();
            }
            ParkingScreen::sendRedMessage($barrier,'支付失败，'.$e->getMessage());
        }
    }

     public static function isOnline(ParkingBarrier $barrier):bool
     {
         $r=Utils::getVersion($barrier);
         if($r){
             return true;
         }
         return false;
     }

     public static function getMessage(ParkingBarrier $barrier,string $name,array $param=[],mixed $data=''):array{
         if($name=='获取版本号'){
             $result=[
                 'msgId'=>uniqid(),
                 'deviceNo'=>$barrier->serialno,
                 'actionName'=>'version',
                 'ack'=>0
             ];
         }else{
             $result=[
                 'msgId'=>uniqid(),
                 'deviceNo'=>$barrier->serialno,
                 'actionName'=>$name,
                 'ack'=>1,
                 'data'=>$data
             ];
         }
         return $result;
     }
}