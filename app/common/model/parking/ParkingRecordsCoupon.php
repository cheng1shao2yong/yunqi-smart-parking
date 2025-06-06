<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\base\ConstTraits;
use app\common\model\manage\Parking;
use think\Model;

class ParkingRecordsCoupon extends Model
{
    use ConstTraits;

    const STATUS=[
        0=>'使用中',
        1=>'已使用'
    ];
}
