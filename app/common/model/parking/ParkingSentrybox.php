<?php
declare(strict_types=1);

namespace app\common\model\parking;

use think\Model;

class ParkingSentrybox extends Model
{
    public function getRemarkAttr($data)
    {
        if($data){
            return json_decode($data,true);
        }
        return null;
    }

    public function getOperatorAttr($data)
    {
        if($data){
            return json_decode($data,true);
        }
        return null;
    }
}
