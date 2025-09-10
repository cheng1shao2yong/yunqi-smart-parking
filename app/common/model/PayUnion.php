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

namespace app\common\model;

use app\common\model\base\ConstTraits;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingCarsApply;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingMerchantLog;
use app\common\model\parking\ParkingMonthlyRecharge;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsCoupon;
use app\common\model\parking\ParkingRecordsFilter;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRecovery;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingScreen;
use app\common\model\parking\ParkingStoredLog;
use app\common\model\parking\ParkingTemporary;
use app\common\model\parking\ParkingTrafficRecords;
use app\common\service\barrier\Utils;
use app\common\service\BarrierService;
use app\common\service\InsideService;
use app\common\service\msg\WechatMsg;
use app\common\service\PayService;
use think\facade\Cache;
use think\facade\Db;
use think\Model;

class PayUnion extends Model{

    use ConstTraits;

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $type = [
        'createtime'     =>  'timestamp:Y-m-d H:i',
        'updatetime'     =>  'timestamp:Y-m-d H:i',
    ];

    protected $append = [
        'pay_type_text',
        'order_type_text'
    ];

    const PAY_TYPE_HANDLE=[
        'yibao'=>'易宝支付',
        'guotong'=>'国通支付',
        'dougong'=>'斗拱支付'
    ];

    const ORDER_TYPE=[
        'parking'=>'停车缴费',
        'parking_monthly'=>'停车月租缴费',
        'parking_stored'=>'停车储值卡充值',
        'merch_recharge'=>'商户充值',
        'parking_recovery'=>'逃费追缴'
    ];

    const PAYTYPE=[
        'underline'=>'线下支付',
        'stored'=>'储值卡支付',
        'wechat-miniapp'=>'微信小程序支付',
        'pay-qrcode'=>'付款码支付',
        'etc'=>'ETC支付',
        'wechat-h5'=>'微信H5支付',
        'alipay'=>'支付宝支付',
    ];

    public function getPayTypeTextAttr($value,$data){
        return self::PAYTYPE[$data['pay_type']];
    }

    public function getOrderTypeTextAttr($value,$data){
        return self::ORDER_TYPE[$data['order_type']];
    }

    public function filter()
    {
        return $this->hasOne(ParkingRecordsFilter::class,'pay_id','id');
    }

    public function park()
    {
        return $this->hasOne(Parking::class,'id','parking_id')->field('id,title');
    }

    public static function alipay($pay_type_handle,$user,$pay_price,$handling_fees,$order_type,$attach,$detail)
    {
        $payunion=new PayUnion();
        $payunion->user_id=isset($user['user_id'])?$user['user_id']:null;
        $payunion->parking_id=isset($user['parking_id'])?$user['parking_id']:null;
        $payunion->property_id=isset($user['property_id'])?$user['property_id']:null;
        $payunion->out_trade_no=create_out_trade_no();
        $payunion->pay_type_handle=$pay_type_handle;
        $payunion->attach=$attach;
        $payunion->pay_type=self::PAYTYPE('支付宝支付');
        $payunion->pay_price=$pay_price;
        $payunion->handling_fees=$handling_fees;
        $payunion->order_type=$order_type;
        $payunion->pay_status=0;
        $payunion->detail=$detail;
        $payunion->save();
        return $payunion;
    }

    public static function wechatminiapp($pay_type_handle,$user,$pay_price,$handling_fees,$order_type,$attach,$detail)
    {
        $payunion=new PayUnion();
        $payunion->user_id=isset($user['user_id'])?$user['user_id']:null;
        $payunion->parking_id=isset($user['parking_id'])?$user['parking_id']:null;
        $payunion->property_id=isset($user['property_id'])?$user['property_id']:null;
        $payunion->out_trade_no=create_out_trade_no();
        $payunion->pay_type_handle=$pay_type_handle;
        $payunion->attach=$attach;
        $payunion->pay_type=self::PAYTYPE('微信小程序支付');
        $payunion->pay_price=$pay_price;
        $payunion->handling_fees=$handling_fees;
        $payunion->order_type=$order_type;
        $payunion->pay_status=0;
        $payunion->detail=$detail;
        $payunion->save();
        return $payunion;
    }

