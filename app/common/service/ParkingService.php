<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingBlack;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingContactless;
use app\common\model\parking\ParkingException;
use app\common\model\parking\ParkingInfield;
use app\common\model\parking\ParkingInfieldRecords;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsDetail;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRecovery;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingScreen;
use app\common\model\parking\ParkingStoredLog;
use app\common\model\parking\ParkingTrafficRecords;
use app\common\model\PayUnion;
use app\common\service\barrier\Utils;
use app\common\service\msg\WechatMsg;
use think\facade\Cache;
use think\facade\Db;

/**
 * 停车服务
 */
class ParkingService extends BaseService{
    use Functions{
        getRulesType as _getRulesType;
        getMatchRules as _getMatchRules;
        getActivitiesFee as _getActivitiesFee;
        getTotalFee as _getTotalFee;
    }
    private $parking;
    private $barrier;
    private $plate_number;
    private $plate_type;
    private $entry_time;
    private $exit_time;
    private $records_type;
    private $photo;
    private $pay_status;
    private $remark;

    public function infieldEntry()
    {
        $setting=$this->parking->setting;
        $plate=$this->getObj(ParkingPlate::class);
        if(!in_array($plate->plate_type,$this->barrier->plate_type)){
            $this->throwException('通道禁止该类型车辆');
        }
        $records=ParkingRecords::where(['parking_id'=>$this->parking->id,'plate_number'=>$plate->plate_number])->whereIn('status',[0,1,6])->find();
        //没有在场记录，则转到外场模式
        if(!$records){
            return $this->entry();
        }
        $rulesType=$this->getRulesType();
        //判断收费规则
        $rules=$this->getMatchRules($rulesType);
        if($rules && !$this->checkBarrierAllowRules($rules,$this->barrier)){
            $ruletitle=$rules->title;
            if(!$ruletitle){
                $ruletitle='临时卡';
            }
            $this->throwException($ruletitle.'禁止通行');
        }
        $infield=false;
        $infields=ParkingInfield::where(['parking_id'=>$this->parking->id])->select();
        foreach ($infields as $value){
            if(in_array($this->barrier->id,$value->entry_barrier)){
                $infield=$value;
                break;
            }
        }
        $allow=false;
        if($infield->rules==ParkingInfield::RULES('外场统一收费')){
            $allow=true;
        }
        if($infield->rules==ParkingInfield::RULES('自定义收费')){
            if($rulesType==ParkingRules::RULESTYPE('临时车')){
                $no_entry_records=$setting[$rulesType.'_no_entry'];
                $records=ParkingRecords::where(['parking_id'=>$this->parking->id,'plate_number'=>$plate->plate_number])->whereIn('status',[0,1,6])->find();
                if($records){
                    $infieldrecords=ParkingInfieldRecords::where(['parking_id'=>$this->parking->id,'records_id'=>$records->id])->find();
                    if(!$infieldrecords){
                        (new ParkingInfieldRecords())->save([
                            'parking_id'=>$this->parking->id,
                            'records_id'=>$records->id,
                            'infield_id'=>$infield->id,
                            'entry_barrier'=>$this->barrier->id,
                            'entry_time'=>$this->entry_time
                        ]);
                        $records->infield_diy=1;
                        $records->save();
                    }
                    $allow=true;
                }
                if(!$records && $no_entry_records){
                    $allow=true;
                }
            }else{
                $allow=true;
            }
        }
        if($allow){
            Utils::inFieldOpen($this->barrier,$plate,$rulesType,$this->records_type);
            ParkingScreen::sendBlackMessage($this->barrier,$plate->plate_number.'内场开闸成功，'.ParkingRules::RULESTYPE[$rulesType]);
        }
        return $allow;
    }

