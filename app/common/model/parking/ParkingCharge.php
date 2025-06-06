<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\ConstTraits;
use app\common\model\manage\Parking;
use think\facade\Db;
use think\Model;

class ParkingCharge extends Model
{
    use ConstTraits;

    const CHANNEL=[
        'fast-cloud-charge'=>'云快充',
        'telaidian'=>'特来电',
        'xiang-qian-chong'=>'向黔充',
        'xiaoju'=>'小橘充电',
    ];

    const TRIGGER=[
        'charge-time'=>'充电时长',
        'charge-fee'=>'充电金额',
        'charge-kwh'=>'充电电量'
    ];

    public function rules()
    {
        return $this->hasOne(ParkingRules::class,'id','rules_id');
    }

    public function send($platenumber,$fee,$kwh,$time)
    {
        $trigger=$this->trigger;
        $rulesvalue=$this->rules_value;
        $coupon_id=false;
        foreach ($rulesvalue as $value){
            $number=floatval($value['number']);
            switch ($trigger){
                case 'charge-time':
                    if(intval($time/60)>=$number){
                        $coupon_id=$value['coupon_id'];
                    }
                    break;
                case 'charge-fee':
                    if(floatval($fee)>=$number){
                        $coupon_id=$value['coupon_id'];
                    }
                    break;
                case 'charge-kwh':
                    if(floatval($kwh)>=$number){
                        $coupon_id=$value['coupon_id'];
                    }
                    break;
            }
        }
        if(!$coupon_id){
            throw new \Exception('没用匹配到优惠规则');
        }
        $merchant=ParkingMerchant::where('id',$this->merch_id)->find();
        $coupon=ParkingMerchantCoupon::where('id',$coupon_id)->find();
        [$records,$couponlist]=ParkingMerchantCouponList::given($merchant,$coupon,$platenumber);
        Db::startTrans();
        try{
            (new ParkingChargeList())->save([
                'parking_id'=>$this->parking_id,
                'plate_number'=>$platenumber,
                'records_id'=>$records?$records->id:null,
                'fee'=>$fee,
                'kwh'=>$kwh,
                'time'=>$time,
                'rules_id'=>$this->use_diy_rules?$this->rules_id:null,
                'coupon_id'=>$coupon_id,
                'coupon_list_id'=>$couponlist->id
            ]);
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            throw new \Exception($e->getMessage());
        }
    }

    public function parking()
    {
        return $this->belongsTo(Parking::class,'parking_id','id');
    }

    public function getRulesValueAttr($data)
    {
        return json_decode($data,true);
    }
}
