<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\library\ParkingAccount;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBlack;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingException;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsDetail;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingScreen;
use app\common\model\parking\ParkingStoredLog;
use app\common\model\parking\ParkingTrafficRecords;
use app\common\model\PayUnion;
use app\common\service\barrier\Utils;
use think\facade\Cache;
use think\facade\Db;
/**
 * 场内场停车服务
 */
class InsideService extends BaseService{
    use Functions;
    private $insideParking;
    private $outsideParking;
    private $insideBarrier;
    private $outsideBarrier;
    private $outsidePlate;
    private $insidePlate;
    private $plate_number;
    private $plate_type;
    private $photo;

    protected function init()
    {
        if($this->outsideParking->status!='normal'){
            throw new \Exception('外停车场已经被禁止使用');
        }
        if(!$this->outsideParking->setting->phone){
            throw new \Exception('外停车场紧急联系电话未设置');
        }
        if(!$this->outsideParking->setting->rules_txt){
            throw new \Exception('外停车场收费规则介绍未设置');
        }
        if(!$this->outsideParking->setting->invoice_entity){
            throw new \Exception('外停车场开票主体未设置');
        }
        if($this->insideParking->status!='normal'){
            throw new \Exception('内停车场已经被禁止使用');
        }
        if(!$this->insideParking->setting->phone){
            throw new \Exception('内停车场紧急联系电话未设置');
        }
        if(!$this->insideParking->setting->rules_txt){
            throw new \Exception('内停车场收费规则介绍未设置');
        }
        if(!$this->insideParking->setting->invoice_entity){
            throw new \Exception('内停车场开票主体未设置');
        }
        $this->initPlates();
    }

    //入场
    public function entry():bool
    {
        $outsideCallback=null;
        $outsideNext=null;
        $insideCallback=null;
        $insideNext=null;
        if($this->insideEntry($insideCallback,$insideNext) && $this->outsideExit($outsideCallback,$outsideNext)){
            Utils::open($this->insideBarrier,ParkingRecords::RECORDSTYPE('自动识别'));
            Db::startTrans();
            try{
                if($outsideCallback){
                    $outsideCallback();
                }
                if($insideCallback){
                    $insideCallback();
                }
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                throw $e;
            }
            if($outsideNext){
                $outsideNext();
            }
            if($insideNext){
                $insideNext();
            }
            return true;
        }
        return false;
    }

    //出场
    public function exit():bool
    {
        $insideCallback=null;
        $insideNext=null;
        $outsideCallback=null;
        $outsideNext=null;
        if($this->outsideEntry($insideCallback,$insideNext) && $this->insideExit($outsideCallback,$outsideNext)){
            Utils::open($this->insideBarrier,ParkingRecords::RECORDSTYPE('自动识别'));
            Db::startTrans();
            try{
                if($outsideCallback){
                    $outsideCallback();
                }
                if($insideCallback){
                    $insideCallback();
                }
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                throw $e;
            }
            if($outsideNext){
                $outsideNext();
            }
            if($insideNext){
                $insideNext();
            }
            return true;
        }
        return false;
    }

