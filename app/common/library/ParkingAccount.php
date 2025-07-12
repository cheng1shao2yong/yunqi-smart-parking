<?php

declare(strict_types=1);

namespace app\common\library;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingCharge;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingRules;

class ParkingAccount{
    private $parking;
    private $rulesArr;
    private $modesArr;
    private $chargeArr;
    private string $plate_type;
    private mixed $special;
    private int $entry_time;
    private int $exit_time;
    private mixed $rules;
    private $total_fee;
    private $parking_detail;
    private $allow_free_time=true;

    public function __construct(Parking $parking)
    {
        $this->rulesArr=ParkingRules::with(['provisionalmode'])
            ->order('weigh desc')
            ->where(['parking_id'=>$parking->id,'status'=>'normal'])
            ->cache('parking_rules_'.$parking->id,3600*24)
            ->select();
        $this->modesArr=ParkingMode::where(['parking_id'=>$parking->id,'status'=>'normal'])
            ->cache('parking_mode_'.$parking->id,3600*24)
            ->select();
        $this->chargeArr=ParkingCharge::where(['parking_id'=>$parking->id])
            ->cache('parking_charge_'.$parking->id,3600*24)
            ->find();
        $this->parking=$parking;
    }

    public function setRecords(string $plate_type,mixed $special=null,int $entry_time,int $exit_time,mixed $rules)
    {
        $this->plate_type=$plate_type;
        $this->special=$special;
        $this->entry_time=$entry_time;
        $this->exit_time=$exit_time;
        $this->rules=$rules;
        $this->total_fee=0;
        $this->parking_detail=[];
        return $this;
    }

    //获取免费时长
    public function unAllowFreeTime()
    {
        $this->allow_free_time=false;
    }

    public function fee()
    {
        //特殊车辆免费
        if($this->special && in_array($this->special,$this->parking->setting->special_free)){
            $this->total_fee=0;
            $this->parking_detail=array(
                ['start_time'=>$this->entry_time,'end_time'=>$this->exit_time,'fee'=>0,'mode'=>'特殊车辆免费']
            );
            return $this;
        }
        $startTime=$this->entry_time;
        $modeTime=strtotime(date('Y-m-d',$this->entry_time));
        //首先转成mode的关联数组
        $arr=[];
        while(true){
            $mode=$this->getMatchMode($modeTime);
            $modeTime=strtotime('tomorrow',$modeTime);
            $isEnd=false;
            if($modeTime>=$this->exit_time){
                $endTime=$this->exit_time;
                $isEnd=true;
            }else{
                $endTime=$modeTime;
            }
            $count=count($arr);
            if($count>0 && $arr[$count-1]['mode']->id==$mode->id){
                $arr[$count-1]['end_time']=$endTime;
                $startTime=$endTime;
            }else{
                $arr[]=[
                    'mode'=>$mode,
                    'start_time'=>$startTime,
                    'end_time'=>$endTime,
                ];
                $startTime=$modeTime;
            }
            if($isEnd){
                break;
            }
        }
        $rangeTime=0;
        foreach ($arr as $key=>$item){
            $mode=$item['mode'];
            $startTime=$item['start_time'];
            $endTime=$item['end_time'];
            //找不到匹配的mode
            if($mode->id==-1){
                $this->parking_detail=array(
                    ['start_time'=>$startTime,'end_time'=>$endTime,'fee'=>0,'mode'=>$mode->title]
                );
                continue;
            }
            //免费时长内
            if(
                $key===0 &&
                $this->allow_free_time &&
                $mode->free_time &&
                ($this->exit_time-$this->entry_time)<$mode->free_time*60
            ){
                $this->total_fee=0;
                $this->parking_detail=array(
                    ['start_time'=>$this->entry_time,'end_time'=>$this->exit_time,'fee'=>0,'mode'=>$mode->title]
                );
                return $this;
            }
            if($rangeTime>0){
                $startTime=$startTime+$rangeTime;
                $rangeTime=0;
            }
            if($startTime>=$this->exit_time){
                break;
            }
            //存在每日封顶
            $have_day_top=false;
            if($mode->day_top_fee && ($periodTime=floor(($endTime - $startTime) / (24 * 3600)))>0) {
                $fee = 0;
                for ($day = 0; $day < $periodTime; $day++) {
                    $fee+=$mode->day_top_fee;
                }
                $this->parking_detail[]=['start_time' => $startTime, 'end_time' => $startTime + $periodTime * 24 * 3600, 'fee' => $fee, 'mode' => $mode->title];
                $this->total_fee+=$fee;
                $startTime=$startTime + $periodTime * 24 * 3600;
                $rangeTime=0;
                $have_day_top=true;
            }
            switch ($mode->fee_setting){
                case 'free':
                    $itme_detail=$this->accountingFree($startTime,$endTime,$mode);
                    break;
                case 'normal':
                    $itme_detail=$this->accountingNormal($have_day_top?1:$key,$startTime,$endTime,$mode,$rangeTime);
                    break;
                case 'period':
                    $itme_detail=$this->accountingPeriod($have_day_top?1:$key,$startTime,$endTime,$mode,$rangeTime);
                    break;
                case 'loop':
                    $itme_detail=$this->accountingLoop($have_day_top?1:$key,$startTime,$endTime,$mode,$rangeTime);
                    break;
                case 'step':
                    $itme_detail=$this->accountingStep($startTime,$endTime,$mode,$rangeTime);
                    break;
            }
            //如果有每日封顶，则取最大值
            if($mode->day_top_fee) {
                $total_fee=0;
                foreach ($itme_detail as $value){
                    $total_fee+=$value['fee'];
                }
                if($total_fee>$mode->day_top_fee){
                    $rangeTime=0;
                    $itme_detail=array(
                        ['start_time' => $startTime, 'end_time' =>$endTime, 'fee' => $mode->day_top_fee, 'mode' => $mode->title]
                    );
                }
            }
            foreach ($itme_detail as $value){
                $this->parking_detail[]=$value;
                $this->total_fee+=$value['fee'];
            }
        }
        return $this;
    }

