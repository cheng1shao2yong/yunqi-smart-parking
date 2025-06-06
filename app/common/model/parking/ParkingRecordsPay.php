<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\PayUnion;
use think\Model;

class ParkingRecordsPay extends Model
{
    public function unionpay()
    {
        return $this->hasOne(PayUnion::class,'id','pay_id');
    }

    public function records()
    {
        return $this->hasOne(ParkingRecords::class,'id','records_id');
    }

    //创建通道订单
    public static function createBarrierOrder(ParkingRecords $records,PayUnion $payunion=null,array $feeArr,mixed $barrier_id=null)
    {
        $recordspay=new ParkingRecordsPay();
        $recordspay->save(array_merge($feeArr,[
            'parking_id'=>$records->parking_id,
            'records_id'=>$records->id,
            'pay_id'=>$payunion?$payunion->id:null,
            'barrier_id'=>$barrier_id,
            'createtime'=>time()
        ]));
        return $recordspay;
    }
}
