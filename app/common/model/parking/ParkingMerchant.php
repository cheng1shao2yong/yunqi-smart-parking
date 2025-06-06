<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\manage\Parking;

class ParkingMerchant extends BaseModel
{
    const SETTLE_TYPE=[
        'before'=>'预先充值使用后扣款',
        'after'=>'先使用，后结算',
        'time'=>'充值时间，用完为止',
    ];

    public function user()
    {
        return $this->hasMany(ParkingMerchantUser::class,'merch_id','id');
    }
}