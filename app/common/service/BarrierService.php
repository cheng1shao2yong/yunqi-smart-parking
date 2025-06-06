<?php
declare(strict_types=1);
namespace app\common\service;

use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\service\barrier\Zhenshi;

/**
 * 道闸服务
 */
abstract class BarrierService extends BaseService {
    protected ParkingBarrier $barrier;
    protected ParkingPlate $plate;
    protected ParkingRecords $records;
    protected ParkingRecordsPay $recordsPay;
    protected string $recordsType;
    protected string $rulesType;

    public function init()
    {

    }

    abstract public function open():bool;
    abstract public function showPayQRCode();
    abstract public function showEntryQRCode();
    abstract public function inFieldOpen():bool;
    abstract public function voice(string $action);
    abstract public function screen(string $action);
    abstract public function invoke(array $message);
    abstract public function havaNoEntryOpen(string $message,bool $open);
    abstract static public function isOnline(ParkingBarrier $barrier):bool;
    abstract public function showLastSpace(int $last_space);
    abstract public static function getTopic(ParkingBarrier $barrier,string $name);
    abstract public static function getUniqidName(ParkingBarrier $barrier);
    abstract public static function getMessage(ParkingBarrier $barrier,string $name,array $param=[]);
    abstract public static function get_subject(string $serialno);
    abstract public static function get_keep_alive(string $serialno);

    public static function getBarriers(string $topic,array $message=[])
    {
        //赛非姆通道
        if($topic=='/gate/push/result'){
            $sn=$message['deviceNo'];
            $barrier=ParkingBarrier::cache('parking_barrier_'.$sn,24*3600)->where(['serialno'=>$sn,'status'=>'normal'])->where('trigger_type','<>','outside')->find();
            return $barrier;
        }
        //赛非姆道闸
        if($topic=='/camera/push/result'){
            $sn=$message['PlateInfo']['serialno'];
            $barrier=ParkingBarrier::cache('parking_barrier_'.$sn,24*3600)->where(['serialno'=>$sn,'status'=>'normal'])->where('trigger_type','<>','outside')->find();
            return $barrier;
        }
        //成都臻视
        $zhenshi=array_merge(Zhenshi::SUBJECT,Zhenshi::ALIVE);
        foreach ($zhenshi as $key=>$value){
            if(str_ends_with($topic,$key)){
                $sn=substr($topic,0,strlen($topic)-strlen($key));
                $barrier=ParkingBarrier::cache('parking_barrier_'.$sn,24*3600)->where(['serialno'=>$sn,'status'=>'normal'])->where('trigger_type','<>','outside')->find();
                return $barrier;
            }
        }
    }

    public static function getSn(string $topic)
    {
        //成都臻视
        $zhenshi=array_merge(Zhenshi::SUBJECT,Zhenshi::ALIVE);
        foreach ($zhenshi as $key=>$value){
            if(str_ends_with($topic,$key)){
                $sn= substr($topic,0,strlen($topic)-strlen($key));
                return $sn;
            }
        }
    }
}