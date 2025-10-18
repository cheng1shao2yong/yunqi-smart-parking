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

use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingWhite;
use app\common\service\barrier\Utils;
use think\facade\Db;

//同步白名单
class Whitelist implements EventInterFace
{
    public static $usetime=true;
    const ROWNUM=20;

    public static function handle($output)
    {
        $prefix=getDbPrefix();
        $date=date('Y-m-d',time());
        $hours=intval(date('H',time()));
        $list=ParkingWhite::where("updatedate<>'{$date}' or updatedate is null")->select();
        foreach ($list as $row){
            if($row->time!==$hours){
                continue;
            }
            $rules_id=explode(',',$row->rules_id);
            $barriers=ParkingBarrier::where(['parking_id'=>$row->parking_id,'status'=>'normal','pid'=>0])->select();
            $count=ParkingCars::whereRaw("parking_id={$row->parking_id} and (synch is null or synch<>CONCAT(starttime,',',endtime))")->whereIn('rules_id',$rules_id)->count();
            $sqls=[];
            if($count>0){
                $rows=ceil($count/self::ROWNUM);
                for($i=0;$i<$rows;$i++){
                    $offset=$i*self::ROWNUM;
                    $cars=ParkingCars::whereRaw("parking_id={$row->parking_id} and (synch is null or synch<>CONCAT(starttime,',',endtime))")->whereIn('rules_id',$rules_id)->limit($offset,self::ROWNUM)->select();
                    if(count($cars)>0){
                        foreach ($barriers as $barrier){
                            Utils::setWhitelist($barrier,$cars);
                        }
                        $ids=[];
                        foreach ($cars as $car){
                            $ids[]=$car->id;
                        }
                        $ids=implode(',',$ids);
                        $sqls[]="update {$prefix}parking_cars set synch=CONCAT(starttime,',',endtime) where id in ({$ids})";
                    }
                }
            }
            foreach ($sqls as $sql){
                Db::execute($sql);
            }
            $row->updatedate=$date;
            $row->save();
        }
    }
}