    public function infieldExit()
    {
        $setting=$this->parking->setting;
        $plate=$this->getObj(ParkingPlate::class);
        if(!in_array($plate->plate_type,$this->barrier->plate_type)){
            $this->throwException('通道禁止该类型车辆');
        }
        $rulesType=$this->getRulesType();
        $rules=$this->getMatchRules($rulesType);
        if($setting->match_no_rule==1 && !$rules){
            $this->throwException('无匹配规则，禁止出场');
        }
        if($rules && !$this->checkBarrierAllowRules($rules,$this->barrier)){
            $ruletitle=$rules->title;
            if(!$ruletitle){
                $ruletitle='临时卡';
            }
            $this->throwException($ruletitle.'禁止通行');
        }
        $infield=false;
        $infields=ParkingInfield::where(['parking_id'=>$this->parking->id])->select();
        foreach ($infields as $value){
            if(in_array($this->barrier->id,$value->exit_barrier)){
                $infield=$value;
                break;
            }
        }
        if(!$infield){
            $this->throwException('系统异常，请联系管理员');
        }
        $allow=false;
        if($infield->rules==ParkingInfield::RULES('外场统一收费')){
           $allow=true;
        }
        if($infield->rules==ParkingInfield::RULES('自定义收费')){
            if($rulesType==ParkingRules::RULESTYPE('临时车')){
                $no_entry_records=$setting[$rulesType.'_no_entry'];
                $records=ParkingRecords::where(['parking_id'=>$this->parking->id,'plate_number'=>$plate->plate_number])->whereIn('status',[0,1,6])->find();
                $infieldrecords=false;
                if($records){
                    $infieldrecords=ParkingInfieldRecords::where(['parking_id'=>$this->parking->id,'records_id'=>$records->id])->find();
                    if($infieldrecords){
                        $infieldrecords->exit_barrier=$this->barrier->id;
                        $infieldrecords->exit_time=$this->exit_time;
                        $infieldrecords->save();
                        $allow=true;
                    }
                }
                if((!$records || !$infieldrecords) && $no_entry_records){
                    $allow=true;
                }
            }else{
                $allow=true;
            }
        }
        if($allow){
            Utils::inFieldOpen($this->barrier,$plate,$rulesType,$this->records_type);
            ParkingScreen::sendGreenMessage($this->barrier,$plate->plate_number.'内场开闸成功，'.ParkingRules::RULESTYPE[$rulesType]);
        }
        return $allow;
    }

