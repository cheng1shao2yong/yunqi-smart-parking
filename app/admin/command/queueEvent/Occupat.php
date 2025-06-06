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

//处理多位多车进出车位
use app\common\model\parking\ParkingCarsOccupat;
use think\facade\Db;

class Occupat implements EventInterFace
{

    public static $usetime=true;
    public static function handle($output)
    {
        self::exit();
        self::entry();
        self::expire();
    }

    //处理车辆出车位
    private static function exit()
    {
        $prefix=getDbPrefix();
        $sql="SELECT occupat.id FROM {$prefix}parking_cars_occupat occupat,{$prefix}parking_records records where occupat.records_id=records.id and occupat.records_id is not null AND ((records.`status` <> 0 AND records.`status` <> 1) OR (records.`status`=6 AND records.updatetime<(UNIX_TIMESTAMP()-30*60)))";
        $list=Db::query($sql);
        foreach ($list as $item){
            ParkingCarsOccupat::where('id',$item['id'])->update([
                'records_id'=>null,
                'plate_number'=>null,
                'entry_time'=>null,
                'exit_time'=>null
            ]);
        }
    }

    //处理车辆入车位
    private static function entry()
    {
        $prefix=getDbPrefix();
        $sql="
            SELECT id as records_id,plate_number,entry_time,cars_id FROM {$prefix}parking_records 
            where cars_id in (SELECT cars_id FROM {$prefix}parking_cars_occupat where records_id is null) 
            and (`status`=0 or `status`=1)
            and id not in (SELECT records_id FROM {$prefix}parking_cars_occupat where records_id is not null)
        ";
        $list=Db::query($sql);
        $time=time();
        foreach ($list as $item){
            $occupat=ParkingCarsOccupat::with(['cars'])->where(['cars_id'=>$item['cars_id'],'records_id'=>null])->find();
            if($occupat && $occupat->cars && $occupat->cars->endtime>$time){
                $occupat->records_id=$item['records_id'];
                $occupat->plate_number=$item['plate_number'];
                $occupat->entry_time=$time;
                $occupat->exit_time=null;
                $occupat->save();
            }
        }
    }

    //处理过期车辆
    private static function expire()
    {
        $prefix=getDbPrefix();
        $time=time();
        $sql="
            SELECT occupat.id as id,cars.endtime as endtime FROM {$prefix}parking_cars_occupat occupat,{$prefix}parking_cars cars
            where occupat.records_id is not null
            and occupat.cars_id=cars.id
            and cars.endtime<{$time}
        ";
        $list=Db::query($sql);
        foreach ($list as $item){
            $endtime=$item['endtime'];
            ParkingCarsOccupat::where('id',$item['id'])->update([
                'exit_time'=>$endtime,
            ]);
        }
    }
}