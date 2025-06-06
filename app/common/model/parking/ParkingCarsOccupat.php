<?php
declare(strict_types=1);

namespace app\common\model\parking;

use think\Model;

class ParkingCarsOccupat extends Model
{
    public function records()
    {
        return $this->hasOne(ParkingRecords::class, 'id', 'records_id');
    }

    public function cars()
    {
        return $this->hasOne(ParkingCars::class, 'id', 'cars_id');
    }
}
