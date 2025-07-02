<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\base\ConstTraits;
use app\common\model\manage\Parking;
use think\facade\Db;
use think\db\Query;

class ParkingMerchantCouponList extends BaseModel
{
    use ConstTraits;

    const STATUS=[
        0=>'未使用',
        1=>'已使用',
        2=>'使用中',
        3=>'已过期',
        4=>'已作废'
    ];
    public function merch()
    {
        return $this->hasOne(ParkingMerchant::class,'id','merch_id');
    }

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id')->field('id,title');
    }

    public function coupon()
    {
        return $this->hasOne(ParkingMerchantCoupon::class,'id','coupon_id');
    }

    public function plate()
    {
        return $this->hasOne(ParkingPlate::class,'plate_number','plate_number');
    }

    public static function createRecordsCoupon(mixed $couponlist,ParkingRecords $records)
    {
        if($couponlist instanceof ParkingMerchantCouponList){
            $couponlist=[$couponlist];
        }
        foreach ($records->coupon as $value){
            $value->delete();
        }
        $coupon=[];
        foreach ($couponlist as $list){
            if($list->status==ParkingMerchantCouponList::STATUS('未使用')){
                $list->status=ParkingMerchantCouponList::STATUS('使用中');
                $list->starttime=$records->entry_time;
                $list->save();
            }
            $detail=$list->coupon->toArray();
            $coupon[]=[
                'records_id'=>$records->id,
                'parking_id'=>$records->parking_id,
                'merch_id'=>$list->merch_id,
                'coupon_list_id'=>$list->id,
                'coupon_type'=>$detail['coupon_type'],
                'title'=>$detail['title'],
                'time'=>$detail['time'],
                'cash'=>$detail['cash'],
                'discount'=>$detail['discount'],
                'period'=>$detail['period'],
                'status'=>0
            ];
        }
        (new ParkingRecordsCoupon())->saveAll($coupon);
    }

    public static function given(ParkingMerchant $merchant,ParkingMerchantCoupon $coupon,string $plate_number,string $remark='')
    {
        self::checkMerchantSendCoupon($merchant,$coupon);
        $plate_number=strtoupper(trim($plate_number));
        $expiretime=$coupon->effective?time()+$coupon->effective*60*60:strtotime('2099-12-31 23:59:59');
        $records=ParkingRecords::with(['coupon'])->where(['parking_id'=>$coupon->parking_id,'plate_number'=>$plate_number])->whereIn('status',[0,6])->order('id desc')->find();
        if($coupon->before_entry!='allow' && !$records){
            throw new \Exception('该车牌号未入场');
        }
        Db::startTrans();
        try{
            $list=new ParkingMerchantCouponList();
            $list->parking_id=$coupon->parking_id;
            $list->merch_id=$merchant->id;
            $list->merch_title=$merchant->merch_name;
            $list->coupon_id=$coupon->id;
            $list->plate_number=$plate_number;
            $list->expiretime=$expiretime;
            $list->remark=$remark;
            $list->save();
            $couponType='';
            if($records){
                if(empty($records->coupon->toArray())){
                    ParkingMerchantCouponList::createRecordsCoupon($list,$records);
                }else{
                    $couponType=$records->coupon[0]->coupon_type;
                }
            }
            //把前面的券作废
            if($coupon->limit_one){
                $plist=ParkingMerchantCouponList::where(function ($query) use ($coupon,$list,$plate_number){
                    $query->where('parking_id','=',$coupon->parking_id);
                    $query->where('coupon_id','=',$coupon->id);
                    $query->where('plate_number','=',$plate_number);
                    $query->where('status','in',[0,2]);
                    $query->where('id','<>',$list->id);
                })->select();
                foreach ($plist as $p){
                    $p->status=ParkingMerchantCouponList::STATUS('已作废');
                    $p->save();
                }
                if($couponType==$coupon->coupon_type){
                    ParkingMerchantCouponList::createRecordsCoupon($list,$records);
                }
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            throw $e;
        }
        return [$records,$list];
    }

    //计算商户可发的优惠券
    public static function getMerchantLastCoupon(ParkingMerchant $merchant,ParkingMerchantSetting $setting)
    {
        if($setting->limit_send){
            $now=time();
            $start='';
            $title='';
            switch ($setting->limit_type){
                case 'dayly':
                    $start=strtotime('today');
                    $title='今天';
                    break;
                case 'weekly':
                    $start=strtotime('monday this week');
                    $title='本周';
                    break;
                case 'monthly':
                    $start=strtotime('first day of this month');
                    $title='当月';
                    break;
                case 'yearly':
                    $start=strtotime('first day of january this year');
                    $title='今年';
                    break;
            }
            if($setting->limit_send==1){
                $count=ParkingMerchantCouponList::where(function ($query) use ($merchant,$start,$now){
                    $query->where('parking_id','=',$merchant->parking_id);
                    $query->where('merch_id','=',$merchant->id);
                    $query->where('createtime','between',[$start,$now]);
                    $query->where('status','in',[0,1,2]);
                })->count();
                $last=$setting->limit_number-$count;
                if($last<=0){
                    $last=0;
                }
                return $title.'还能发'.$last.'张';
            }
            if($setting->limit_send==2){
                $sum=ParkingMerchantLog::where(function ($query) use ($merchant,$start,$now){
                    /* @var Query $query*/
                    $query->where('parking_id','=',$merchant->parking_id);
                    $query->where('merch_id','=',$merchant->id);
                    $query->where('log_type','=','records');
                    $query->where('createtime','between',[$start,$now]);
                })->sum('change');
                $last=$setting->limit_money-$sum;
                if($last<=0){
                    $last=0;
                }
                return $title.'还能发￥'.$last.'元';
            }
            if($setting->limit_send==3){
                $sum=ParkingMerchantLog::where(function ($query) use ($merchant,$start,$now){
                    /* @var Query $query*/
                    $query->where('parking_id','=',$merchant->parking_id);
                    $query->where('merch_id','=',$merchant->id);
                    $query->where('log_type','=','records');
                    $query->where('createtime','between',[$start,$now]);
                })->sum('change');
                $last=$setting->limit_time-$sum;
                if($last<=0){
                    $last=0;
                }
                return $title.'还能发'.$last.'分钟';
            }
            if($setting->limit_send==4){
                $count=ParkingMerchantCouponList::where(function ($query) use ($merchant,$start,$now){
                    $query->where('parking_id','=',$merchant->parking_id);
                    $query->where('merch_id','=',$merchant->id);
                    $query->where('status','=',2);
                })->count();
                $last=$setting->limit_instock-$count;
                if($last<=0){
                    $last=0;
                }
                return $title.'还能发'.$last.'辆车';
            }
        }
        return '';
    }

    //检查商户是否可以发放优惠券
    public static function checkMerchantSendCoupon(ParkingMerchant $merchant,ParkingMerchantCoupon $coupon)
    {
        if($merchant->status!='normal'){
            throw new \Exception('该商户已经被禁用');
        }
        if($merchant->settle_type=='after' && -$merchant->balance>=$merchant->allow_arrears){
            throw new \Exception('账单超额，请先缴费');
        }
        if($merchant->settle_type=='before' && -$merchant->balance>=$merchant->allow_arrears){
            throw new \Exception('余额不足，请先充值');
        }
        if($merchant->settle_type=='time' && $merchant->balance<=0){
            throw new \Exception('余额不足，请先充值');
        }
        if($coupon->status!='normal'){
            throw new \Exception('优惠券类型已经被禁用');
        }
        $setting=ParkingMerchantSetting::where(['parking_id'=>$merchant->parking_id,'merch_id'=>$merchant->id,'coupon_id'=>$coupon->id])->find();
        if(!$setting){
            throw new \Exception('商户没有配置该优惠券');
        }
        if($setting->limit_send){
            $now=time();
            $start='';
            $title='';
            switch ($setting->limit_type){
                case 'dayly':
                    $start=strtotime('today');
                    $title='每日';
                    break;
                case 'weekly':
                    $start=strtotime('monday this week');
                    $title='每周';
                    break;
                case 'monthly':
                    $start=strtotime('first day of this month');
                    $title='每月';
                    break;
                case 'yearly':
                    $start=strtotime('first day of january this year');
                    $title='每年';
                    break;
            }
            if($setting->limit_send==1){
                $count=ParkingMerchantCouponList::where(function ($query) use ($merchant,$start,$now){
                    $query->where('parking_id','=',$merchant->parking_id);
                    $query->where('merch_id','=',$merchant->id);
                    $query->where('createtime','between',[$start,$now]);
                    $query->where('status','in',[0,1,2]);
                })->count();
                if($count>=$setting->limit_number){
                    throw new \Exception('停车券数量已达到'.$title.'发放上限');
                }
            }
            if($setting->limit_send==2){
                $sum=ParkingMerchantLog::where(function ($query) use ($merchant,$start,$now){
                    /* @var Query $query*/
                    $query->where('parking_id','=',$merchant->parking_id);
                    $query->where('merch_id','=',$merchant->id);
                    $query->where('log_type','=','records');
                    $query->where('createtime','between',[$start,$now]);
                })->sum('change');
                if($sum>=$setting->limit_money){
                    throw new \Exception('停车券金额已达到'.$title.'发放上限');
                }
            }
            if($setting->limit_send==3){
                $sum=ParkingMerchantLog::where(function ($query) use ($merchant,$start,$now){
                    /* @var Query $query*/
                    $query->where('parking_id','=',$merchant->parking_id);
                    $query->where('merch_id','=',$merchant->id);
                    $query->where('log_type','=','records');
                    $query->where('createtime','between',[$start,$now]);
                })->sum('change');
                if($sum>=$setting->limit_time){
                    throw new \Exception('停车券时长已达到'.$title.'发放上限');
                }
            }
            if($setting->limit_send==4){
                $count=ParkingMerchantCouponList::where(function ($query) use ($merchant,$start,$now){
                    $query->where('parking_id','=',$merchant->parking_id);
                    $query->where('merch_id','=',$merchant->id);
                    $query->where('status','=',2);
                })->count();
                if($count>=$setting->limit_instock){
                    throw new \Exception('在场车辆已达到上限');
                }
            }
        }
        return true;
    }

    public static function settleCoupon(ParkingRecords $records,string $coupon_type,$couponlist)
    {
        if($couponlist && count($couponlist)>0){
            $merch_id=$couponlist[0]->merch_id;
            $merch=ParkingMerchant::find($merch_id);
            if(!$merch){
                return;
            }
            $parking_id=$couponlist[0]->parking_id;
            $change_fee=$records->activities_fee;
            $change_time=$records->activities_time;
            $prefix=getDbPrefix();
            if($records->activities_fee>0){
                foreach ($couponlist as $key=>$list){
                    $setting=ParkingMerchantSetting::where(['parking_id'=>$parking_id,'merch_id'=>$merch_id,'coupon_id'=>$list->coupon_id])->find();
                    $status=ParkingMerchantCouponList::STATUS('已使用');
                    if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时效券') && $key==count($couponlist)-1){
                        $status=ParkingMerchantCouponList::STATUS('使用中');
                    }
                    //按固定金额结算
                    if($setting->settle_type=='money'){
                        if($key===0){
                            $change_fee=0;
                        }
                        //时效券只结算第一次
                        if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时效券')){
                            $isuse=ParkingRecordsCoupon::where(['coupon_list_id'=>$list->id,'status'=>1])->find();
                            if(!$isuse){
                                $change_fee+=$setting->settle_money;
                            }
                        }else{
                            $change_fee+=$setting->settle_money;
                        }
                    }
                    //按固定时间结算
                    if($setting->settle_type=='time'){
                        if($key===0){
                            $change_time=0;
                        }
                        //时效券只结算第一次
                        if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时效券')){
                            $isuse=ParkingRecordsCoupon::where(['coupon_list_id'=>$list->id,'status'=>1])->find();
                            if(!$isuse){
                                $change_time+=$setting->settle_time*60;
                            }
                        }else{
                            $change_time+=$setting->settle_time*60;
                        }
                    }
                    //如果按停车费结算，且存在上限
                    if($setting->settle_type=='normal' && $setting->settle_max && ($merch->settle_type=='before' || $merch->settle_type=='after')){
                        $sql="SELECT SUM(activities_fee) as activities_fee FROM {$prefix}parking_records WHERE id in (SELECT records_id FROM {$prefix}parking_records_coupon WHERE coupon_list_id={$list->id} and `status`=1)";
                        $actfee=Db::query($sql);
                        $actfee=$actfee[0]['activities_fee'];
                        if(($actfee+$change_fee)>$setting->settle_max){
                            $change_fee=($setting->settle_max-$actfee)>0?$setting->settle_max-$actfee:0;
                        }
                    }
                    //如果按停车时长结算，且存在上限
                    if($setting->settle_type=='normal' && $setting->settle_max && $merch->settle_type=='time'){
                        $sql="SELECT SUM(activities_time) as activities_time FROM {$prefix}parking_records WHERE id in (SELECT records_id FROM {$prefix}parking_records_coupon WHERE coupon_list_id={$list->id} and `status`=1)";
                        $acttime=Db::query($sql);
                        $acttime=$acttime[0]['activities_time'];
                        if(($acttime+$change_time)>$setting->settle_max*60){
                            $change_time=($setting->settle_max*60-$acttime)>0?$setting->settle_max*60-$acttime:0;
                        }
                    }
                    ParkingRecordsCoupon::where(['coupon_list_id'=>$list->id,'records_id'=>$records->id])->update(['status'=>1]);
                    $list->status=$status;
                    $list->save();
                }
            }else{
                foreach ($couponlist as $list){
                    $list->status=ParkingMerchantCouponList::STATUS('已作废');
                    $list->save();
                    ParkingRecordsCoupon::where(['coupon_list_id'=>$list->id,'records_id'=>$records->id])->delete();
                }
            }
            $remark='【'.$records->plate_number.'】停车，'.date('Y-m-d H:i',$records->entry_time).' 到 '.date('Y-m-d H:i',$records->exit_time);
            if($change_time && $merch->settle_type=='time'){
                $minits=intval($change_time/60);
                if($merch->price_time){
                    $rx=intval($minits/$merch->price_time)*$merch->price_time;
                    if($minits%$merch->price_time>0){
                        $rx+=$merch->price_time;
                    }
                    $minits=$rx;
                }
                ParkingMerchantLog::addRecordsLog($merch,$records->id,$minits,$remark);
            }
            if($change_fee && ($merch->settle_type=='before' || $merch->settle_type=='after')){
                ParkingMerchantLog::addRecordsLog($merch,$records->id,$change_fee,$remark);
            }
        }
    }
}