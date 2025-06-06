<?php
declare(strict_types=1);

namespace app\common\model\parking;

use think\Model;

class ParkingChargeList extends Model
{
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $type = [
        'updatetime'     =>  'timestamp:Y-m-d H:i',
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    public function records()
    {
        return $this->hasOne(ParkingRecords::class,'id','records_id');
    }

    public function coupon()
    {
        return $this->hasOne(ParkingMerchantCoupon::class,'id','coupon_id');
    }

    public function couponlist()
    {
        return $this->hasOne(ParkingMerchantCouponList::class,'id','coupon_list_id');
    }
}