    public static function qrcodePay($pay_type_handle,$user,$pay_price,$handling_fees,$order_type,$attach,$detail)
    {
        $payunion=new PayUnion();
        $payunion->user_id=isset($user['user_id'])?$user['user_id']:null;
        $payunion->parking_id=isset($user['parking_id'])?$user['parking_id']:null;
        $payunion->property_id=isset($user['property_id'])?$user['property_id']:null;
        $payunion->out_trade_no=create_out_trade_no();
        $payunion->pay_type_handle=$pay_type_handle;
        $payunion->attach=$attach;
        $payunion->pay_type=self::PAYTYPE('付款码支付');
        $payunion->pay_price=$pay_price;
        $payunion->handling_fees=$handling_fees;
        $payunion->order_type=$order_type;
        $payunion->pay_status=0;
        $payunion->detail=$detail;
        $payunion->save();
        return $payunion;
    }

    public static function underline($pay_price,$order_type,$user,$detail)
    {
        $payunion=new PayUnion();
        $payunion->parking_id=isset($user['parking_id'])?$user['parking_id']:null;
        $payunion->property_id=isset($user['property_id'])?$user['property_id']:null;
        $payunion->out_trade_no=create_out_trade_no();
        $payunion->pay_type=self::PAYTYPE('线下支付');
        $payunion->pay_price=$pay_price;
        $payunion->order_type=$order_type;
        $payunion->pay_status=1;
        $payunion->pay_time=date('Y-m-d H:i:s',time());
        $payunion->detail=$detail;
        $payunion->save();
        return $payunion;
    }

    public static function stored($pay_price,$order_type,$user,$detail)
    {
        $payunion=new PayUnion();
        $payunion->out_trade_no=create_out_trade_no();
        $payunion->parking_id=isset($user['parking_id'])?$user['parking_id']:null;
        $payunion->property_id=isset($user['property_id'])?$user['property_id']:null;
        $payunion->pay_type=self::PAYTYPE('储值卡支付');
        $payunion->pay_price=$pay_price;
        $payunion->order_type=$order_type;
        $payunion->pay_status=1;
        $payunion->pay_time=date('Y-m-d H:i:s',time());
        $payunion->detail=$detail;
        $payunion->save();
        return $payunion;
    }

