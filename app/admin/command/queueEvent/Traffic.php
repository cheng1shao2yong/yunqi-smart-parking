<?php
/**
 * ----------------------------------------------------------------------------
 * 行到水穷处，坐看云起时
 * 开发软件，找贵阳云起信息科技，官网地址:https://www.56q7.com/
 * ----------------------------------------------------------------------------
 * Author: 老成
 * email：85556713@qq.com
 */
declare(strict_types=1);

namespace app\admin\command\queueEvent;

//上传到交管平台
use app\common\model\parking\ParkingTraffic;
use app\common\model\parking\ParkingTrafficRecords;
use app\admin\command\queueEvent\traffic\BaseTraffic;
use app\common\model\PayUnion;
use think\facade\Cache;

class Traffic implements EventInterFace
{
    public static $usetime=true;

    private static $invent_time=0;

    private static $parking=[];

    public static function handle($output)
    {
        self::$invent_time++;
        //5分钟更新一次列表
        if(empty(self::$parking) || self::$invent_time%5===0){
            self::$parking=ParkingTraffic::with(['parking'])->where(['status'=>'normal'])->select();
        }
        //杭州10分钟一次心跳
        if(self::$invent_time%10===0){
            foreach (self::$parking as $traffic){
                $area=$traffic->area;
                $class="\\app\\admin\\command\\queueEvent\\traffic\\".$area;
                /* @var BaseTraffic $object */
                $object=new $class($output);
                try{
                    $object->heartbeat($traffic);
                    $object->ruleinfo($traffic);
                    $object->restberth($traffic);
                }catch (\Exception $e){
                    $output->error(date('Y-m-d H:i:s').'-'.$e->getMessage());
                }
            }
        }
        $event=Cache::get('traffic_event');
        if($event){
            $trars=ParkingTrafficRecords::with(['records'])->where(['status'=>0])->select();
            foreach ($trars as $trar){
                $traffic=self::getTraffic($trar->parking_id);
                if(!$traffic){
                    $trar->delete();
                    continue;
                }
                $area=$traffic->area;
                $class="\\app\\admin\\command\\queueEvent\\traffic\\".$area;
                /* @var BaseTraffic $object */
                $object=new $class($output);
                if($trar->traffic_type=='entry'){
                    try{
                        if($object->inrecord($traffic,$trar->records)){
                            $trar->status=1;
                            $trar->save();
                            if($traffic->remain_parking_number>0){
                                $traffic->remain_parking_number--;
                                $traffic->save();
                            }
                        }
                    }catch (\Exception $e){
                        $trar->status=-1;
                        $trar->error=date('Y-m-d H:i:s').'-'.$e->getMessage();
                        $trar->save();
                        $output->error(date('Y-m-d H:i:s').'-'.$e->getMessage());
                    }
                }
                if($trar->traffic_type=='exit'){
                    try{
                        if($object->outrecord($traffic,$trar->records)){
                            $trar->status=1;
                            $trar->save();
                            if($traffic->remain_parking_number<$traffic->open_parking_number){
                                $traffic->remain_parking_number++;
                                $traffic->save();
                            }
                        }
                    }catch (\Exception $e){
                        $trar->status=-1;
                        $trar->error=date('Y-m-d H:i:s').'-'.$e->getMessage();
                        $trar->save();
                        $output->error(date('Y-m-d H:i:s').'-'.$e->getMessage());
                    }
                }
                if($trar->traffic_type=='order'){
                    try{
                        $order=PayUnion::find($trar->pay_id);
                        if($order && $object->order($traffic,$trar->records,$order)){
                            $trar->status=1;
                            $trar->save();
                        }
                    }catch (\Exception $e){
                        $trar->status=-1;
                        $trar->error=date('Y-m-d H:i:s').'-'.$e->getMessage();
                        $trar->save();
                        $output->error(date('Y-m-d H:i:s').'-'.$e->getMessage());
                    }
                }
            }
            Cache::set('traffic_event',0);
        }
    }

    private static function getTraffic(int $parking_id)
    {
        foreach (self::$parking as $traffic){
           if($traffic->parking_id==$parking_id){
               return $traffic;
           }
        }
        return false;
    }
}