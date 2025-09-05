<?php
declare (strict_types = 1);

namespace app\common\service;

use app\common\library\ParkingAccount;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingCarsOccupat;
use app\common\model\parking\ParkingCarsStop;
use app\common\model\parking\ParkingCharge;
use app\common\model\parking\ParkingChargeList;
use app\common\model\parking\ParkingInfield;
use app\common\model\parking\ParkingInfieldRecords;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingMonthlyRecharge;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRules;

trait Functions{
    private $recordsPay;
    private $chargeRules;

    public function getTotalFee(Parking $parking,ParkingRecords $records,ParkingPlate $plate,int $exit_time,array &$detail=[])
    {
        $rulesType=$this->getRulesType($plate);
        $rules=$this->getMatchRules($rulesType,$plate);
        $special=$this->getSpecial($plate);
        $totalfee=0;
        $detail=[];
        $entry_time=$records->entry_time;
        //周期收费的车辆，如果是在周期内，则免费放行
        $activeMode=false;
        $modesArr=ParkingMode::where(['parking_id'=>$parking->id,'status'=>'normal'])->cache('parking_mode_'.$parking->id,3600*24)->select();
        if($rules->rules_type==ParkingRules::RULESTYPE('临时车')){
            foreach ($modesArr as $mode){
                if($mode->id==$rules->mode_id && $mode->fee_setting=='loop'){
                    $activeMode=$mode;
                }
            }
        }else{
            foreach ($modesArr as $mode){
                foreach ($rules->mode as $ruleMode){
                    if($mode->id==$ruleMode['mode_id'] && $mode->fee_setting=='loop'){
                        $activeMode=$mode;
                    }
                }
            }
        }
        if($activeMode){
            $top_time=$activeMode->top_time*60;
            $payfee=ParkingRecords::where([
                'parking_id'=>$parking->id,
                'rules_id'=>$rules->id,
                'plate_number'=>$plate->plate_number
            ])
            ->where('entry_time','>',$exit_time-$top_time)
            ->sum('pay_fee');
            if($payfee>0){
                return 0;
            }
        }
        if($plate->cars && $plate->cars->rules_type==ParkingRules::RULESTYPE('月租车')){
            if($rulesType==ParkingRules::RULESTYPE('月租车')){
                //多位多车，如果且占有车位
                $plates_count=$plate->cars->plates_count;
                $occupat_number=$plate->cars->occupat_number;
                if($plates_count>$occupat_number){
                    $occupat=ParkingCarsOccupat::where(['parking_id'=>$plate->cars->parking_id,'cars_id'=>$plate->cars->id,'plate_number'=>$plate->plate_number])->find();
                    if($occupat && ($occupat->entry_time-$entry_time)>15*60){
                        $rules1=$this->getMatchRules(ParkingRules::RULESTYPE('临时车'),$plate);
                        $account=new ParkingAccount($parking);
                        $account->setRecords($records->plate_type,$special,$entry_time,$occupat->entry_time,$rules1)->fee();
                        $fee1=$account->getTotal();
                        if($fee1>0){
                            $totalfee+=$fee1;
                            $detail=array_merge($detail,$account->getDetail());
                        }
                        //修改入场时间为入车位的时间
                        $entry_time=$occupat->entry_time;
                    }
                }
                //在场一段时间才申请的月租
                if($entry_time<$plate->cars->starttime){
                    //入场前，月卡还未到期
                    $recharge=ParkingMonthlyRecharge::where(['parking_id'=>$plate->cars->parking_id,'cars_id'=>$plate->cars->id])->where('starttime','<',$entry_time)->where('endtime','>',$entry_time)->order('id desc')->find();
                    if($recharge){
                        $rules2=ParkingRules::find($recharge->rules_id);
                        $account=new ParkingAccount($parking);
                        $account->setRecords($records->plate_type,$special,$entry_time,$recharge->endtime,$rules2)->fee();
                        $fee2=$account->getTotal();
                        if($fee2>0){
                            $totalfee+=$fee2;
                            $detail=array_merge($detail,$account->getDetail());
                        }
                        //修改入场时间为上个月卡的结束时间
                        $entry_time=$recharge->endtime;
                    }
                    $rules3=$this->getMatchRules(ParkingRules::RULESTYPE('临时车'),$plate);
                    $account=new ParkingAccount($parking);
                    $account->setRecords($records->plate_type,$special,$entry_time,$plate->cars->starttime,$rules3)->fee();
                    $fee3=$account->getTotal();
                    if($fee3>0){
                        $totalfee+=$fee3;
                        $detail=array_merge($detail,$account->getDetail());
                    }
                    //修改入场时间为当前月卡的开始时间
                    $entry_time=$plate->cars->starttime;
                }
            }
            //月卡报停的情况出场
            if($rulesType==ParkingRules::RULESTYPE('临时车') && $plate->cars->status=='hidden'){
                //先计算报停之前的费用
                $stop=ParkingCarsStop::where(['parking_id'=>$parking->id,'cars_id'=>$plate->cars->id,'status'=>0])->find();
                //先入场后报停
                if($stop && $entry_time<$stop->begintime){
                    $rules_zq=ParkingRules::find($records->rules_id);
                    $account=new ParkingAccount($parking);
                    $account->setRecords($records->plate_type,$special,$entry_time,$stop->begintime,$rules_zq)->fee();
                    $fee_zq=$account->getTotal();
                    if($fee_zq>0){
                        $totalfee+=$fee_zq;
                        $detail=array_merge($detail,$account->getDetail());
                    }
                    //修改入场时间为报停后的时间
                    $entry_time=$stop->begintime;
                }
            }
            //如果是临时车考虑月卡已经到期的情况
            if($rulesType==ParkingRules::RULESTYPE('临时车') && $plate->cars->status=='normal' && $plate->cars->endtime<time()){
                $plates_count=$plate->cars->plates_count;
                $occupat_number=$plate->cars->occupat_number;
                //多位多车，且车停在车位里面
                if($plates_count>$occupat_number){
                    $occupat=ParkingCarsOccupat::where(['parking_id'=>$plate->cars->parking_id,'cars_id'=>$plate->cars->id,'plate_number'=>$plate->plate_number])->find();
                    //先处理不在车位里面的时间
                    if($occupat && ($occupat->entry_time-$entry_time)>15*60){
                        $rules4=$this->getMatchRules(ParkingRules::RULESTYPE('临时车'),$plate);
                        $account=new ParkingAccount($parking);
                        $account->setRecords($records->plate_type,$special,$entry_time,$occupat->entry_time,$rules4)->fee();
                        $fee4=$account->getTotal();
                        if($fee4>0){
                            $totalfee+=$fee4;
                            $detail=array_merge($detail,$account->getDetail());
                        }
                    }
                    //再考虑停在车位里面的时间
                    if($occupat){
                        $rules5=$this->getMatchRules(ParkingRules::RULESTYPE('月租车'),$plate);
                        $account=new ParkingAccount($parking);
                        $account->setRecords($records->plate_type,$special,$occupat->entry_time,$occupat->exit_time,$rules5)->fee();
                        $fee5=$account->getTotal();
                        if($fee5>0){
                            $totalfee+=$fee5;
                            $detail=array_merge($detail,$account->getDetail());
                        }
                        //修改入场时间为月卡结束时间
                        $entry_time=$occupat->exit_time;
                    }
                }
                if(($plate->cars->endtime-$entry_time)>15*60){
                    $rules6=$this->getMatchRules(ParkingRules::RULESTYPE('月租车'),$plate);
                    $account=new ParkingAccount($parking);
                    $account->setRecords($records->plate_type,$special,$entry_time,$plate->cars->endtime,$rules6)->fee();
                    $fee6=$account->getTotal();
                    if($fee6>0){
                        $totalfee+=$fee6;
                        $detail=array_merge($detail,$account->getDetail());
                    }
                    //修改入场时间为月卡结束时间
                    $entry_time=$plate->cars->endtime;
                }
            }
            //考虑月卡还没启用的情况
            if($rulesType==ParkingRules::RULESTYPE('临时车') && $plate->cars->status=='normal' && $plate->cars->starttime>time()){
                $plates_count=$plate->cars->plates_count;
                $occupat_number=$plate->cars->occupat_number;
                //多位多车，且车停在车位里面
                if($plates_count>$occupat_number){
                    $occupat=ParkingCarsOccupat::where(['parking_id'=>$plate->cars->parking_id,'cars_id'=>$plate->cars->id,'plate_number'=>$plate->plate_number])->find();
                    //先处理不在车位里面的时间
                    if($occupat && ($occupat->entry_time-$entry_time)>15*60){
                        $rules7=$this->getMatchRules(ParkingRules::RULESTYPE('临时车'),$plate);
                        $account=new ParkingAccount($parking);
                        $account->setRecords($records->plate_type,$special,$entry_time,$occupat->entry_time,$rules7)->fee();
                        $fee7=$account->getTotal();
                        if($fee7>0){
                            $totalfee+=$fee7;
                            $detail=array_merge($detail,$account->getDetail());
                        }
                    }
                    //再考虑停在车位里面的时间
                    if($occupat){
                        $rules8=$this->getMatchRules(ParkingRules::RULESTYPE('月租车'),$plate);
                        $account=new ParkingAccount($parking);
                        $account->setRecords($records->plate_type,$special,$occupat->entry_time,$occupat->exit_time,$rules8)->fee();
                        $fee8=$account->getTotal();
                        if($fee8>0){
                            $totalfee+=$fee8;
                            $detail=array_merge($detail,$account->getDetail());
                        }
                        //修改入场时间为月卡结束时间
                        $entry_time=$occupat->exit_time;
                    }
                }
                $recharge=ParkingMonthlyRecharge::where(['parking_id'=>$plate->cars->parking_id,'cars_id'=>$plate->cars->id])->where('starttime','<',$entry_time)->where('endtime','>',$entry_time)->order('id desc')->find();
                if($recharge){
                    $rules9=ParkingRules::find($recharge->rules_id);
                    $account=new ParkingAccount($parking);
                    $account->setRecords($records->plate_type,$special,$entry_time,$recharge->endtime,$rules9)->fee();
                    $fee9=$account->getTotal();
                    if($fee9>0){
                        $totalfee+=$fee9;
                        $detail=array_merge($detail,$account->getDetail());
                    }
                    //修改入场时间为上个月卡的结束时间
                    $entry_time=$recharge->endtime;
                }
            }
        }
        //内场自定义收费
        if($rulesType==ParkingRules::RULESTYPE('临时车') && $records->infield_diy){
            $infieldrecords=ParkingInfieldRecords::with(['infield'])->where(['parking_id'=>$records->parking_id,'records_id'=>$records->id])->find();
            //先看在外场停车费用
            $rules10=$this->getMatchRules(ParkingRules::RULESTYPE('临时车'),$plate);
            $account=new ParkingAccount($parking);
            $account->setRecords($records->plate_type,$special,$entry_time,$infieldrecords->entry_time,$rules10)->fee();
            $fee10=$account->getTotal();
            if($fee10>0){
                $totalfee+=$fee10;
                $detail=array_merge($detail,$account->getDetail());
            }
            //再看在内场停车费用
            $rules11=$this->getInfiedRules($infieldrecords->infield);
            $account=new ParkingAccount($parking);
            $account->setRecords($records->plate_type,$special,$infieldrecords->entry_time,$infieldrecords->exit_time??time(),$rules11)->fee();
            $fee11=$account->getTotal();
            if($fee11>0){
                $totalfee+=$fee11;
                $detail=array_merge($detail,$account->getDetail());
            }
            //修改入场时间为入车位的时间
            $entry_time=$infieldrecords->exit_time??time();
        }
        $account=new ParkingAccount($parking);
        $account->setRecords($records->plate_type,$special,$entry_time,$exit_time,$rules)->fee();
        $fee12=$account->getTotal();
        if($fee12>0){
            $totalfee+=$fee12;
            $detail=array_merge($detail,$account->getDetail());
        }
        return $totalfee;
    }