    public function getTotal()
    {
        return formatNumber($this->total_fee);
    }

    public function getDetail()
    {
        return $this->parking_detail;
    }

    public static function formatDetail(array $detail)
    {
        foreach ($detail as $key=>$item){
            $detail[$key]['start_time']=date('Y-m-d H:i:s',$item['start_time']);
            $detail[$key]['end_time']=date('Y-m-d H:i:s',$item['end_time']);
        }
        return $detail;
    }

    //获取当天的收费模式
    private function getMatchMode(int $startTime)
    {
        $nomatch=new ParkingMode();
        $nomatch->id=-1;
        $nomatch->title='未找到匹配规则';
        if(!$this->rules){
            return $nomatch;
        }
        //匹配临时车
        if($this->rules->rules_type==ParkingRules::RULESTYPE('临时车')){
            foreach ($this->rulesArr as $rule){
                if($rule->rules_type!=$this->rules->rules_type){
                    continue;
                }
                if($this->matchMode($rule->provisionalmode,$startTime)){
                    return $rule->provisionalmode;
                }
            }
            return $nomatch;
        }else{
            $mode=$this->rules->mode;
            foreach ($mode as $m1){
                foreach ($this->modesArr as $m2){
                    if($m1['mode_id']==$m2->id && $this->matchMode($m2,$startTime)){
                        return $m2;
                    }
                }
            }
        }
        return $nomatch;
    }

    private function matchMode(ParkingMode $mode,int $startTime)
    {
        //判断车牌类型是否匹配
        $plate_type=$mode->plate_type;
        if(!in_array($this->plate_type,$plate_type)){
            return false;
        }
        //判断特殊车辆【军、警、消防车等】
        $special=$mode->special;
        if($special && !in_array($this->special,$special)){
            return false;
        }
        $time_setting=$mode->time_setting;
        if($time_setting!='all'){
            if($time_setting=='date'){
                if(!in_array(date('Y-m-d',$startTime),explode(',',$mode->time_setting_rules))){
                    return false;
                }
            }
            if($time_setting=='period'){
                $period=explode(',',$mode->time_setting_rules);
                $start=strtotime($period[0]);
                $end=strtotime($period[1]);
                if($startTime<$start || $startTime>$end){
                    return false;
                }
            }
            if($time_setting=='week'){
                $week=explode(',',$mode->time_setting_rules);
                if(!in_array(date('w',$startTime),$week)){
                    return false;
                }
            }
            if($time_setting=='month'){
                $day=explode(',',$mode->time_setting_rules);
                if(!in_array(date('d',$startTime),$day)){
                    return false;
                }
            }
        }
        return true;
    }

    private function accountingFree($startTime,$endTime,ParkingMode $mode)
    {
        $r[]=['start_time'=>$startTime,'end_time'=>$endTime,'fee'=>0,'mode'=>$mode->title];
        return $r;
    }

