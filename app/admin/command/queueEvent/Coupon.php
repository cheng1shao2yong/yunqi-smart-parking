<?php
/**
 * ----------------------------------------------------------------------------
 * 行到水穷处，坐看云起时
 * 开发软件，找贵阳云起信息科技，官网地址:https://www.56q7.com/
 * ----------------------------------------------------------------------------
 * Author: 老成
 * email：85556713@qq.com
 */
declare(strict_types=1);

namespace app\admin\command\queueEvent;

//处理过期优惠券
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantCouponList;
use think\facade\Db;

class Coupon implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $now=time();
        //更新过期优惠券
        $list=ParkingMerchantCouponList::where(function ($query) use ($now){
            $query->where('status','in',[0,2]);
            $query->where('expiretime','<',$now);
        })->select();
        foreach ($list as $item){
            $item['status']=ParkingMerchantCouponList::STATUS('已过期');
            $item->save();
        }
        $prefix=getDbPrefix();
        //处理已经使用的时效券
        $sql="
            SELECT list.id,list.plate_number  from {$prefix}parking_merchant_coupon_list list,{$prefix}parking_merchant_coupon coupon 
            where 
            list.coupon_id=coupon.id 
            and list.status=2 
            and coupon.coupon_type='period' 
            and list.id not in (select coupon_list_id from {$prefix}parking_records_coupon where status=0)
            and list.starttime+coupon.period*3600<{$now}
        ";
        $list=Db::query($sql);
        foreach ($list as $item){
            ParkingMerchantCouponList::where('id',$item['id'])->update(['status'=>ParkingMerchantCouponList::STATUS('已使用')]);
        }
    }
}