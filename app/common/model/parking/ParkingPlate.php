<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\ConstTraits;
use think\Model;

class ParkingPlate extends Model
{
    use ConstTraits;

    const CARMODELS=[
        'large'=>'大型车',
        'medium'=>'中型车',
        'small'=>'小型车',
        'mini'=>'微型车',
        'car'=>'轿车',
        'suv'=>'SUV',
        'mpv'=>'MPV',
        'sports'=>'跑车',
        'pickup'=>'皮卡车',
        'van'=>'面包车',
        'truck'=>'货车'
    ];

    public function cars()
    {
        return $this->hasOne(ParkingCars::class,'id','cars_id');
    }
}
