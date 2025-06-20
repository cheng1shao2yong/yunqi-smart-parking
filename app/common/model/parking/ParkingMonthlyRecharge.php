<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\base\ConstTraits;
use app\common\model\PayUnion;
use think\facade\Db;
use think\Model;

class ParkingMonthlyRecharge extends Model
{
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';

    protected $type = [
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    public function payunion()
    {
        return $this->hasOne(PayUnion::class,'id','pay_id')->field('id,out_trade_no,pay_type,order_type');
    }

    public function cars()
    {
        return $this->hasOne(ParkingCars::class,'id','cars_id');
    }

    public function plate()
    {
        return $this->hasMany(ParkingPlate::class,'cars_id','cars_id');
    }

    public static function recharge(ParkingCars $cars,$change_type,PayUnion $union,string $starttime='',string $endtime='')
    {
        $money=floatval($union->pay_price);
        if($change_type=='end'){
            if(intval($money*100)%intval($cars->rules->fee*100)!=0){
                throw new \Exception('充值金额必须是月租的整数倍');
            }
            $month=intval($money/$cars->rules->fee);
            $starttime=$cars->endtime+1;
            $endtime=strtotime("+".$month." month",$cars->endtime);
        }
        if($change_type=='now'){
            if(intval($money*100)%intval($cars->rules->fee*100)!=0){
                throw new \Exception('充值金额必须是月租的整数倍');
            }
            $month=intval($money/$cars->rules->fee);
            $starttime=strtotime(date('Y-m-d 00:00:00',time()));
            $endtime=strtotime("+".$month." month",$starttime)-1;
        }
        if($change_type=='time'){
            $starttime=strtotime($starttime.' 00:00:00');
            $endtime=strtotime($endtime.' 23:59:59');
        }
        (new self())->save([
            'parking_id'=>$cars->parking_id,
            'cars_id'=>$cars->id,
            'rules_id'=>$cars->rules_id,
            'pay_id'=>$union->id,
            'money'=>$money,
            'starttime'=>$starttime,
            'endtime'=>$endtime
        ]);
        if($change_type!='end'){
            $cars->starttime=$starttime;
        }
        $cars->endtime=$endtime;
        $cars->save();
        if($cars->plates_count>$cars->occupat_number){
            $records=ParkingRecords::where(['parking_id'=>$cars->parking_id,'cars_id'=>$cars->id])->whereIn('status',[0,6])->order('id desc')->select();
            if(count($records)===0){
                return;
            }
            //获取所有在车位的记录
            $fomateRecords=[];
            foreach ($records as $record){
                $fomateRecords[$record->id]=$record;
            }
            $occupats=ParkingCarsOccupat::where(['parking_id'=>$cars->parking_id,'cars_id'=>$cars->id])->select();
            foreach ($occupats as $occupat){
                //在车位上，且已经到期
                if($occupat->records_id && $occupat->exit_time && $occupat->exit_time>$starttime){
                    //从$recordsIds中删除当前在车位的记录
                    unset($fomateRecords[$occupat->records_id]);
                    $occupat->exit_time=null;
                    $occupat->save();
                }
            }
            $recordsid=array_keys($fomateRecords);
            foreach ($occupats as $occupat){
                //不在车位上
                if(!$occupat->records_id && count($recordsid)>0){
                    $records=$fomateRecords[array_shift($recordsid)];
                    $occupat->records_id=$records->id;
                    $occupat->plate_number=$records->plate_number;
                    $occupat->entry_time=$records->entry_time;
                    $occupat->save();
                }
            }
        }
    }
}
