<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\base\ConstTraits;
use app\common\model\manage\Parking;
use app\common\model\PlateBinding;
use app\common\model\Third;
use think\Model;

class ParkingLog extends Model
{
    public function getDataAttr($data)
    {
        return json_decode($data,true);
    }
}
