<?php
declare(strict_types=1);

namespace app\common\model\manage;

use app\common\model\Area;
use app\common\model\base\BaseModel;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingSetting;
use app\common\model\parking\ParkingTrigger;
use think\facade\Cache;
use think\facade\Db;

class Parking extends BaseModel
{
    public function area()
    {
        return $this->hasOne(Area::class,'id','area_id');
    }

    public function property()
    {
        return $this->hasOne(Property::class,'id','property_id');
    }

    public function setting()
    {
        return $this->hasOne(ParkingSetting::class,'parking_id','id');
    }

    public static function onAfterInsert($parking)
    {
        ParkingSetting::insert([
            'parking_id' => $parking->id
        ]);
    }

    public static function getParkingInfo($parking_id)
    {
        $parking=Db::name('parking')
            ->alias('p')
            ->join('parking_setting ps','p.id=ps.parking_id')
            ->where('p.id','=',$parking_id)
            ->field('p.id,p.title,p.address,p.longitude,p.latitude,ps.phone,ps.rules_txt')
            ->find();
        return $parking;
    }

}
