<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\Third;
use think\Model;

class ParkingMerchantUser extends Model
{
    public function third()
    {
        return $this->hasOne(Third::class,'id','third_id')->field('id,avatar,openname');
    }

    public function merch()
    {
        return $this->hasOne(ParkingMerchant::class,'id','merch_id');
    }
}