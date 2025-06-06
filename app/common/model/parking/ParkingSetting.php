<?php
declare(strict_types=1);

namespace app\common\model\parking;

use think\Model;

class ParkingSetting extends Model
{
    public function getSpecialFreeAttr($data)
    {
        if(!$data){
            return [];
        }
        return explode(',',$data);
    }
}
