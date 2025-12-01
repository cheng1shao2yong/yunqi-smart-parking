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

//逃费自动追缴
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecovery;
use app\common\model\parking\ParkingRecoveryAuto;
use app\common\model\parking\ParkingSetting;
use think\facade\Db;

class AutoRecovery implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $auto=ParkingRecoveryAuto::where(['status'=>'normal'])->select();
        $start=time()-2*24*3600;
        $end=time()-24*3600;
        $arr=[];
        foreach ($auto as $au)
        {
            $search_parking=null;
            if($au->recovery_type==ParkingRecovery::RECOVERYTYPE('车场追缴')){
                $search_parking=$au->parking_id;
            }
            if($au->recovery_type==ParkingRecovery::RECOVERYTYPE('集团追缴')){
                $property_id=Parking::where('id',$au->parking_id)->value('property_id');
                $search_parking=Parking::where('property_id',$property_id)->column('id');
                $search_parking=implode(',',$search_parking);
            }
            $recordslist=ParkingRecords::where(['parking_id'=>$au->parking_id])
                ->whereIn('status',[6,7])
                ->whereBetween('exit_time',[$start,$end])
                ->whereNotIn('id','(select records_id from yun_parking_recovery where parking_id=8)')
                ->select();
            foreach ($recordslist as $records)
            {
                $arr[]=[
                    'parking_id'=>$records->parking_id,
                    'records_id'=>$records->id,
                    'plate_number'=>$records->plate_number,
                    'total_fee'=>round($records->total_fee-$records->pay_fee-$records->activities_fee,2),
                    'search_parking'=>$search_parking,
                    'recovery_type'=>$au->recovery_type,
                    'entry_set'=>$au->entry_set,
                    'exit_set'=>$au->exit_set,
                    'msg'=>$au->msg,
                ];
            }
        }
        $recovery=new ParkingRecovery();
        $recovery->saveAll($arr);
    }
}