    private function accountingNormal($key,$startTime,$endTime,ParkingMode $mode,&$rangeTime)
    {
        //存在起步时长
        $r=[];
        if($key===0 && $mode->start_fee){
            [$instart,$start_fee]=self::calculateStartFee($mode->start_fee);
            if($instart){
                $r[]=['start_time'=>$this->entry_time,'end_time'=>$this->exit_time,'fee'=>$start_fee,'mode'=>$mode->title];
                return $r;
            }else{
                $start_second=$mode->start_fee[count($mode->start_fee)-1]['time']*60;
                $r[]=['start_time'=>$this->entry_time,'end_time'=>$this->entry_time+$start_second,'fee'=>$start_fee,'mode'=>$mode->title];
                $startTime=$startTime+$start_second;
            }
        }
        if($rangeTime){
            $startTime=$startTime+$rangeTime;
        }
        $parking_time=$endTime-$startTime;
        $number=intval(ceil($parking_time/($mode->add_time*60)));
        $rangeTime=$number*$mode->add_time*60-$parking_time;
        $fee=$number*$mode->add_fee;
        $endTime=$endTime+$rangeTime;
        if($endTime>$this->exit_time){
            $endTime=$this->exit_time;
        }
        $r[]=['start_time'=>$startTime,'end_time'=>$endTime,'fee'=>$fee,'mode'=>$mode->title];
        return $r;
    }

    private function accountingPeriod($key,$startTime,$endTime,ParkingMode $mode,&$rangeTime)
    {
        //存在起步时长
        $start_fee=0;
        $r=[];
        if($key===0 && $mode->start_fee){
            [$instart,$start_fee]=self::calculateStartFee($mode->start_fee);
            if($instart){
                $r[]=['start_time'=>$this->entry_time,'end_time'=>$this->exit_time,'fee'=>$start_fee,'mode'=>$mode->title];
                return $r;
            }else{
                $start_second=$mode->start_fee[count($mode->start_fee)-1]['time']*60;
                $r[]=['start_time'=>$this->entry_time,'end_time'=>$this->entry_time+$start_second,'fee'=>$start_fee,'mode'=>$mode->title];
                $startTime=$startTime+$start_second;
            }
        }
        $kk=0;
        if($rangeTime){
            $startTime=$startTime+$rangeTime;
        }
        $periods=$this->getTimePeriods($mode->period_fee,$startTime,$endTime);
        $rangeTime=0;
        foreach ($periods as $period){
            if($rangeTime){
                $period['timeStart']=$period['timeStart']+$rangeTime;
            }
            $period_parking_time=$period['timeEnd']-$period['timeStart'];
            if($period_parking_time<=0){
                break;
            }
            if($rangeTime>=$period_parking_time){
                $rangeTime=$rangeTime-$period_parking_time;
                $r[]=['start_time'=>$period['timeStart'],'end_time'=>$period['timeEnd'],'fee'=>$period['add_fee'],'mode'=>$mode->title];
                $kk++;
                continue;
            }
            $fee=0;
            $addTime=$period['timeStart'];
            while($addTime<$period['timeEnd']){
                $addTime+=$period['add_time']*60;
                $fee+=$period['add_fee'];
                if($kk===0 && $start_fee>0 && $fee+$start_fee>=$period['top_fee']){
                    $r=[];
                    $fee=$period['top_fee'];
                    $rangeTime=0;
                    break;
                }
                if($fee>=$period['top_fee']){
                    $fee=$period['top_fee'];
                    $rangeTime=0;
                    break;
                }
            }
            if($addTime>$period['timeEnd']){
                $rangeTime=$addTime-$period['timeEnd'];
            }
            if($addTime>$this->exit_time){
                $addTime=$this->exit_time;
            }
            $r[]=['start_time'=>$period['timeStart'],'end_time'=>$addTime>$period['timeEnd']?$addTime:$period['timeEnd'],'fee'=>$fee,'mode'=>$mode->title];
            $kk++;
        }
        return $r;
    }