    private function getInfiedRules(ParkingInfield $infield)
    {
        $rules=new ParkingRules();
        $rules->parking_id=$infield->parking_id;
        $rules->rules_type='monthly';
        $rules->mode=$infield->mode;
        return $rules;
    }

    private function getActivitiesFee(Parking $parking,ParkingPlate $plate,ParkingRecords $records,$exit_time,$totalFee)
    {
        $activities_fee=0;
        $usetime=0;
        $end_time=$records->entry_time;
        $rlist=[];
        $coupon_type='';
        //如果已经使用过优惠券则不能再使用
        if($records->activities_fee>0){
            return [0,0,'',false,'无'];
        }
        foreach ($this->getAllowCoupon($parking,$plate) as $list){
            $detail=$list->coupon;
            switch ($detail['coupon_type']) {
                case ParkingMerchantCoupon::COUPON_TYPE('免费券'):
                    if($coupon_type){
                        continue 2;
                    }
                    $usetime=$exit_time-$records->entry_time;
                    return [$totalFee,$usetime,ParkingMerchantCoupon::COUPON_TYPE('免费券'), [$list],$detail['title']];
                case ParkingMerchantCoupon::COUPON_TYPE('折扣券'):
                    if($coupon_type){
                        continue 2;
                    }
                    if($detail['discount_time'] && $exit_time-$records->entry_time>$detail['discount_time']*60){
                        $rulesType=$this->getRulesType($plate);
                        $rules = $this->getMatchRules($rulesType,$plate);
                        $account = new ParkingAccount($parking);
                        $special = $this->getSpecial($plate);
                        $account->setRecords($records->plate_type, $special, $records->entry_time, $records->entry_time+$detail['discount_time']*60, $rules)->fee();
                        $fee = $account->getTotal();
                        $activities_fee = round($fee * $detail['discount'] * 0.1, 2);
                        $usetime=$detail['discount_time']*60;
                    }else{
                        $activities_fee = round($totalFee * $detail['discount'] * 0.1, 2);
                        $usetime=$exit_time-$records->entry_time;
                    }
                    return [$activities_fee,$usetime,ParkingMerchantCoupon::COUPON_TYPE('折扣券'), [$list],$detail['title']];
                case ParkingMerchantCoupon::COUPON_TYPE('时效券'):
                    if($coupon_type && $coupon_type!=ParkingMerchantCoupon::COUPON_TYPE('时效券')){
                        continue 2;
                    }
                    if($coupon_type && !$detail['multiple_use']){
                        continue 2;
                    }
                    $coupon_type=ParkingMerchantCoupon::COUPON_TYPE('时效券');
                    if(empty($rlist)){
                        if(!$list->starttime){
                            $list->starttime=$records->entry_time;
                        }
                        $starttime=$list->starttime;
                    }else{
                        $starttime=$rlist[0]->starttime;
                        foreach ($rlist as $r){
                            $starttime+=$r->coupon['period']*60*60;
                        }
                        $list->starttime=$starttime;
                    }
                    //有效期结束时间
                    $endtime = $starttime + $detail['period'] * 60 * 60;
                    if($endtime<$records->entry_time){
                        continue 2;
                    }
                    $rlist[]=$list;
                    //未到结束时间
                    if ($endtime > $exit_time) {
                        break 2;
                    }
                case ParkingMerchantCoupon::COUPON_TYPE('代金券'):
                    $merchant=ParkingMerchant::find($list->merch_id);
                    if($coupon_type && $coupon_type!=ParkingMerchantCoupon::COUPON_TYPE('代金券')){
                        continue 2;
                    }
                    if($coupon_type && !$detail['multiple_use']){
                        continue 2;
                    }
                    $coupon_type=ParkingMerchantCoupon::COUPON_TYPE('代金券');
                    $activities_fee+=$detail['cash'];
                    if($merchant->price){
                        $usetime+=round($activities_fee/($merchant->price/60/60));
                    }
                    $rlist[]=$list;
                    if($totalFee<=$activities_fee){
                        break 2;
                    }
                case ParkingMerchantCoupon::COUPON_TYPE('时长券'):
                    if($coupon_type && $coupon_type!=ParkingMerchantCoupon::COUPON_TYPE('时长券')){
                        continue 2;
                    }
                    if($coupon_type && !$detail['multiple_use']){
                        continue 2;
                    }
                    $coupon_type=ParkingMerchantCoupon::COUPON_TYPE('时长券');
                    $end_time+=$detail['time']*60;
                    $rlist[]=$list;
                    if($end_time>$exit_time){
                        $end_time=$exit_time;
                        break 2;
                    }
                case ParkingMerchantCoupon::COUPON_TYPE('时段券'):
                    if($coupon_type){
                        continue 2;
                    }
                    $timespan_time=$detail['timespan_time']*60;
                    $timespan=$detail['timespan'];
                    if(!$timespan_time){
                        $timespan_time=24*3600*365;
                    }
                    $freeperiods=$this->getFreePeriods($end_time,$exit_time,$timespan,$timespan_time);
                    if(count($freeperiods)>0){
                        $rulesType=$this->getRulesType($plate);
                        $rules = $this->getMatchRules($rulesType,$plate);
                        $account = new ParkingAccount($parking);
                        $special = $this->getSpecial($plate);
                        foreach ($freeperiods as $freeperiod){
                            $account->setRecords($records->plate_type, $special, $freeperiod['start'], $freeperiod['end'], $rules)->fee();
                            $activities_fee+= $account->getTotal();
                            $usetime+=$freeperiod['duration'];
                        }
                    }
                    //存在折扣
                    if(isset($detail['timespan_discount']) && $detail['timespan_discount']){
                        $activities_fee=$activities_fee*$detail['timespan_discount']*0.1;
                    }
                    $activities_fee=$activities_fee<$totalFee?$activities_fee:$totalFee;
                    return [$activities_fee,$usetime,ParkingMerchantCoupon::COUPON_TYPE('时段券'), [$list],$detail['title']];
            }
        }
        if(count($rlist)===0){
            return [0,0,'',false,'无'];
        }
        if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时效券')){
            $count=count($rlist);
            $lastcoupon=$rlist[$count-1];
            $endtime = $lastcoupon->starttime + $lastcoupon->coupon['period'] * 60 * 60;
            //未到结束时间
            if ($endtime > $exit_time) {
                $activities_fee=$totalFee;
                $usetime=$exit_time-$records->entry_time;
            }else{
                $rulesType=$this->getRulesType($plate);
                $rules = $this->getMatchRules($rulesType,$plate);
                $account = new ParkingAccount($parking);
                $special = $this->getSpecial($plate);
                $account->setRecords($records->plate_type, $special, $records->entry_time, $endtime, $rules)->fee();
                $activities_fee = $account->getTotal();
                $usetime=$endtime-$records->entry_time;
            }
            if($count==1){
                $title=$rlist[0]->coupon->title;
            }else{
                $period=0;
                for($i=0;$i<$count;$i++){
                    $period+=$rlist[$i]->coupon['period'];
                }
                $title=$count.'张时效券合计'.$period.'小时';
            }
            return [$activities_fee,$usetime,ParkingMerchantCoupon::COUPON_TYPE('时效券'),$rlist,$title];
        }
        if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('代金券')){
            $activities_fee=($activities_fee>$totalFee)?$totalFee:$activities_fee;
            $count=count($rlist);
            if($count==1){
                $title=$rlist[0]->coupon->title;
            }else{
                $cash=0;
                for($i=0;$i<$count;$i++){
                    $cash+=$rlist[$i]->coupon['cash'];
                }
                $title=$count.'张代金券合计'.$cash.'元';
            }
            return [$activities_fee,$usetime,ParkingMerchantCoupon::COUPON_TYPE('代金券'),$rlist,$title];
        }
        if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时长券')){
            $rulesType=$this->getRulesType($plate);
            $rules=$this->getMatchRules($rulesType,$plate);
            $special=$this->getSpecial($plate);
            //优惠时长不够
            if($exit_time>$end_time){
                $account=new ParkingAccount($parking);
                $account->unAllowFreeTime();
                $account->setRecords($records->plate_type,$special,$end_time,$exit_time,$rules)->fee();
                $newTotalFee=$account->getTotal();
                $activities_fee=($totalFee-$newTotalFee>0)?$totalFee-$newTotalFee:0;
            }else{
                $activities_fee=$totalFee;
            }
            $count=count($rlist);
            if($count==1){
                $title=$rlist[0]->coupon->title;
            }else{
                $time=0;
                for($i=0;$i<$count;$i++){
                    $time+=$rlist[$i]->coupon['time'];
                }
                $title=$count.'张时长券合计'.$time.'分钟';
            }
            $usetime=$end_time-$records->entry_time;
            return [$activities_fee,$usetime,ParkingMerchantCoupon::COUPON_TYPE('时长券'),$rlist,$title];
        }
        return [0,0,'',false,'无'];
    }

    private function getFreePeriods($entry_time, $exit_time, $timespan, $toptime) {
        $free_periods = [];
        $total_free_time = 0;

        // 遍历从入场到出场的每一天
        for ($current_day = strtotime(date('Y-m-d', $entry_time)); $current_day <= strtotime(date('Y-m-d', $exit_time)); $current_day = strtotime('+1 day', $current_day)) {
            foreach ($timespan as $period) {
                $start_str = date('Y-m-d ', $current_day). $period['starttime'];
                $end_str = date('Y-m-d ', $current_day). $period['endtime'];

                // 处理跨天的优惠时间段
                if (strtotime($end_str) < strtotime($start_str)) {
                    $end_str = date('Y-m-d ', strtotime('+1 day', $current_day)). $period['endtime'];
                }

                $start_time = strtotime($start_str);
                $end_time = strtotime($end_str);

                // 计算实际的优惠时间段
                $actual_start = max($entry_time, $start_time);
                $actual_end = min($exit_time, $end_time);

                if ($actual_end > $actual_start) {
                    $duration = $actual_end - $actual_start;
                    if ($total_free_time + $duration > $toptime) {
                        $duration = $toptime - $total_free_time;
                        $actual_end = $actual_start + $duration;
                    }
                    if ($duration > 0) {
                        $free_periods[] = [
                            'start' => $actual_start,
                            'end' => $actual_end,
                            'duration' => $duration
                        ];
                        $total_free_time += $duration;
                    }
                    if ($total_free_time >= $toptime) {
                        break 2;
                    }
                }
            }
        }
        return $free_periods;
    }

    private function checkBarrierAllowRules(ParkingRules $rules,ParkingBarrier $barrier):bool
    {
        if($rules->rules_type==ParkingRules::RULESTYPE('临时车') && in_array('provisional',$barrier->rules_type)){
            return true;
        }
        if($rules->rules_type!=ParkingRules::RULESTYPE('临时车') && in_array('unprovisional',$barrier->rules_type)){
            if(in_array($rules->id,$barrier->rules_id)){
                return true;
            }
        }
        return false;
    }

    private function getFreeParkingTime($begintime, $endtime, $timespan)
    {
        $freeTimes = [];
        $beginDateTime = new DateTime($begintime);
        $endDateTime = new DateTime($endtime);
        // 遍历每一天
        $currentDay = clone $beginDateTime;
        while ($currentDay <= $endDateTime) {
            foreach ($timespan as $span) {
                $start = $currentDay->format('Y-m-d') . ' ' . $span['start'];
                $end = $currentDay->format('Y-m-d') . ' ' . $span['end'];
                $spanStart = new DateTime($start);
                $spanEnd = new DateTime($end);
                if ($spanStart >= $beginDateTime && $spanEnd <= $endDateTime) {
                    $freeTimes[] = ['start' => $spanStart->format('Y-m-d H:i:s'), 'end' => $spanEnd->format('Y-m-d H:i:s')];
                } elseif ($spanStart < $beginDateTime && $spanEnd <= $endDateTime) {
                    $freeTimes[] = ['start' => $beginDateTime->format('Y-m-d H:i:s'), 'end' => $spanEnd->format('Y-m-d H:i:s')];
                } elseif ($spanStart >= $beginDateTime && $spanEnd > $endDateTime) {
                    $freeTimes[] = ['start' => $spanStart->format('Y-m-d H:i:s'), 'end' => $endDateTime->format('Y-m-d H:i:s')];
                } elseif ($spanStart < $beginDateTime && $spanEnd > $endDateTime) {
                    $freeTimes[] = ['start' => $beginDateTime->format('Y-m-d H:i:s'), 'end' => $endDateTime->format('Y-m-d H:i:s')];
                }
            }
            $currentDay->modify('+1 day');
        }
        return $freeTimes;
    }
    private function getAllowCoupon(Parking $parking,ParkingPlate $plate)
    {
        $couponlist=ParkingMerchantCouponList::where(['list.parking_id'=>$parking->id,'list.plate_number'=>$plate->plate_number])
        ->whereIn('list.status',[0,2])
        ->alias('list')
        ->field('list.*')
        ->rightJoin('parking_merchant_coupon coupon','coupon.id = list.coupon_id')
        ->order('coupon.weigh desc,list.status desc')
        ->select();
        $coupon=ParkingMerchantCoupon::where(['parking_id'=>$parking->id,'status'=>'normal'])->cache('parking_coupon_'.$parking->id,3600*24)->select();
        $r=[];
        $merch_id=false;
        foreach ($couponlist as $list){
            if($merch_id && $list->merch_id!=$merch_id){
                continue;
            }
            foreach ($coupon as $pon){
                if($list->coupon_id==$pon->id && $list->expiretime>=time()){
                    $merch_id=$list->merch_id;
                    $list->coupon=$pon;
                    $r[]=$list;
                }
            }
        }
        return $r;
    }

    private function getRulesType(ParkingPlate $plate)
    {
        if($plate->rulesType){
            return $plate->rulesType;
        }
        //是否删除
        if(!$plate->cars){
            $plate->rulesType=ParkingRules::RULESTYPE('临时车');
            return $plate->rulesType;
        }
        //状态是否正常
        if($plate->cars->status!='normal'){
            $plate->rulesType=ParkingRules::RULESTYPE('临时车');
            return $plate->rulesType;
        }
        //不在期限范围内
        if($plate->cars->starttime>time()){
            $plate->rulesType=ParkingRules::RULESTYPE('临时车');
            return $plate->rulesType;
        }
        //已经过期
        if($plate->cars->endtime<time()){
            if($plate->cars->rules_type!=ParkingRules::RULESTYPE('月租车')){
                $plate->rulesType=ParkingRules::RULESTYPE('临时车');
                return $plate->rulesType;
            }
            if($plate->cars->rules_type==ParkingRules::RULESTYPE('月租车')){
                $rules=$plate->cars->rules;
                if($plate->cars->endtime+$rules->expire_day*3600*24<time()){
                    $plate->rulesType=ParkingRules::RULESTYPE('临时车');
                    return $plate->rulesType;
                }
            }
        }
        $plate->rulesType=$plate->cars->rules_type;
        return $plate->rulesType;
    }

    private function getSpecial(ParkingPlate $plate)
    {
        $plate_number=$plate->plate_number;
        if(str_ends_with($plate_number,'应急') || str_starts_with($plate_number,'应急')){
            return ParkingMode::SPECIAL('应急车');
        }
        if(str_ends_with($plate_number,'警') || str_starts_with($plate_number,'警')){
            return ParkingMode::SPECIAL('警车');
        }
        //判断军车车牌
        $pattern='/^[QVKHBSLJNGCEZ]{1}[ABCDEFGHJKLMNPQRSTUVWXY]{1}[0-9A-Z]{5}$/';
        if(preg_match($pattern, $plate_number)){
            return ParkingMode::SPECIAL('军车');
        }
        //判断武警车车牌
        $pattern='/^WJ[A-Z]{1}[京津沪渝冀豫云辽黑湘皖鲁新苏浙赣鄂桂甘晋蒙陕吉闽贵粤青藏川宁琼]{0,1}[0-9A-Z]{5}$/';
        if(preg_match($pattern, $plate_number)){
            return ParkingMode::SPECIAL('武警车');
        }
        return null;
    }

    private function getMatchRules(string $rulesType,ParkingPlate $plate)
    {
        //匹配临时车
        if($rulesType==ParkingRules::RULESTYPE('临时车')){
            //充电车自定义收费规则
            if($this->chargeRules){
                return $this->chargeRules;
            }
            $rules_id=false;
            if($this->chargeRules===null){
                $charge=ParkingCharge::where(['parking_id'=>$plate->parking_id])->cache('parking_charge_'.$plate->parking_id,3600*24)->find();
                if($charge && $charge->use_diy_rules){
                    $chargelist=ParkingChargeList::with(['records'])->where(['parking_id'=>$plate->parking_id,'plate_number'=>$plate->plate_number])->where('rules_id','>',0)->order('id desc')->find();
                    if($chargelist && ($chargelist->records->status==ParkingRecords::STATUS('正在场内') || $chargelist->records->status==ParkingRecords::STATUS('未缴费等待'))){
                        $rules_id=$charge->rules_id;
                    }
                }
            }
            $rules=ParkingRules::with(['provisionalmode'])
                ->order('weigh desc')
                ->where(['parking_id'=>$plate->parking_id,'status'=>'normal'])
                ->cache('parking_rules_'.$plate->parking_id,3600*24)
                ->select();
            foreach ($rules as $rule){
                if($rules_id && $rules_id==$rule->id){
                    $this->chargeRules=$rule;
                    return $rule;
                }
            }
            foreach ($rules as $rule){
                if($rule->rules_type==$rulesType){
                    return $rule;
                }
            }
            return null;
        }else{
            $rules=$plate->cars->rules;
            return $rules;
        }
        return null;
    }
}