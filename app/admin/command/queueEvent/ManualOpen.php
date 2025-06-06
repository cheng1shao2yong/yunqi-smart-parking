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

//处理手动开闸后未缴费的情况
use app\common\model\parking\ParkingManualOpen;
use app\common\model\parking\ParkingRecords;
use think\facade\Cache;

class ManualOpen implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $now=time();
        //每12小时检查一次
        $list=ParkingManualOpen::where(function ($query) use ($now){
            $query->where('is_checked','=',0);
            $query->where('createtime','<',$now-12*3600);
        })->select();
        foreach ($list as $item){
            ParkingRecords::where(function ($query) use ($item){
                $query->where('exit_barrier','=',$item->barrier_id);
                $query->where('status','=',ParkingRecords::STATUS('未缴费等待'));
                $query->where('exit_time','<',$item->createtime+60*2);
                $query->where('exit_time','>',$item->createtime);
            })->update([
                'status'=>ParkingRecords::STATUS('手动开闸出场'),
                'remark'=>$item->message,
            ]);
            $item->is_checked=1;
            $item->save();
        }
    }
}