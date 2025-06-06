<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\base\ConstTraits;
use app\common\model\manage\Parking;
use app\common\model\PayUnion;

class ParkingCarsApply extends BaseModel
{
    use ConstTraits;

    const APPLY_TYPE=[
        'month_apply'=>'申请月卡',
        'month_xufee'=>'过期月卡申请续费',
        'day_apply'=>'申请日租卡',
        'day_renew'=>'过期日租卡续期',
        'stored_apply'=>'申请储值卡'
    ];

    public function pay()
    {
        return $this->hasOne(PayUnion::class,'id','pay_id');
    }

    public function rules()
    {
        return $this->hasOne(ParkingRules::class,'id','rules_id');
    }


    public function cars()
    {
        return $this->hasOne(ParkingCars::class,'id','cars_id');
    }

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id')->field('id,title');
    }
}
