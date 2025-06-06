<?php
declare(strict_types=1);

namespace app\common\model\parking;

use think\Model;

class ParkingInfieldRecords Extends Model
{
    public function infield()
    {
        return $this->hasOne(ParkingInfield::class,'id','infield_id');
    }
}
