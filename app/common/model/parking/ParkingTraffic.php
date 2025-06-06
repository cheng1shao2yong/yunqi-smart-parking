<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\manage\Parking;
use think\Model;

class ParkingTraffic extends Model
{

    const AREA=[
        'Guiyang'=>'贵阳',
        'Kunming'=>'昆明',
    ];

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id');
    }
}