    private function accountingLoop($key,$startTime,$endTime,ParkingMode $mode,&$rangeTime)
    {
        //存在起步时长
        $start_fee=0;
        $start_second=0;
        $r=[];
        if($key===0 && $mode->start_fee){
            [$instart,$start_fee]=self::calculateStartFee($mode->start_fee);
            if($instart){
                $r[]=['start_time'=>$this->entry_time,'end_time'=>$this->exit_time,'fee'=>$start_fee,'mode'=>$mode->title];
                return $r;
            }
            $start_second=$mode->start_fee[count($mode->start_fee)-1]['time']*60;
        }
        $number=intval(ceil(($endTime-$startTime)/($mode->top_time*60)));
        for($i=0;$i<$number;$i++){
            $startLoopTime=$startTime+$i*$mode->top_time*60;
            if($rangeTime){
                $startLoopTime=$startLoopTime+$rangeTime;
            }
            $endLoopTime=$startLoopTime+$mode->top_time*60;
            if($endLoopTime>$endTime){
                $endLoopTime=$endTime;
            }
            $addLoopTime=$startLoopTime;
            $start_second+=$startLoopTime;
            $fee=0;
            while ($addLoopTime<$endLoopTime){
                $addLoopTime+=$mode->add_time*60;
                if($i===0 && $addLoopTime<=$start_second){
                    $fee=$start_fee;
                }else{
                    $fee+=$mode->add_fee;
                }
                if($fee>=$mode->top_fee){
                    $fee=$mode->top_fee;
                    break;
                }
            }
            if($addLoopTime>$endLoopTime){
                $rangeTime=$addLoopTime-$endLoopTime;
            }
            if($addLoopTime>$this->exit_time){
                $addLoopTime=$this->exit_time;
            }
            $r[]=['start_time'=>$startLoopTime,'end_time'=>$addLoopTime>$endLoopTime?$addLoopTime:$endLoopTime,'fee'=>$fee,'mode'=>$mode->title];
        }
        return $r;
    }

    private function accountingStep($startTime,$endTime,ParkingMode $mode,&$rangeTime)
    {
        $r=[];
        if($rangeTime){
            $startTime=$startTime+$rangeTime;
        }
        $top_time=$mode->step_fee[count($mode->step_fee)-1]['time'];
        $top_fee=$mode->step_fee[count($mode->step_fee)-1]['fee'];
        $number=intval(ceil(($endTime-$startTime)/($top_time*60)));
        for($i=0;$i<$number;$i++){
            $startStepTime=$startTime+$i*$top_time*60;
            $endStepTime=$startStepTime+$top_time*60;
            //如果是最后一次循环
            if($i==$number-1){
                if($endStepTime>$endTime){
                    $endStepTime=$endTime;
                }
                $fee=0;
                $parking_time=$endStepTime-$startStepTime;
                foreach ($mode->step_fee as $step){
                    if($step['time']*60>=$parking_time){
                        $fee=$step['fee'];
                        break;
                    }
                }
                $r[]=['start_time'=>$startStepTime,'end_time'=>$endStepTime,'fee'=>$fee,'mode'=>$mode->title];
            }else{
                $r[]=['start_time'=>$startStepTime,'end_time'=>$endStepTime,'fee'=>$top_fee,'mode'=>$mode->title];
            }
        }
        return $r;
    }

    private function getTimePeriods($periods, $startTime, $endTime) {
        $result = [];
        $startTime=date('Y-m-d H:i:s',(int)$startTime);
        $endTime=date('Y-m-d H:i:s',(int)$endTime);
        $start = new \DateTime($startTime);
        $end = new \DateTime($endTime);
        while($start < $end) {
            foreach($periods as $period) {
                list($periodStart, $periodEnd) = explode('-', $period['period']);
                $periodStartDate = clone $start;
                $periodStartDate->setTime(...$this->explode(':', $periodStart));
                $periodEndDate = clone $start;
                $periodEndDate->setTime(...$this->explode(':', $periodEnd));
                if($periodEndDate < $periodStartDate) {
                    if($start < $periodEndDate) {
                        $periodStartDate->modify('-1 day');
                    } else {
                        $periodEndDate->modify('+1 day');
                    }
                }
                if($start >= $periodStartDate && $start < $periodEndDate) {
                    if($end > $periodEndDate) {
                        $result[] = [...$period, 'timeStart' => $start->getTimestamp(), 'timeEnd' => $periodEndDate->getTimestamp()];
                        $start = $periodEndDate;
                    } else {
                        $result[] = [...$period, 'timeStart' => $start->getTimestamp(), 'timeEnd' => $end->getTimestamp()];
                        $start = $end;
                    }
                    break;
                }
            }
        }
        return $result;
    }


    private function explode(string $split,string $time)
    {
        $arr=explode($split, $time);
        return [(int)$arr[0],(int)$arr[1]];
    }

    private function calculateStartFee(array $start_fee)
    {
        $parking_time=$this->exit_time-$this->entry_time;
        $r1=$start_fee[count($start_fee)-1]['fee'];
        for($i=count($start_fee);$i>0;$i--){
            if($start_fee[$i-1]['time']*60>=$parking_time){
                $r1=$start_fee[$i-1]['fee'];
            }
        }
        $r2=false;
        foreach ($start_fee as $value){
            if($parking_time<=$value['time']*60){
                $r2=true;
            }
        }
        return [$r2,$r1];
    }

}