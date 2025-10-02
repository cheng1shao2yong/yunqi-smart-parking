<?php
declare(strict_types=1);
namespace app\common\service;

use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\service\barrier\Zhenshi;
use think\facade\Cache;

/**
 * 道闸服务
 */
abstract class BarrierService{

    abstract public static function invoke(ParkingBarrier $barrier,array $message);
    abstract public static function isOnline(ParkingBarrier $barrier):bool;
    abstract public static function getTopic(ParkingBarrier $barrier,string $name);
    abstract public static function getUniqidName(ParkingBarrier $barrier);
    abstract public static function getMessage(ParkingBarrier $barrier,string $name,array $param=[],mixed $data=''):array;
    abstract public static function get_subject(string $serialno):array;
    abstract public static function get_keep_alive(string $serialno):array;

    public static function getBarriers(string $topic,array $message=[])
    {
        //赛非姆通道
        if($topic=='/gate/push/result'){
            $sn=$message['deviceNo'];
        }
        //赛非姆道闸
        if($topic=='/camera/push/result'){
            $sn=$message['PlateInfo']['serialno'];
        }
        //成都臻视
        $zhenshi=array_merge(Zhenshi::SUBJECT,Zhenshi::ALIVE);
        foreach ($zhenshi as $key=>$value){
            if(str_ends_with($topic,$key)){
                $sn=substr($topic,0,strlen($topic)-strlen($key));
            }
        }
        $barrier=Cache::get('parking_barrier_'.$sn);
        if(!$barrier){
            $barrier=ParkingBarrier::where(['serialno'=>$sn,'status'=>'normal'])->where('trigger_type','<>','outside')->find();
            if($barrier){
                $barrier->children=ParkingBarrier::where(['pid'=>$barrier->id,'status'=>'normal'])->select();
            }
            Cache::set('parking_barrier_'.$sn,$barrier);
        }
        return $barrier;
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