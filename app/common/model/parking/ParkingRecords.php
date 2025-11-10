<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\base\ConstTraits;
use app\common\model\manage\Parking;
use think\facade\Cache;

class ParkingRecords extends BaseModel
{
    use ConstTraits;

    protected $append=['entry_time_txt','exit_time_txt','status_txt','rules_type_txt','entry_type_txt','exit_type_txt'];

    protected $type = [
        'updatetime'     =>  'timestamp:Y-m-d H:i',
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    const RECORDSTYPE=[
        'normal'=>'自动识别',
        'backend'=>'手动操作',
        'manual'=>'人工确认',
    ];

    const STATUS=[
        '0'=>'正在场内',
        '1'=>'缴费未出场',
        '2'=>'连续进场异常',
        '3'=>'缴费出场',
        '4'=>'免费出场',
        '5'=>'追缴补缴出场',
        '6'=>'未缴费等待',
        '7'=>'未缴费出场',
        '8'=>'手动开闸出场',
        '9'=>'现金缴费出场',
        '10'=>'先离后付出场'
    ];

    public function getEntryTypeTxtAttr($data,$row)
    {
        if(!$row['entry_type']){
            return '';
        }
        return self::RECORDSTYPE[$row['entry_type']];
    }

    public function getExitTypeTxtAttr($data,$row)
    {
        if(!$row['exit_type']){
            return '';
        }
        return self::RECORDSTYPE[$row['exit_type']];
    }

    public function getStatusTxtAttr($data,$row)
    {
        return self::STATUS[$row['status']];
    }

    public function getRulesTypeTxtAttr($data,$row)
    {
        return ParkingRules::RULESTYPE[$row['rules_type']];
    }

    public function getEntryTimeTxtAttr($data,$row)
    {
        return $row['entry_time']?date('Y-m-d H:i',$row['entry_time']):'';
    }

    public function getExitTimeTxtAttr($data,$row)
    {
        return $row['exit_time']?date('Y-m-d H:i',$row['exit_time']):'';
    }

    public function getEntryPhotoAttr($data,$row)
    {
        return $data??request()->domain().'/assets/img/nopic.jpg';
    }

    public function getExitPhotoAttr($data,$row)
    {
        return $data??request()->domain().'/assets/img/nopic.jpg';
    }

    public function rules()
    {
        return $this->hasOne(ParkingRules::class,'id','rules_id');
    }

    public function cars()
    {
        return $this->hasOne(ParkingCars::class,'id','cars_id');
    }

    public function coupon()
    {
        return $this->hasMany(ParkingRecordsCoupon::class,'records_id','id');
    }

    public function filter()
    {
        return $this->hasOne(ParkingRecordsFilter::class,'records_id','id');
    }

    public function recovery()
    {
        return $this->hasOne(ParkingRecovery::class,'records_id','id');
    }

    public function getCouponTxt()
    {
        $coupon=$this->coupon->toArray();
        if(!empty($coupon)){
            if(count($coupon)==1){
                return $coupon[0]['title'];
            }else{
                if($coupon[0]['coupon_type']==ParkingMerchantCoupon::COUPON_TYPE('时长券')){
                    $time=0;
                    foreach ($coupon as $k=>$v){
                        $time+=$v['time'];
                    }
                    return count($coupon).'张时长券合计'.$time.'分钟';
                }
                if($coupon[0]['coupon_type']==ParkingMerchantCoupon::COUPON_TYPE('代金券')){
                    $money=0;
                    foreach ($coupon as $k=>$v){
                        $money+=$v['cash'];
                    }
                    return count($coupon).'张代金券合计'.$money.'元';
                }
                if($coupon[0]['coupon_type']==ParkingMerchantCoupon::COUPON_TYPE('时效券')){
                    $time=0;
                    foreach ($coupon as $k=>$v){
                        $time+=$v['period'];
                    }
                    return count($coupon).'张时效券合计'.$time.'小时';
                }
            }
        }
        return '';
    }

    public function detail()
    {
        return $this->hasMany(ParkingRecordsDetail::class,'records_id','id');
    }

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id');
    }

    public static function parkingSpaceEntry(Parking $parking,string $type='')
    {
        $parking_space_entry=Cache::get('parking_space_entry_'.$parking->id);
        if(!$parking_space_entry){
            $parking_space_entry=ParkingRecords::where(['parking_id'=>$parking->id])->whereIn('status',[0,1])->count();
            Cache::set('parking_space_entry_'.$parking->id,$parking_space_entry);
        }
        if($type=='entry'){
            $setting=$parking->setting;
            if($setting['autoupdate_space_total'] && $parking_space_entry >= $setting->parking_space_total){
                $setting->parking_space_total=$parking_space_entry+1;
                $setting->save();
            }
            Cache::inc('parking_space_entry_'.$parking->id);
        }
        if($type=='exit' && $parking_space_entry>0){
            Cache::dec('parking_space_entry_'.$parking->id);
        }
        return $parking_space_entry;
    }
}
