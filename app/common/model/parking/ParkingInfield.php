<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\ConstTraits;
use think\Model;

class ParkingInfield Extends Model
{
    use ConstTraits;

    const RULES=[
        'outfield'=>'外场统一收费',
        'diy'=>'自定义收费'
    ];

    public function getEntryBarrierAttr($data)
    {
        if(!$data){
            return [];
        }
        return array_map(function($v){return intval($v);},explode(',',$data));
    }

    public function getExitBarrierAttr($data)
    {
        if(!$data){
            return [];
        }
        return array_map(function($v){return intval($v);},explode(',',$data));
    }

}
