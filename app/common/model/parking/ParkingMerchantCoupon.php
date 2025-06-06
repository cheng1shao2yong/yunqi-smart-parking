<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\base\ConstTraits;
use think\db\Query;
use think\facade\Db;

class ParkingMerchantCoupon extends BaseModel
{
    use ConstTraits;

    protected $append=['coupon_type_txt'];
    //1、单次停车免费券，折扣券，时效券只能使用一张，时长券，代金券可以使用多张
    //2、时效券是停车时就开始使用，其他的都是出场时使用
    const COUPON_TYPE=[
        'free'=>'免费券',
        'time'=>'时长券',
        'cash'=>'代金券',
        'discount'=>'折扣券',
        'period'=>'时效券',
        'timespan'=>'时段券'
    ];

    public static function onAfterInsert($model)
    {
        $model->weigh=$model->id;
        $model->save();
    }

    public function getCouponTypeTxtAttr($value,$data)
    {
        return self::COUPON_TYPE[$data['coupon_type']];
    }

    public function getTimespanAttr($value,$data)
    {
        if($value){
            $timespan=json_decode($value,true);
            return $timespan;
        }else{
            return [];
        }
    }

    public function records()
    {
        return $this->hasOne(ParkingRecords::class,'id','records_id');
    }
}