    public function paySuccess(string $transaction_id)
    {
        Db::startTrans();
        try{
            $this->pay_time=date('Y-m-d H:i:s',time());
            $this->transaction_id=$transaction_id;
            $this->pay_status=1;
            $this->save();
            switch ($this->order_type){
                case 'merch_recharge':
                    $this->merch_recharge();
                    break;
                case 'parking_monthly':
                    $this->parking_monthly();
                    break;
                case 'parking_stored':
                    $this->parking_stored();
                    break;
                case 'parking':
                    $this->parking();
                    break;
                case 'parking_recovery':
                    $this->parking_recovery();
                    break;
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            throw $e;
        }
    }

    public function refund(float $refund_price,string $refund_cause)
    {
        if($this->pay_price<$this->refund_price+$refund_price){
            throw new \Exception('退款金额不能大于支付金额');
        }
        if($this->pay_type=='underline'){
            $this->refund_price=$this->refund_price+$refund_price;
            $this->save();
            return;
        }
        $pay_type_handle=$this->pay_type_handle;
        $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
        $service=PayService::newInstance([
            'pay_type_handle'=>$pay_type_handle,
            'pay_union'=>$this,
            'sub_merch_no'=>$parking->sub_merch_no,
            'refund_price'=>$refund_price,
            'refund_cause'=>$refund_cause,
        ]);
        if($service->refund()){
            if($this->order_type=='parking'){
                $persent=$parking->parking_records_persent;
            }
            if($this->order_type=='parking_monthly' || $this->order_type=='parking_stored'){
                $persent=$parking->parking_recharge_persent;
            }
            if($this->order_type=='merch_recharge'){
                $persent=$parking->parking_merch_persent;
            }
            $handling_fees=$this->handling_fees-round($refund_price*$persent*0.1);
            if($handling_fees<0){
                $handling_fees=0;
            }
            try{
                Db::startTrans();
                $this->refund_price=$this->refund_price+$refund_price;
                $this->handling_fees=$handling_fees;
                $this->save();
                $refund=new PayRefund();
                $refund->pay_id=$this->id;
                $refund->parking_id=$this->parking_id;
                $refund->refund_price=$refund_price;
                $refund->refund_cause=$refund_cause;
                $refund->refund_time=date('Y-m-d H:i:s',time());
                $refund->save();
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                throw $e;
            }
        }
    }

    private function parking_recovery()
    {
        $attach=json_decode($this->attach,true);
        $barrier_id=$attach['barrier_id'];
        $recovery_id=$attach['recovery_id'];
        $plate_number=$attach['plate_number'];
        $recovery=ParkingRecovery::with(['records'])->whereIn('id',$recovery_id)->select();
        foreach ($recovery as $item){
            $item->pay_id=$this->id;
            $item->save();
            if($item->records){
                $item->records->pay_fee=$item->total_fee;
                $item->records->status=ParkingRecords::STATUS('追缴补缴出场');
                $item->records->save();
            }
        }
        $barrier=ParkingBarrier::find($barrier_id);
        if($barrier && $barrier->barrier_type=='entry'){
            ParkingScreen::sendGreenMessage($barrier,$plate_number.'，支付成功，重新识别车牌');
            Utils::send($barrier,'主动识别');
        }
        if($barrier && $barrier->barrier_type=='exit'){
            ParkingScreen::sendGreenMessage($barrier,$plate_number.'，支付成功，重新识别车牌');
            Utils::send($barrier,'主动识别');
        }
    }

    private function parking()
    {
        $attach=json_decode($this->attach,true);
        $records_pay_id=$attach['records_pay_id'];
        $recordspay=ParkingRecordsPay::with(['records'])->find($records_pay_id);
        $recordspay->pay_id=$this->id;
        $recordspay->save();
        $parking=Parking::cache('parking_'.$recordspay->parking_id,24*3600)->withJoin(['setting'])->find($recordspay->parking_id);
        //更新缴费金额
        $records=$recordspay->records;
        $records->pay_fee=$records->pay_fee+$this->pay_price;
        $status=ParkingRecords::STATUS('缴费未出场');
        if($recordspay->barrier_id){
            //查看有没有新车刷新道闸
            $lastRecordsPay=ParkingRecordsPay::where(['barrier_id'=>$recordspay->barrier_id])->order('id desc')->find();
            if($lastRecordsPay->records_id==$recordspay->records_id){
                $status=ParkingRecords::STATUS('缴费出场');
            }
            $records->exit_type=ParkingRecords::RECORDSTYPE('自动识别');
        }
        //可能属于追缴
        if($records->status==ParkingRecords::STATUS('未缴费出场') || $records->status==ParkingRecords::STATUS('未缴费等待')){
            $recovery=ParkingRecovery::where(['records_id'=>$records->id])->find();
            if($recovery){
                $recovery->pay_id=$this->id;
                $recovery->save();
                $status=ParkingRecords::STATUS('追缴补缴出场');
            }
        }
        if($recordspay->activities_fee){
            $records->activities_fee=$recordspay->activities_fee;
        }
        if($recordspay->activities_time){
            $records->activities_time=$recordspay->activities_time;
        }
        $records->status=$status;
        $records->save();
        $recordscoupon=ParkingRecordsCoupon::where(['status'=>0,'records_id'=>$records->id])->select();
        if(count($recordscoupon)>0){
            $coupon_list_id=[];
            foreach ($recordscoupon as $coupon){
                $coupon_list_id[]=$coupon->coupon_list_id;
            }
            $couponlist=ParkingMerchantCouponList::whereIn('id',$coupon_list_id)->select();
            $coupon_type=$recordscoupon[0]->coupon_type;
            ParkingMerchantCouponList::settleCoupon($records,$coupon_type,$couponlist);
        }
        //过滤支付
        ParkingRecordsFilter::where('records_pay_id',$records_pay_id)->update(['pay_id'=>$this->id]);
        if($status==ParkingRecords::STATUS('缴费出场')){
            /* @var ParkingBarrier $barrier*/
            $barrier=ParkingBarrier::find($recordspay->barrier_id);
            if($barrier->trigger_type=='infield' || $barrier->trigger_type=='outfield'){
                /* @var BarrierService $barrierService*/
                $barrierService=$barrier->getBarrierService();
                $barrierService->payOpen();
                //发送消息
                ParkingScreen::sendGreenMessage($barrier,$records->plate_number.'，支付成功，开启道闸');
                WechatMsg::exit($parking,$records->plate_number);
            }
            if($barrier->trigger_type=='inside' || $barrier->trigger_type=='outside'){
                ParkingScreen::sendGreenMessage($barrier,$records->plate_number.'，支付成功');
                if($parking->pid){
                    $insideBarrier=$barrier;
                    $insideParking=$parking;
                    $outsideParking=Parking::cache('parking_'.$insideParking->pid,24*3600)->withJoin(['setting'])->find($insideParking->pid);
                    $outsideBarrier=ParkingBarrier::where(['serialno'=>$insideBarrier->serialno,'parking_id'=>$outsideParking->id])->find();
                }else{
                    $outsideBarrier=$barrier;
                    $outsideParking=$parking;
                    $insideBarrier=ParkingBarrier::where(['serialno'=>$outsideBarrier->serialno,'trigger_type'=>'inside'])->find();
                    $insideParking=Parking::cache('parking_'.$insideBarrier->parking_id,24*3600)->withJoin(['setting'])->find($insideBarrier->parking_id);
                }
                $theadkey=md5($insideParking->id.'-'.$outsideParking->id.'-'.$insideBarrier->id.'-'.$outsideBarrier->id.'-'.$records->plate_number);
                $service = InsideService::newInstance([
                    'insideParking' => $insideParking,
                    'outsideParking' => $outsideParking,
                    'insideBarrier' => $insideBarrier,
                    'outsideBarrier'=>$outsideBarrier,
                    'plate_number' => $records->plate_number,
                    'plate_type' => $records->plate_type,
                    'photo' => $records->exit_photo
                ],$theadkey);
                if($parking->pid){
                    $service->exit();
                }else{
                    $service->entry();
                }
            }
        }
        //更新车位总数
        ParkingRecords::parkingSpaceEntry($parking,'exit');
        //推动到交管平台
        if($parking->setting->push_traffic && $records->rules_type==ParkingRules::RULESTYPE('临时车')){
            (new ParkingTrafficRecords())->save([
                'parking_id'=>$records->parking_id,
                'records_id'=>$records->id,
                'traffic_type'=>'exit',
                'status'=>0
            ]);
            Cache::set('traffic_event',1);
        }
    }

    private function merch_recharge()
    {
        $attach=json_decode($this->attach,true);
        $parking_id=$attach['parking_id'];
        $merch_id=$attach['merch_id'];
        $merch=ParkingMerchant::where(['id'=>$merch_id,'parking_id'=>$parking_id])->find();
        $money=$this->pay_price;
        if($merch->settle_type=='time'){
            $money=floor($money/$merch->price)*60;
        }
        ParkingMerchantLog::addRechargeLog($merch,$money,$this->id);
    }

    private function parking_stored()
    {
        $attach=json_decode($this->attach,true);
        $plate_number=$attach['plate_number'];
        //储值卡充值
        if(isset($attach['cars_id'])){
            $cars=ParkingCars::with(['rules'])->where(['id'=>$attach['cars_id']])->find();
            ParkingStoredLog::addRechargeLog($cars,$this);
        }
        //申请储值卡
        if(isset($attach['rules_id'])){
            $remark=$attach['remark'];
            $contact=$attach['contact'];
            $mobile=$attach['mobile'];
            $rules=ParkingRules::where(['id'=>$attach['rules_id']])->find();
            if($rules->auto_online_apply=='yes'){
                $plates=[
                    'plate_number'=>$plate_number,
                    'plate_type'=>'blue',
                    'car_models'=>'small',
                ];
                $time=strtotime(date('Y-m-d 00:00:00',time()));
                $endtime=$rules->online_apply_days*3600*24+$time-1;
                $third=Third::where(['user_id'=>$this->user_id,'platform'=>'miniapp'])->find();
                $cars=ParkingCars::addCars($rules,$contact,$mobile,$this->user_id,[$plates],['endtime'=>$endtime,'third_id'=>$third->id]);
                ParkingStoredLog::addRechargeLog($cars,$this);
            }
            if($rules->auto_online_apply=='no'){
                (new ParkingCarsApply())->save([
                    'parking_id'=>$rules->parking_id,
                    'user_id'=>$this->user_id,
                    'apply_type'=>ParkingCarsApply::APPLY_TYPE('申请储值卡'),
                    'plate_number'=>$plate_number,
                    'mobile'=>$mobile,
                    'contact'=>$contact,
                    'rules_type'=>$rules->rules_type,
                    'rules_id'=>$rules->id,
                    'remark'=>$remark?json_encode($remark,JSON_UNESCAPED_UNICODE):'',
                    'pay_id'=>$this->id,
                    'status'=>0
                ]);
            }
        }
    }

    private function parking_monthly()
    {
        $attach=json_decode($this->attach,true);
        $plate_number=$attach['plate_number'];
        if(isset($attach['cars_id'])){
            $remark=$attach['remark'];
            $change_type=$attach['change_type'];
            $cars=ParkingCars::with(['rules'])->where(['id'=>$attach['cars_id']])->find();
            $this->parking_monthly_renew($cars,$plate_number,$change_type,$remark);
        }
        if(isset($attach['rules_id'])){
            $remark=$attach['remark'];
            $contact=$attach['contact'];
            $mobile=$attach['mobile'];
            $rules=ParkingRules::where(['id'=>$attach['rules_id']])->find();
            $this->parking_monthly_apply($rules,$plate_number,$contact,$mobile,$remark);
        }
    }

    private function parking_monthly_apply(ParkingRules $rules,$plate_number,$contact,$mobile,$remark)
    {
        if($rules->auto_online_apply=='yes'){
            $plates=[
                'plate_number'=>$plate_number,
                'plate_type'=>'blue',
                'car_models'=>'small',
            ];
            $third=Third::where(['user_id'=>$this->user_id,'platform'=>'miniapp'])->find();
            $cars=ParkingCars::addCars($rules,$contact,$mobile,$this->user_id,[$plates],['third_id'=>$third->id]);
            ParkingMonthlyRecharge::recharge($cars,'end',$this);
        }
        if($rules->auto_online_apply=='no'){
            $apply=new ParkingCarsApply();
            $apply->save([
                'parking_id'=>$rules->parking_id,
                'user_id'=>$this->user_id,
                'apply_type'=>ParkingCarsApply::APPLY_TYPE('申请月卡'),
                'plate_number'=>$plate_number,
                'mobile'=>$mobile,
                'contact'=>$contact,
                'rules_type'=>$rules->rules_type,
                'rules_id'=>$rules->id,
                'remark'=>$remark?json_encode($remark,JSON_UNESCAPED_UNICODE):'',
                'pay_id'=>$this->id,
                'status'=>0
            ]);
            WechatMsg::monthlyCarApply($apply);
        }
    }

    private function parking_monthly_renew(ParkingCars $cars,$plate_number,$change_type,$remark)
    {
        if($change_type=='end'){
            ParkingMonthlyRecharge::recharge($cars,$change_type,$this);
        }
        if($change_type=='now'){
            $rules=$cars->rules;
            if($rules->auto_online_renew=='yes'){
                ParkingMonthlyRecharge::recharge($cars,$change_type,$this);
            }
            if($rules->auto_online_renew=='no'){
                $plate=ParkingPlate::where(['plate_number'=>$plate_number,'cars_id'=>$cars->id])->find();
                $apply=new ParkingCarsApply();
                $apply->save([
                    'parking_id'=>$cars->parking_id,
                    'user_id'=>$this->user_id,
                    'apply_type'=>ParkingCarsApply::APPLY_TYPE('过期月卡申请续费'),
                    'cars_id'=>$cars->id,
                    'plate_number'=>$plate_number,
                    'plate_type'=>$plate->plate_type,
                    'car_models'=>$plate->car_models,
                    'mobile'=>$cars->mobile,
                    'contact'=>$cars->contact,
                    'rules_type'=>$cars->rules_type,
                    'rules_id'=>$cars->rules_id,
                    'remark'=>$remark?json_encode($remark,JSON_UNESCAPED_UNICODE):'',
                    'pay_id'=>$this->id,
                    'status'=>0
                ]);
                WechatMsg::monthlyCarApply($apply);
            }
        }
    }
}