    //内场出场
    private function insideExit(&$callback,&$next):bool
    {
        $insideSetting=$this->insideParking->setting;
        if(!in_array($this->insidePlate->plate_type,$this->insideBarrier->plate_type)){
            $this->inSideException('内场通道禁止该类型车辆');
        }
        $records=ParkingRecords::where(['parking_id'=>$this->insideParking->id,'plate_number'=>$this->insidePlate->plate_number])->whereIn('status',[0,1,6])->order('id desc')->find();
        $rulesType=$this->getRulesType($this->insidePlate);
        $rules=$this->getMatchRules($rulesType,$this->insidePlate);
        if($insideSetting->match_no_rule==1 && !$rules){
            $this->inSideException('内场无匹配规则，禁止出场');
        }
        if($rules && !$this->checkBarrierAllowRules($rules,$this->insideBarrier)){
            $ruletitle=$rules->title;
            if(!$ruletitle){
                $ruletitle='临时卡';
            }
            $this->inSideException($ruletitle.'内场禁止通行');
        }
        if(!$records){
            //没有入场记录，查看上次出场时间在15分钟内可以再开一次
            $exitRecords=ParkingRecords::where(['parking_id'=>$this->insideParking->id,'plate_number'=>$this->insidePlate->plate_number])->whereIn('status',[3,4,9])->order('id desc')->find();
            if($exitRecords && $exitRecords->exit_time>time()-60*15){
                $next=function () use ($exitRecords){
                    /* @var BarrierService $barrierService*/
                    $barrierService=$this->insideBarrier->getBarrierService();
                    $barrierService->setParam([
                        'recordsType'=>ParkingRecords::RECORDSTYPE('自动识别'),
                        'rulesType'=>$exitRecords->rules_type,
                        'plate'=>$this->insidePlate,
                        'records'=>$exitRecords
                    ]);
                    //内场提前缴费
                    if($exitRecords->pay_fee>0){
                        $recordsPay=ParkingRecordsPay::where(['records_id'=>$exitRecords->id])->where('pay_id','>',0)->find();
                        $barrierService->setParam(['recordsPay'=>$recordsPay]);
                    }
                    $barrierService->screen('exit');
                    $barrierService->voice('exit');
                    //岗亭通知
                    ParkingScreen::sendGreenMessage($this->insideBarrier,$this->insidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$exitRecords->rules_type].'，'.ParkingRecords::STATUS[$exitRecords->status]);
                };
                return true;
            }
            //没有入场记录的自动出场车辆
            if($insideSetting[$rulesType.'_no_entry']){
                $next=function () use ($exitRecords,$rulesType){
                    /* @var BarrierService $barrierService*/
                    $barrierService=$this->insideBarrier->getBarrierService();
                    $barrierService->setParam([
                        'recordsType'=>ParkingRecords::RECORDSTYPE('自动识别'),
                    ]);
                    $barrierService->havaNoEntryOpen(false);
                    ParkingScreen::sendRedMessage($this->insideBarrier,$this->insidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType].'，没有入场记录');
                };
                return true;
            }
            //追溯到上次入场
            if($exitRecords && $insideSetting[$rulesType.'_match_last']){
                $parkingtime=$exitRecords->entry_time-$exitRecords->exit_time;
                $modesArr=ParkingMode::where(['parking_id'=>$this->insideParking->id,'status'=>'normal'])->cache('parking_mode_'.$this->insideParking->id,3600*24)->select();
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
                $this->inSideException('内场无入场记录');
            }
        }
        if(time()<$records->entry_time){
            $this->inSideException('内场出场时间大于入场时间'.$records->id);
        }
        if($insideSetting[$rulesType]){
            //查看15分钟内的计费情况
            $access=false;
            if($records->account_time && $records->account_time>time()-15*60 && $records->total_fee==($records->activities_fee+$records->pay_fee)){
                $access=true;
            }
            if(!$access){
                $ispay=$this->createOrder($this->insideParking,$records);
                if(!$ispay){
                    $barrierService=$this->insideBarrier->getBarrierService();
                    $barrierService->setParam([
                        'recordsType'=>ParkingRecords::RECORDSTYPE('自动识别'),
                        'plate'=>$this->insidePlate,
                        'records'=>$records,
                        'recordsPay'=>$this->recordsPay,
                        'rulesType'=>$rulesType
                    ]);
                    $barrierService->screen('exit');
                    $barrierService->voice('exit');
                    ParkingScreen::sendRedMessage($this->outsideBarrier,$this->outsidePlate->plate_number.'等待内场支付');
                    ParkingScreen::sendRedMessage($this->insideBarrier,$this->insidePlate->plate_number.ParkingRules::RULESTYPE[$rulesType].'，入场时间'.$records->entry_time_txt.'，待支付'.($this->recordsPay->pay_price).'元');
                    return false;
                }
            }
            $callback=function () use ($records,$rules,$rulesType,$insideSetting){
                $status=ParkingRecords::STATUS('免费出场');
                if($records->pay_fee>0){
                    $status=ParkingRecords::STATUS('缴费出场');
                }
                $records->exit_time=time();
                $records->exit_type=ParkingRecords::RECORDSTYPE('自动识别');
                $records->exit_barrier=$this->insideBarrier->id;
                $records->rules_type=$rulesType;
                $records->rules_id=$rules?$rules->id:null;
                $records->cars_id=$this->insidePlate->cars?$this->insidePlate->cars->id:null;
                $records->status=$status;
                $records->save();
                //更新车位总数
                ParkingRecords::parkingSpaceEntry($this->insideParking,'exit');
                //推动到交管平台
                if($insideSetting->push_traffic && $records->rules_type==ParkingRules::RULESTYPE('临时车')){
                    (new ParkingTrafficRecords())->save([
                        'parking_id'=>$this->insideParking->id,
                        'records_id'=>$records->id,
                        'traffic_type'=>'exit',
                        'status'=>0
                    ]);
                    Cache::set('traffic_event',1);
                }
            };
            $next=function () use ($records,$rulesType){
                /* @var BarrierService $barrierService*/
                $barrierService=$this->insideBarrier->getBarrierService();
                $barrierService->setParam([
                    'recordsType'=>ParkingRecords::RECORDSTYPE('自动识别'),
                    'rulesType'=>$rulesType,
                    'plate'=>$this->insidePlate,
                    'records'=>$records,
                ]);
                if($records->pay_fee>0){
                    $recordsPay=ParkingRecordsPay::where(['records_id'=>$records->id])->where('pay_id','>',0)->find();
                    $barrierService->setParam(['recordsPay'=>$recordsPay]);
                }
                $barrierService->screen('exit');
                $barrierService->voice('exit');
                //岗亭通知
                ParkingScreen::sendGreenMessage($this->insideBarrier,$this->insidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType].'，'.ParkingRecords::STATUS[$records->status]);
            };
            return true;
        }
        $this->inSideException('内场停车规则不支持，禁止出场');
    }

    //外场入场
    private function outsideEntry(&$callback,&$next):bool
    {
        $outsideSetting=$this->outsideParking->setting;
        if(!in_array($this->outsidePlate->plate_type,$this->outsideBarrier->plate_type)){
            $this->outSideException('外场通道禁止该类型车辆');
        }
        $rulesType=$this->getRulesType($this->outsidePlate);
        $black=ParkingBlack::where(['plate_number'=>$this->outsidePlate->plate_number,'parking_id'=>$this->outsideParking->id])->find();
        if($black){
            $this->outSideException('外场黑名单禁止入场');
        }
        //同一个通道，15分钟内存在入场，则直接开闸
        $records=ParkingRecords::where(['parking_id'=>$this->outsideParking->id,'plate_number'=>$this->outsidePlate->plate_number])->whereIn('status',[0,1,6])->find();
        if($records && $records->entry_time+15*60>time()){
            $next=function () use ($rulesType){
                //岗亭通知
                ParkingScreen::sendBlackMessage($this->outsideBarrier,$this->outsidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType]);
            };
            return true;
        }
        if($records && $outsideSetting->one_entry){
            $this->outSideException('外场一进一出异常导致限制入场');
        }
        if($outsideSetting[$rulesType]){
            //判断车位数
            $parking_space_entry=ParkingRecords::parkingSpaceEntry($this->outsideParking);
            if($outsideSetting[$rulesType.'_space_full'] && $parking_space_entry >= $outsideSetting->parking_space_total){
                $this->outSideException('外场车位已满');
            }
            //判断收费规则
            $rules=$this->getMatchRules($rulesType,$this->outsidePlate);
            if($outsideSetting->match_no_rule==1 && !$rules){
                $this->outSideException('外场无匹配规则，禁止入场');
            }
            if($rules && !$this->checkBarrierAllowRules($rules,$this->outsideBarrier)){
                $ruletitle=$rules->title;
                if(!$ruletitle){
                    $ruletitle='临时卡';
                }
                $this->outSideException($ruletitle.'外场禁止通行');
            }
            $callback=function () use ($outsideSetting,$parking_space_entry,$records,$rules,$rulesType){
                $insert=[
                    'parking_id'=>$this->outsideParking->id,
                    'parking_title'=>$this->outsideParking->title,
                    'rules_type'=>$rulesType,
                    'rules_id'=>$rules?$rules->id:null,
                    'plate_number'=>$this->outsidePlate->plate_number,
                    'plate_type'=>$this->outsidePlate->plate_type,
                    'special'=>$this->outsidePlate->special,
                    'entry_type'=>ParkingRecords::RECORDSTYPE('自动识别'),
                    'entry_barrier'=>$this->outsideBarrier->id,
                    'entry_time'=>time(),
                    'entry_photo'=>$this->photo,
                    'cars_id'=>$this->outsidePlate->cars?$this->outsidePlate->cars->id:null,
                    'status'=>ParkingRecords::STATUS('正在场内')
                ];
                $precords=new ParkingRecords();
                $precords->save($insert);
                //如果存在优惠券，则使用
                $couponlist=$this->getAllowCoupon($this->outsideParking,$this->outsidePlate);
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
                ParkingRecords::parkingSpaceEntry($this->outsideParking,'entry');
                //推动到交管平台
                if($outsideSetting->push_traffic && $precords->rules_type==ParkingRules::RULESTYPE('临时车')){
                    (new ParkingTrafficRecords())->save([
                        'parking_id'=>$this->outsideParking->id,
                        'records_id'=>$precords->id,
                        'traffic_type'=>'entry',
                        'status'=>0
                    ]);
                    Cache::set('traffic_event',1);
                }
            };
            $next=function () use ($rulesType){
                //岗亭通知
                ParkingScreen::sendBlackMessage($this->outsideBarrier,$this->outsidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType]);
            };
            return true;
        }
        $this->outSideException('外场停车规则不支持，禁止入场');
    }

    //外场出场
    private function outsideExit(&$callback,&$next):bool
    {
        $outsideSetting=$this->outsideParking->setting;
        if(!in_array($this->outsidePlate->plate_type,$this->outsideBarrier->plate_type)){
            $this->outSideException('外场通道禁止该类型车辆');
        }
        $records=ParkingRecords::where(['parking_id'=>$this->outsideParking->id,'plate_number'=>$this->outsidePlate->plate_number])->whereIn('status',[0,1,6])->order('id desc')->find();
        $rulesType=$this->getRulesType($this->outsidePlate);
        $rules=$this->getMatchRules($rulesType,$this->outsidePlate);
        if($outsideSetting->match_no_rule==1 && !$rules){
            $this->outSideException('外场无匹配规则，禁止出场');
        }
        if($rules && !$this->checkBarrierAllowRules($rules,$this->outsideBarrier)){
            $ruletitle=$rules->title;
            if(!$ruletitle){
                $ruletitle='临时卡';
            }
            $this->outSideException($ruletitle.'外场禁止通行');
        }
        if(!$records){
            //没有入场记录，查看上次出场时间在15分钟内可以再开一次
            $exitRecords=ParkingRecords::where(['parking_id'=>$this->outsideParking->id,'plate_number'=>$this->outsidePlate->plate_number])->whereIn('status',[3,4,9])->order('id desc')->find();
            if($exitRecords && $exitRecords->exit_time>time()-60*15){
                $next=function () use ($exitRecords){
                    //岗亭通知
                    ParkingScreen::sendGreenMessage($this->outsideBarrier,$this->outsidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$exitRecords->rules_type].'，'.ParkingRecords::STATUS[$exitRecords->status]);
                };
                return true;
            }
            //没有入场记录的自动出场车辆
            if($outsideSetting[$rulesType.'_no_entry']){
                $next=function () use ($rulesType){
                    //岗亭通知
                    ParkingScreen::sendRedMessage($this->outsideBarrier,$this->outsidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType].'，没有入场记录');
                };
                return true;
            }
            //追溯到上次入场
            if($exitRecords && $outsideSetting[$rulesType.'_match_last']){
                $parkingtime=$exitRecords->entry_time-$exitRecords->exit_time;
                $modesArr=ParkingMode::where(['parking_id'=>$this->outsideParking->id,'status'=>'normal'])->cache('parking_mode_'.$this->outsideParking->id,3600*24)->select();
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
                $this->outSideException('外场无入场记录');
            }
        }
        if(time()<$records->entry_time){
            $this->outSideException('外场出场时间大于入场时间'.$records->id);
        }
        if($outsideSetting[$rulesType]){
            //查看15分钟内的计费情况
            $access=false;
            if($records->account_time && $records->account_time>time()-15*60 && $records->total_fee==($records->activities_fee+$records->pay_fee)){
                $access=true;
            }
            if(!$access){
                $ispay=$this->createOrder($this->outsideParking,$records);
                if(!$ispay){
                    $barrierService=$this->outsideBarrier->getBarrierService();
                    $barrierService->setParam([
                        'recordsType'=>ParkingRecords::RECORDSTYPE('自动识别'),
                        'plate'=>$this->outsidePlate,
                        'records'=>$records,
                        'rulesType'=>$rulesType,
                        'recordsPay'=>$this->recordsPay
                    ]);
                    $barrierService->screen('exit');
                    $barrierService->voice('exit');
                    ParkingScreen::sendRedMessage($this->insideBarrier,$this->insidePlate->plate_number.'等待外场支付');
                    ParkingScreen::sendRedMessage($this->outsideBarrier,$this->outsidePlate->plate_number.ParkingRules::RULESTYPE[$rulesType].'，入场时间'.$records->entry_time_txt.'，待支付'.($this->recordsPay->pay_price).'元');
                    return false;
                }
            }
            $callback=function () use ($records,$rules,$rulesType,$outsideSetting){
                $status=ParkingRecords::STATUS('免费出场');
                if($records->pay_fee>0){
                    $status=ParkingRecords::STATUS('缴费出场');
                }
                $records->exit_time=time();
                $records->exit_type=ParkingRecords::RECORDSTYPE('自动识别');
                $records->exit_barrier=$this->outsideBarrier->id;
                $records->rules_type=$rulesType;
                $records->rules_id=$rules?$rules->id:null;
                $records->cars_id=$this->outsidePlate->cars?$this->outsidePlate->cars->id:null;
                $records->status=$status;
                $records->save();
                //更新车位总数
                ParkingRecords::parkingSpaceEntry($this->outsideParking,'exit');
                //推动到交管平台
                if($outsideSetting->push_traffic && $records->rules_type==ParkingRules::RULESTYPE('临时车')){
                    (new ParkingTrafficRecords())->save([
                        'parking_id'=>$this->outsideParking->id,
                        'records_id'=>$records->id,
                        'traffic_type'=>'exit',
                        'status'=>0
                    ]);
                    Cache::set('traffic_event',1);
                }
            };
            $next=function () use ($records,$rulesType){
                //岗亭通知
                ParkingScreen::sendGreenMessage($this->outsideBarrier,$this->outsidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType].'，'.ParkingRecords::STATUS[$records->status]);
            };
            return true;
        }
        $this->outSideException('外场停车规则不支持，禁止出场');
    }

    private function insideEntry(&$callback,&$next):bool
    {
        $insideSetting=$this->insideParking->setting;
        if(!in_array($this->insidePlate->plate_type,$this->insideBarrier->plate_type)){
            $this->inSideException('内场通道禁止该类型车辆');
        }
        $rulesType=$this->getRulesType($this->insidePlate);
        $black=ParkingBlack::where(['plate_number'=>$this->insidePlate->plate_number,'parking_id'=>$this->insideParking->id])->find();
        if($black){
            $this->inSideException('内场黑名单禁止入场');
        }
        //同一个通道，15分钟内存在入场，则直接开闸
        $records=ParkingRecords::where(['parking_id'=>$this->insideParking->id,'plate_number'=>$this->insidePlate->plate_number])->whereIn('status',[0,1,6])->find();
        if($records && $records->entry_time+15*60>time()){
            $next=function () use ($rulesType){
                /* @var BarrierService $barrierService*/
                $barrierService=$this->insideBarrier->getBarrierService();
                $barrierService->setParam([
                    'recordsType'=>ParkingRecords::RECORDSTYPE('自动识别'),
                    'plate'=>$this->insidePlate,
                    'rulesType'=>$rulesType,
                ]);
                //发送屏幕与语音消息
                $barrierService->screen('entry');
                $barrierService->voice('entry');
                //岗亭通知
                ParkingScreen::sendBlackMessage($this->insideBarrier,$this->insidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType]);
            };
            return true;
        }
        if($records && $insideSetting->one_entry){
            $this->inSideException('内场一进一出异常导致限制入场');
        }
        if($insideSetting[$rulesType]){
            //判断车位数
            $parking_space_entry=ParkingRecords::parkingSpaceEntry($this->insideParking);
            if($insideSetting[$rulesType.'_space_full'] && $parking_space_entry >= $insideSetting->parking_space_total){
                $this->inSideException('内场车位已满');
            }
            //判断收费规则
            $rules=$this->getMatchRules($rulesType,$this->insidePlate);
            if($insideSetting->match_no_rule==1 && !$rules){
                $this->inSideException('内场无匹配规则，禁止入场');
            }
            if($rules && !$this->checkBarrierAllowRules($rules,$this->insideBarrier)){
                $ruletitle=$rules->title;
                if(!$ruletitle){
                    $ruletitle='临时卡';
                }
                $this->inSideException($ruletitle.'内场禁止通行');
            }
            $callback=function () use ($insideSetting,$parking_space_entry,$records,$rules,$rulesType){
                $insert=[
                    'parking_id'=>$this->insideParking->id,
                    'parking_title'=>$this->insideParking->title,
                    'rules_type'=>$rulesType,
                    'rules_id'=>$rules?$rules->id:null,
                    'plate_number'=>$this->insidePlate->plate_number,
                    'plate_type'=>$this->insidePlate->plate_type,
                    'special'=>$this->insidePlate->special,
                    'entry_type'=>ParkingRecords::RECORDSTYPE('自动识别'),
                    'entry_barrier'=>$this->insideBarrier->id,
                    'entry_time'=>time(),
                    'entry_photo'=>$this->photo,
                    'cars_id'=>$this->insidePlate->cars?$this->insidePlate->cars->id:null,
                    'status'=>ParkingRecords::STATUS('正在场内')
                ];
                $precords=new ParkingRecords();
                $precords->save($insert);
                //如果存在优惠券，则使用
                $couponlist=$this->getAllowCoupon($this->insideParking,$this->insidePlate);
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
                ParkingRecords::parkingSpaceEntry($this->insideParking,'entry');
                //推动到交管平台
                if($insideSetting->push_traffic && $precords->rules_type==ParkingRules::RULESTYPE('临时车')){
                    (new ParkingTrafficRecords())->save([
                        'parking_id'=>$this->insideParking->id,
                        'records_id'=>$precords->id,
                        'traffic_type'=>'entry',
                        'status'=>0
                    ]);
                    Cache::set('traffic_event',1);
                }
            };
            $next=function () use ($parking_space_entry,$insideSetting,$rulesType){
                /* @var BarrierService $barrierService*/
                $barrierService=$this->insideBarrier->getBarrierService();
                $barrierService->setParam([
                    'recordsType'=>ParkingRecords::RECORDSTYPE('自动识别'),
                    'plate'=>$this->insidePlate,
                    'rulesType'=>$rulesType,
                ]);
                //发送屏幕与语音消息
                $barrierService->screen('entry');
                $barrierService->voice('entry');
                //计算余位
                if($this->insideBarrier->show_last_space){
                    $last_space=$insideSetting->parking_space_total-$parking_space_entry-1;
                    $last_space=$last_space>0?$last_space:0;
                    $barrierService->showLastSpace($last_space);
                }
                //岗亭通知
                ParkingScreen::sendBlackMessage($this->insideBarrier,$this->insidePlate->plate_number.'开闸成功，'.ParkingRules::RULESTYPE[$rulesType]);
            };
            return true;
        }
        $this->outSideException('内场停车规则不支持，禁止入场');
    }

    public function createOrder(Parking $parking,ParkingRecords $records)
    {
        if($parking->pid){
            $plate=$this->insidePlate;
            $barrier=$this->insideBarrier;
        }else{
            $plate=$this->outsidePlate;
            $barrier=$this->outsideBarrier;
        }
        $rulesType=$this->getRulesType($plate);
        $rules=$this->getMatchRules($rulesType,$plate);
        $detail=[];
        $exit_time=time();
        $totalfee=$this->getTotalFee($parking,$records,$plate,$exit_time,$detail);
        foreach ($detail as &$item){
            $item['records_id']=$records->id;
            $item['parking_id']=$this->outsideParking->id;
        }
        $r=false;
        Db::startTrans();
        try{
            $records->account_time=time();
            $records->rules_id=$rules?$rules->id:null;
            $records->rules_type=$rulesType;
            $records->cars_id=$plate->cars?$plate->cars->id:null;
            ParkingRecordsDetail::where(['records_id'=>$records->id,'parking_id'=>$parking->id])->delete();
            ParkingRecordsDetail::insertAll($detail);
            //已经支付过的不能使用优惠券
            [$activities_fee,$activities_time,$coupon_type,$couponlist,$coupontitle]=$this->getActivitiesFee($parking,$plate,$records,$exit_time,$totalfee);
            //使用优惠券
            if($couponlist){
                ParkingMerchantCouponList::createRecordsCoupon($couponlist,$records);
            }
            $pay_price=formatNumber($totalfee-$records->pay_fee-$records->activities_fee-$activities_fee);
            if($pay_price<=0 || ($pay_price>0 && $this->checkIsPay($parking,$records,['total_fee'=>$totalfee,'activities_time'=>$activities_time,'activities_fee'=>$activities_fee,'pay_fee'=>$records->pay_fee,'pay_price'=>$pay_price]))){
                $r=true;
            }
            $records->exit_time=$exit_time;
            $records->exit_barrier=$barrier->id;
            $records->exit_photo=$this->photo;
            $records->status=ParkingRecords::STATUS('未缴费等待');
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

    private function checkIsPay(Parking $parking,ParkingRecords $records,array $feeArr)
    {
        if($parking->pid){
            $plate=$this->insidePlate;
            $barrier=$this->insideBarrier;
        }else{
            $plate=$this->outsidePlate;
            $barrier=$this->outsideBarrier;
        }
        $pay_price=$feeArr['pay_price'];
        $rulesType=$this->getRulesType($plate);
        if($rulesType==ParkingRules::RULESTYPE('储值车')){
            $balance=$plate->cars->balance;
            $remark=$records->plate_number.'（'.ParkingMode::PLATETYPE[$records->plate_type].'）停车缴费，在场时间：'.date('Y-m-d H:i',$records->entry_time).' 到 '.date('Y-m-d H:i',time());
            //储值车余额足够
            if($balance>=$pay_price){
                $payunion=PayUnion::stored(
                    $balance,
                    PayUnion::ORDER_TYPE('停车缴费'),
                    ['parking_id'=>$parking->id],
                    $records->plate_number.'停车缴费'
                );
                ParkingRecordsPay::createBarrierOrder($records,$payunion,$feeArr,$barrier->id);
                ParkingStoredLog::addRecordsLog($plate->cars,$pay_price,$remark);
                $records->pay_fee=$feeArr['pay_fee']+$pay_price;
                $records->status=ParkingRecords::STATUS('缴费未出场');
                $records->save();
                return true;
                //储值车余额不足
            }else{
                switch ($plate->cars->insufficient_balance){
                    case ParkingCars::INSUFFICIENT_BALANCE('禁止出场'):
                        if($parking->pid){
                            $this->inSideException('余额不足，禁止出场');
                        }else{
                            $this->outSideException('余额不足，禁止出场');
                        }
                    case ParkingCars::INSUFFICIENT_BALANCE('改为临停收费'):
                        break;
                    case ParkingCars::INSUFFICIENT_BALANCE('扣完余额，剩下的钱正常付费'):
                        if($balance<=0){
                            break;
                        }
                        $payunion=PayUnion::stored(
                            $balance,
                            PayUnion::ORDER_TYPE('停车缴费'),
                            ['parking_id'=>$parking->id],
                            $records->plate_number.'停车缴费'
                        );
                        ParkingRecordsPay::createBarrierOrder($records,$payunion,$feeArr,$barrier->id);
                        ParkingStoredLog::addRecordsLog($plate->cars,$balance,$remark);
                        $records->pay_fee=$feeArr['pay_fee']+$balance;
                        $records->save();
                        $feeArr['pay_price']=$pay_price-$balance;
                        break;
                }
            }
        }
        $this->recordsPay=ParkingRecordsPay::createBarrierOrder($records,null,$feeArr,$barrier->id);
        return false;
    }

    public function getRecordsPay()
    {
        return $this->recordsPay;
    }

    private function outSideException(string $message)
    {
        ParkingException::addException($this->outsidePlate,$this->outsideBarrier,$message,$this->photo);
        throw new \Exception($message);
    }

    private function inSideException(string $message)
    {
        ParkingException::addException($this->insidePlate,$this->insideBarrier,$message,$this->photo);
        throw new \Exception($message);
    }

    private function initPlates()
    {
        $this->plate_number=strtoupper(trim($this->plate_number));
        if(!is_car_license($this->plate_number)){
            throw new \Exception('车牌号格式错误');
        }
        $plates=ParkingPlate::where(function($query){
            $query->whereIn('parking_id',[$this->outsideParking->id,$this->insideParking->id]);
            $query->where('plate_number',$this->plate_number);
        })->select();
        $outsidePlate=null;
        $insidePlate=null;
        foreach ($plates as $plate){
            $cars=ParkingCars::find($plate->cars_id);
            if($cars && $cars->parking_id==$this->outsideParking->id){
                $plate->cars=$cars;
                $plate->plate_type=$this->plate_type;
                $outsidePlate=$plate;
            }
            if($cars && $cars->parking_id==$this->insideParking->id){
                $plate->cars=$cars;
                $plate->plate_type=$this->plate_type;
                $insidePlate=$plate;
            }
        }
        if(!$outsidePlate){
            $outsidePlate=new ParkingPlate();
            $outsidePlate->cars=null;
            $outsidePlate->parking_id=$this->outsideParking->id;
            $outsidePlate->plate_number=$this->plate_number;
            $outsidePlate->plate_type=$this->plate_type;
        }
        $outsidePlate->special=$this->getSpecial($outsidePlate);
        if(!$insidePlate){
            $insidePlate=new ParkingPlate();
            $insidePlate->cars=null;
            $insidePlate->parking_id=$this->insideParking->id;
            $insidePlate->plate_number=$this->plate_number;
            $insidePlate->plate_type=$this->plate_type;
        }
        $insidePlate->special=$this->getSpecial($insidePlate);
        $this->insidePlate=$insidePlate;
        $this->outsidePlate=$outsidePlate;
    }
}