    //停车入场
    public function entry()
    {
        $parking=$this->parking;
        $setting=$this->parking->setting;
        $plate=$this->getObj(ParkingPlate::class);
        if(!in_array($plate->plate_type,$this->barrier->plate_type)){
            $this->throwException('通道禁止该类型车辆');
        }
        $black=ParkingBlack::where(['plate_number'=>$plate->plate_number,'parking_id'=>$parking->id])->find();
        if($black){
            $this->throwException('黑名单禁止入场');
        }
        $rulesType=$this->getRulesType();
        //同一个通道，15分钟内存在入场，则直接开闸
        $records=ParkingRecords::where(['parking_id'=>$parking->id,'plate_number'=>$plate->plate_number])->whereIn('status',[0,1,6])->find();
        if($records && $records->entry_time+15*60>time()){
            Utils::open($this->barrier,$this->records_type);
            Utils::entryVoiceAndScreen($this->barrier,$plate,$this->records_type,$rulesType);
            ParkingScreen::sendBlackMessage($this->barrier,$plate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType]);
            return true;
        }
        if($records && $setting->one_entry){
            $this->throwException('一进一出异常导致限制入场');
        }
        //追缴逃费情况
        $this->recovery('entry');
        if($setting[$rulesType]){
            //判断车位数
            $parking_space_entry=ParkingRecords::parkingSpaceEntry($parking);
            if($setting[$rulesType.'_space_full'] && $parking_space_entry >= $setting->parking_space_total){
                $this->throwException('车位已满');
            }
            //判断收费规则
            $rules=$this->getMatchRules($rulesType);
            if($setting->match_no_rule==1 && !$rules){
                $this->throwException('无匹配规则，禁止入场');
            }
            //临时车限时入场
            if($rulesType==ParkingRules::RULESTYPE('临时车') && $rules->time_limit_entry && $rules->time_limit_setting){
                $now=intval(date('Hi',time()));
                foreach ($rules->time_limit_setting as $svalue){
                    $period_begin=intval(str_replace(':','',$svalue['period_begin']));
                    $period_end=intval(str_replace(':','',$svalue['period_end']));
                    if($now>=$period_begin && $now<=$period_end){
                        $this->throwException('临时车'.$svalue['period_begin'].'到'.$svalue['period_end'].'禁止入场');
                    }
                }
            }
            if($rules && !$this->checkBarrierAllowRules($rules,$this->barrier)){
                $ruletitle=$rules->title;
                if(!$ruletitle){
                    $ruletitle='临时卡';
                }
                $this->throwException($ruletitle.'禁止通行');
            }
            Utils::open($this->barrier,$this->records_type,function($res) use ($parking,$setting,$records,$rules,$plate,$rulesType,$parking_space_entry){
                if($res){
                    Db::startTrans();
                    try{
                        $insert=[
                            'parking_id'=>$parking->id,
                            'parking_title'=>$parking->title,
                            'rules_type'=>$rulesType,
                            'rules_id'=>$rules?$rules->id:null,
                            'plate_number'=>$plate->plate_number,
                            'plate_type'=>$plate->plate_type,
                            'special'=>$plate->special,
                            'entry_type'=>$this->records_type,
                            'entry_barrier'=>$this->barrier->id,
                            'entry_time'=>$this->entry_time,
                            'entry_photo'=>$this->photo,
                            'cars_id'=>$plate->cars?$plate->cars->id:null,
                            'remark'=>$this->remark,
                            'status'=>ParkingRecords::STATUS('正在场内')
                        ];
                        $precords=new ParkingRecords();
                        $precords->save($insert);
                        //如果存在优惠券，则使用
                        $couponlist=$this->getAllowCoupon($parking,$plate);
                        if(!empty($couponlist)){
                            ParkingMerchantCouponList::createRecordsCoupon($couponlist[0],$precords);
                        }
                        if($records){
                            if($records->status==ParkingRecords::STATUS('未缴费等待')){
                                $records->status=ParkingRecords::STATUS('未缴费出场');
                                $records->save();
                            }else{
                                $records->status=ParkingRecords::STATUS('连续进场异常');
                                $records->save();
                            }
                        }
                        //自动更新车位总数
                        ParkingRecords::parkingSpaceEntry($parking,'entry');
                        //推动到交管平台
                        if($setting->push_traffic){
                            (new ParkingTrafficRecords())->save([
                                'parking_id'=>$parking->id,
                                'records_id'=>$precords->id,
                                'traffic_type'=>'entry',
                                'status'=>0
                            ]);
                            Cache::set('traffic_event',1);
                        }
                        Db::commit();
                        Utils::entryVoiceAndScreen($this->barrier,$plate,$this->records_type,$rulesType);
                        //计算余位
                        if($this->barrier->show_last_space){
                            $last_space=$setting->parking_space_total-$parking_space_entry-1;
                            $last_space=$last_space>0?$last_space:0;
                            Utils::showLastSpace($this->barrier,$this->records_type,$last_space);
                        }
                    }catch (\Exception $e){
                        Db::rollback();
                        throw $e;
                    }
                    //岗亭通知
                    if($this->records_type!=ParkingRecords::RECORDSTYPE('手动操作')){
                        ParkingScreen::sendBlackMessage($this->barrier,$plate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType]);
                    }
                }else{
                    $this->throwException('通道未正常开启');
                }
            });
            return true;
        }
        $this->throwException('停车规则不支持，禁止入场');
    }

    //停车出场
    public function exit()
    {
        $parking=$this->parking;
        $setting=$this->parking->setting;
        $plate=$this->getObj(ParkingPlate::class);
        if(!in_array($plate->plate_type,$this->barrier->plate_type)){
            $this->throwException('通道禁止该类型车辆');
        }
        $black=ParkingBlack::where(['plate_number'=>$plate->plate_number,'parking_id'=>$parking->id])->find();
        if($black){
            $this->throwException('黑名单禁止入场');
        }
        $records=ParkingRecords::where(['parking_id'=>$parking->id,'plate_number'=>$plate->plate_number])->whereIn('status',[0,1,6])->order('id desc')->find();
        $rulesType=$this->getRulesType();
        $rules=$this->getMatchRules($rulesType);
        if($setting->match_no_rule==1 && !$rules){
            $this->throwException('无匹配规则，禁止出场');
        }
        if($rules && !$this->checkBarrierAllowRules($rules,$this->barrier)){
            $ruletitle=$rules->title;
            if(!$ruletitle){
                $ruletitle='临时卡';
            }
            $this->throwException($ruletitle.'禁止通行');
        }
        //追缴逃费情况
        $this->recovery('exit');
        if(!$records){
            //没有入场记录，查看上次出场时间在15分钟内可以再开一次
            $exitRecords=ParkingRecords::where(['parking_id'=>$parking->id,'plate_number'=>$plate->plate_number])->whereIn('status',[3,4,9])->order('id desc')->find();
            if($exitRecords && $exitRecords->exit_time>time()-60*15){
                //内场提前缴费
                $recordsPay=null;
                if($exitRecords->pay_fee>0){
                    $recordsPay=ParkingRecordsPay::where(['records_id'=>$exitRecords->id])->where('pay_id','>',0)->find();
                }
                Utils::open($this->barrier,$this->records_type);
                Utils::exitScreenAndVoice($this->barrier,$plate,$exitRecords,$recordsPay,$this->records_type,$exitRecords->rules_type);
                ParkingScreen::sendGreenMessage($this->barrier,$plate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$exitRecords->rules_type].'，'.ParkingRecords::STATUS[$exitRecords->status]);
                return true;
            }
            //没有入场记录的自动出场车辆
            if($setting[$rulesType.'_no_entry']){
                Utils::havaNoEntryOpen($this->barrier,$plate->plate_number,$this->records_type,true);
                ParkingScreen::sendRedMessage($this->barrier,$plate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType].'，没有入场记录');
                return true;
            }
            //追溯到上次入场
            if($exitRecords && $setting[$rulesType.'_match_last']){
                $parkingtime=$exitRecords->entry_time-$exitRecords->exit_time;
                $modesArr=ParkingMode::where(['parking_id'=>$parking->id,'status'=>'normal'])->cache('parking_mode_'.$parking->id,3600*24)->select();
                $activeMode=false;
                if($rules->rules_type==ParkingRules::RULESTYPE('临时车')){
                    foreach ($modesArr as $mode){
                        if($mode->id==$rules->mode_id){
                            $activeMode=$mode;
                        }
                    }
                }else{
                    foreach ($modesArr as $mode){
                        foreach ($rules->mode as $ruleMode){
                            if($mode->id==$ruleMode['mode_id']){
                                $activeMode=$mode;
                            }
                        }
                    }
                }
                //免费停车时间内离场
                if($activeMode && $activeMode->free_time && $activeMode->free_time*60>$parkingtime){
                    $exitRecords->account_time=null;
                    $exitRecords->exit_time=null;
                    $exitRecords->status=ParkingRecords::STATUS('正在场内');
                    $records=$exitRecords;
                }
            }
            if(!$records){
                $this->throwException('无入场记录');
            }
        }
        if($this->exit_time<$records->entry_time){
            $this->throwException('出场时间大于入场时间');
        }
        if($setting[$rulesType]){
            //查看15分钟内的计费情况
            $access=false;
            if($records->account_time && $records->account_time>time()-15*60 && $records->total_fee==($records->activities_fee+$records->pay_fee)){
                if($records->status==ParkingRecords::STATUS('缴费未出场')){
                    $records->status=ParkingRecords::STATUS('缴费出场');
                }
                $access=true;
            }
            if(!$access){
                $ispay=$this->createOrder($records);
                if(!$ispay){
                    Utils::exitScreenAndVoice($this->barrier,$plate,$records,$this->recordsPay,$this->records_type,$rulesType);
                    ParkingScreen::sendPayMessage($this->barrier,$records,ParkingRules::RULESTYPE[$rulesType],$this->recordsPay->pay_price);
                    return false;
                }
            }
            Utils::open($this->barrier,$this->records_type,function($res) use ($parking,$setting,$records,$rules,$plate,$rulesType){
                if($res){
                    Db::startTrans();
                    try{
                        $records->exit_time=$this->exit_time;
                        $records->exit_type=$this->records_type;
                        $records->exit_barrier=$this->barrier->id;
                        $records->rules_type=$rulesType;
                        $records->rules_id=$rules?$rules->id:null;
                        $records->cars_id=$plate->cars?$plate->cars->id:null;
                        $records->remark=$this->remark;
                        $records->save();
                        //更新车位总数
                        ParkingRecords::parkingSpaceEntry($parking,'exit');
                        //推动到交管平台
                        if($setting->push_traffic){
                            (new ParkingTrafficRecords())->save([
                                'parking_id'=>$parking->id,
                                'records_id'=>$records->id,
                                'traffic_type'=>'exit',
                                'status'=>0
                            ]);
                            Cache::set('traffic_event',1);
                        }
                        Db::commit();
                        Utils::exitScreenAndVoice($this->barrier,$plate,$records,$this->recordsPay,$this->records_type,$rulesType);
                    }catch (\Exception $e){
                        Db::rollback();
                        throw $e;
                    }
                    //岗亭通知
                    if($this->records_type!=ParkingRecords::RECORDSTYPE('手动操作')){
                        ParkingScreen::sendGreenMessage($this->barrier,$plate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType].'，'.ParkingRecords::STATUS[$records->status]);
                    }
                }else{
                    $this->throwException('通道未正常开启');
                }
            });
            return true;
        }
        $this->throwException('停车规则不支持，禁止出场');
    }

    //追缴逃费
    private function recovery(string $type)
    {
        $plate=$this->getObj(ParkingPlate::class);
        $recoverylist=ParkingRecovery::where(['plate_number'=>$plate->plate_number,'pay_id'=>null])->where('total_fee','>',0)->select();
        if($this->records_type==ParkingRecords::RECORDSTYPE('自动识别') && !empty($recoverylist)){
            foreach ($recoverylist as $recovery){
                if(($recovery->search_parking && in_array($this->parking->id,explode(',',$recovery->search_parking))) || is_null($recovery->search_parking)){
                    if($type=='entry'){
                        //给管理员发送入场消息
                        if($recovery->msg){
                            WechatMsg::remindArrearsParking($recovery,$this->parking->title,$this->barrier->title);
                        }
                        $recovery->entry_barrier=$this->barrier->serialno;
                        $recovery->entry_time=time();
                        $recovery->save();
                        if($recovery->entry_set==1 || $recovery->entry_set==2){
                             Cache::set('recovery_event_'.$this->barrier->serialno,$recovery->plate_number,60*15);
                        }
                        if($recovery->entry_set==1){
                            ParkingScreen::sendRedMessage($this->barrier,'车辆存在欠费，付费后才能入场');
                            $this->throwException('车辆存在欠费，请扫入场码付费后进入');
                        }
                        if($recovery->entry_set==2){
                            ParkingScreen::sendRecoveryMessage($this->barrier,$recovery->id,$plate->plate_number,$this->barrier->id,$this->photo);
                            $this->throwException('车辆存在欠费，请人工确认开闸');
                        }
                    }
                    if($type=='exit'){
                        if($recovery->exit_set==1 || $recovery->exit_set==2){
                            Cache::set('recovery_event_'.$this->barrier->serialno,$recovery->plate_number,60*15);
                        }
                        if($recovery->exit_set==1){
                            ParkingScreen::sendRedMessage($this->barrier,'车辆存在欠费，付费后才能出场');
                            $this->throwException('车辆存在欠费，请扫出场码付费后出场');
                        }
                        if($recovery->exit_set==2){
                            ParkingScreen::sendRecoveryMessage($this->barrier,$recovery->id,$plate->plate_number,$this->barrier->id,$this->photo);
                            $this->throwException('车辆存在欠费，请人工确认开闸');
                        }
                    }
                }
            }
        }
    }

    //创建停车订单
    public function createOrder(ParkingRecords $records)
    {
        $plate=$this->getObj(ParkingPlate::class);
        $rulesType=$this->getRulesType();
        $rules=$this->getMatchRules($rulesType);
        $detail=[];
        $totalfee=$this->getTotalFee($records,$this->exit_time,$detail);
        foreach ($detail as &$item){
            $item['records_id']=$records->id;
            $item['parking_id']=$this->parking->id;
        }
        $r=false;
        Db::startTrans();
        try{
            $records->account_time=time();
            $records->rules_type=$rulesType;
            $records->rules_id=$rules?$rules->id:null;
            $records->cars_id=$plate->cars?$plate->cars->id:null;
            ParkingRecordsDetail::where(['records_id'=>$records->id,'parking_id'=>$this->parking->id])->delete();
            ParkingRecordsDetail::insertAll($detail);
            //已经支付过的不能使用优惠券
            [$activities_fee,$activities_time,$coupon_type,$couponlist,$coupontitle]=$this->getActivitiesFee($records,$totalfee);
            //使用优惠券
            if($couponlist){
                ParkingMerchantCouponList::createRecordsCoupon($couponlist,$records);
            }
            $pay_price=formatNumber($totalfee-$records->pay_fee-$records->activities_fee-$activities_fee);
            $records->status=ParkingRecords::STATUS('免费出场');
            if($pay_price<=0 || ($pay_price>0 && $this->checkIsPay($rulesType,$records,['total_fee'=>$totalfee,'activities_time'=>$activities_time,'activities_fee'=>$activities_fee,'pay_fee'=>$records->pay_fee,'pay_price'=>$pay_price]))){
                $r=true;
                if($records->pay_fee>0){
                    $records->status=ParkingRecords::STATUS('缴费出场');
                }
            }
            if($this->records_type==ParkingRecords::RECORDSTYPE('自动识别')){
                $records->exit_time=$this->exit_time;
                $records->exit_barrier=$this->barrier?$this->barrier->id:null;
                $records->exit_photo=$this->photo;
            }
            if($r && $activities_fee){
                $records->activities_fee=$activities_fee;
            }
            if($r && $activities_time){
                $records->activities_time=$activities_time;
            }
            $records->total_fee=$totalfee;
            $records->save();
            if($r && $couponlist){
                ParkingMerchantCouponList::settleCoupon($records,$coupon_type,$couponlist);
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            throw $e;
        }
        return $r;
    }

    public function getTotalFee(ParkingRecords $records,int $exit_time,array &$detail=[])
    {
        $plate=$this->getObj(ParkingPlate::class);
        return $this->_getTotalFee($this->parking,$records,$plate,$exit_time,$detail);
    }

    public function isProvisional():bool
    {
        $plate=$this->getObj(ParkingPlate::class);
        $rulesType=$this->getRulesType();
        //处理月租多位多车
        if($rulesType==ParkingRules::RULESTYPE('月租车')){
            $plates_count=$plate->cars->plates_count;
            $occupat_number=$plate->cars->occupat_number;
            if($plates_count>$occupat_number){
                $records_numbers=ParkingRecords::where([
                    'parking_id'=>$this->parking->id,
                    'cars_id'=>$plate->cars->id,
                    'rules_type'=>ParkingRules::RULESTYPE('月租车')
                ])->where('plate_number','<>',$plate->plate_number)
                    ->whereIn('status',[0,1,6])
                    ->count();
                if($records_numbers>=$occupat_number){
                    $rulesType=ParkingRules::RULESTYPE('临时车');
                }
            }
        }
        if($rulesType==ParkingRules::RULESTYPE('临时车')){
            return true;
        }
        return false;
    }

    public function getRecordsPay()
    {
        return $this->recordsPay;
    }

    protected function init()
    {
        if($this->parking->status!='normal'){
            throw new \Exception('该停车场已经被禁止使用');
        }
        $setting=$this->parking->setting;
        if(!$setting->phone){
            throw new \Exception('停车场紧急联系电话未设置');
        }
        if(!$setting->rules_txt){
            throw new \Exception('停车场收费规则介绍未设置');
        }
        if(!$setting->invoice_entity){
            throw new \Exception('停车场开票主体未设置');
        }
        $this->plate_number=strtoupper(trim($this->plate_number));
        if(!is_car_license($this->plate_number)){
            throw new \Exception('车牌号格式错误');
        }
        if($this->entry_time && $this->entry_time>time()){
            throw new \Exception('入场时间不能大于当前时间');
        }
        if($this->exit_time && $this->exit_time-5>time()){
            throw new \Exception('出场时间不能大于当前时间');
        }
        $plate=ParkingPlate::with(['cars'])->where(function($query){
            $prefix=getDbPrefix();
            $query->where('parking_id',$this->parking->id);
            $query->where('plate_number',$this->plate_number);
            $query->whereRaw("cars_id in (select id from {$prefix}parking_cars where deletetime is null and parking_id={$this->parking->id})");
        })->find();
        if(!$plate){
            $plate=new ParkingPlate();
            $plate->cars=null;
            $plate->parking_id=$this->parking->id;
            $plate->plate_number=trim($this->plate_number);
            $plate->plate_type=$this->getPlateType();
        }else{
            $plate->plate_type=$this->getPlateType();
        }
        $plate->special=$this->getSpecial($plate);
        $this->setObj(ParkingPlate::class,$plate);
    }

    private function getPlateType()
    {
        if($this->plate_type){
            return $this->plate_type;
        }
        $plate=ParkingPlate::where(['plate_number'=>$this->plate_number,'parking_id'=>$this->parking->id])->find();
        if($plate){
            return $plate->plate_type;
        }
        $records=ParkingRecords::where(['plate_number'=>$this->plate_number,'parking_id'=>$this->parking->id])->find();
        if($records){
            return $records->plate_type;
        }
        throw new \Exception('请选择车牌颜色');
    }

    private function checkIsPay(string $rulesType,ParkingRecords $records,array $feeArr)
    {
        $pay_price=$feeArr['pay_price'];
        if($this->records_type==ParkingRecords::RECORDSTYPE('手动操作') && ($this->pay_status==ParkingRecords::STATUS('缴费出场') || $this->pay_status==ParkingRecords::STATUS('缴费未出场'))){
            $payunion=PayUnion::underline(
                $pay_price,
                PayUnion::ORDER_TYPE('停车缴费'),
                ['parking_id'=>$this->parking->id],
                $records->plate_number.'停车缴费'.($this->remark?'，'.$this->remark:'')
            );
            $this->recordsPay=ParkingRecordsPay::createBarrierOrder($records,$payunion,$feeArr,$this->barrier?$this->barrier->id:null);
            $records->pay_fee=$feeArr['pay_fee']+$pay_price;
            $records->status=$this->pay_status;
            return true;
        }
        if($this->records_type==ParkingRecords::RECORDSTYPE('手动操作') && $this->pay_status==ParkingRecords::STATUS('免费出场')){
            $records->status=$this->pay_status;
            return true;
        }
        if($this->records_type==ParkingRecords::RECORDSTYPE('手动操作') && $this->pay_status==ParkingRecords::STATUS('未缴费出场')){
            $records->status=$this->pay_status;
            return true;
        }
        //无感支付检查
        $contactless=ParkingContactless::where('records_id',$records->id)->find();
        if($contactless && !$contactless->status && $contactless->money_limit>=intval($pay_price*100)){
            $recordsPay=ParkingRecordsPay::createBarrierOrder($records,null,$feeArr);
            $user=[
                'parking_id'=>$contactless->parking_id,
                'property_id'=>$contactless->property_id,
                'user_id'=>null
            ];
            $attach=json_encode([
                'records_id'=>$records->id,
                'records_pay_id'=>$recordsPay->id,
                'plate_number'=>$records->plate_number,
                'parking_title'=>$this->parking->title
            ],JSON_UNESCAPED_UNICODE);
            $union=PayUnion::contactless($user,$pay_price,0,$attach,$records->plate_number.'停车缴费');
            if($contactless->pay($records,$union)){
                $this->recordsPay=$recordsPay;
                $records->status=ParkingRecords::STATUS('先离后付出场');
                return true;
            }
        }
        if($rulesType==ParkingRules::RULESTYPE('储值车')){
            $plate=$this->getObj(ParkingPlate::class);
            $balance=$plate->cars->balance;
            $remark=$records->plate_number.'（'.ParkingMode::PLATETYPE[$records->plate_type].'）停车缴费，在场时间：'.date('Y-m-d H:i',$records->entry_time).' 到 '.date('Y-m-d H:i',time());
            //储值车余额足够
            if($balance>=$pay_price){
                $payunion=PayUnion::stored(
                    $balance,
                    PayUnion::ORDER_TYPE('停车缴费'),
                    ['parking_id'=>$this->parking->id],
                    $records->plate_number.'停车缴费'
                );
                $this->recordsPay=ParkingRecordsPay::createBarrierOrder($records,$payunion,$feeArr,$this->barrier?$this->barrier->id:null);
                ParkingStoredLog::addRecordsLog($plate->cars,$pay_price,$remark);
                $records->pay_fee=$feeArr['pay_fee']+$pay_price;
                $records->status=ParkingRecords::STATUS('缴费出场');
                return true;
                //储值车余额不足
            }else{
                switch ($plate->cars->insufficient_balance){
                    case ParkingCars::INSUFFICIENT_BALANCE('禁止出场'):
                        $this->throwException('余额不足，禁止出场');
                    case ParkingCars::INSUFFICIENT_BALANCE('改为临停收费'):
                        break;
                    case ParkingCars::INSUFFICIENT_BALANCE('扣完余额，剩下的钱正常付费'):
                        if($balance<=0){
                            break;
                        }
                        $payunion=PayUnion::stored(
                            $balance,
                            PayUnion::ORDER_TYPE('停车缴费'),
                            ['parking_id'=>$this->parking->id],
                            $records->plate_number.'停车缴费'
                        );
                        ParkingRecordsPay::createBarrierOrder($records,$payunion,$feeArr,$this->barrier?$this->barrier->id:null);
                        ParkingStoredLog::addRecordsLog($plate->cars,$balance,$remark);
                        $records->pay_fee=$feeArr['pay_fee']+$balance;
                        $feeArr['pay_price']=$pay_price-$balance;
                        break;
                }
            }
        }
        $this->recordsPay=ParkingRecordsPay::createBarrierOrder($records,null,$feeArr,$this->barrier?$this->barrier->id:null);
        $records->status=ParkingRecords::STATUS('未缴费等待');
        return false;
    }

    public function getRulesType()
    {
        $plate=$this->getObj(ParkingPlate::class);
        return $this->_getRulesType($plate);
    }

    public function getMatchRules(string $rulsType)
    {
        $plate=$this->getObj(ParkingPlate::class);
        return $this->_getMatchRules($rulsType,$plate);
    }

    public function getActivitiesFee(ParkingRecords $records,$totalFee)
    {
        $plate=$this->getObj(ParkingPlate::class);
        $exit_time=$this->exit_time??time();
        return $this->_getActivitiesFee($this->parking,$plate,$records,$exit_time,$totalFee);
    }

    private function throwException($message)
    {
        $plate=$this->getObj(ParkingPlate::class);
        ParkingException::addException($plate,$this->barrier,$message,$this->photo);
        throw new \Exception($message);
    }
}