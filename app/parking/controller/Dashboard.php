<?php
/**
 * ----------------------------------------------------------------------------
 * 行到水穷处，坐看云起时
 * 开发软件，找贵阳云起信息科技，官网地址:https://www.56q7.com/
 * ----------------------------------------------------------------------------
 * Author: 老成
 * email：85556713@qq.com
 */
declare (strict_types = 1);

namespace app\parking\controller;

use app\common\controller\ParkingBase;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingQrcode;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingSetting;
use app\common\model\PayUnion;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("dashboard")]
class Dashboard extends ParkingBase
{
    #[Route('GET','index')]
    public function index()
    {
        if($this->request->isAjax()){
            $panel=[
                ParkingSetting::where('parking_id',$this->parking->id)->value('parking_space_total'),
                ParkingRecords::parkingSpaceEntry($this->parking),
                ParkingRecords::where(['parking_id'=>$this->parking->id,'rules_type'=>'provisional'])->whereIn('status',[0,1,6])->count(),
                ParkingCars::where(['parking_id'=>$this->parking->id,'rules_type'=>'monthly'])->count()
            ];
            $todaytime=$this->getRangeTime('string','today');
            $prefix=getDbPrefix();
            $today=PayUnion::where(['parking_id'=>$this->parking->id,'pay_status'=>1])
                ->whereBetween('pay_time',$todaytime)
                ->whereRaw("id not in (select pay_id from {$prefix}parking_records_filter where pay_id is not null and parking_id={$this->parking->id})")
                ->sum('pay_price');
            $yestodaytime=$this->getRangeTime('string','yestoday');
            $yestoday=PayUnion::where(['parking_id'=>$this->parking->id,'pay_status'=>1])
                ->whereBetween('pay_time',$yestodaytime)
                ->whereRaw("id not in (select pay_id from {$prefix}parking_records_filter where pay_id is not null and parking_id={$this->parking->id})")
                ->sum('pay_price');
            $order=[
                'today'=>$today,
                'yestoday'=>$yestoday,
                'percentage'=>$yestoday>$today?round($today/$yestoday*100):100
            ];
            $this->success('',compact('panel','order'));
        }
        $this->assign('parking',$this->parking);
        $this->assign('ruletype',array_merge(['all'=>'全部'],ParkingRules::RULESTYPE));
        return $this->fetch();
    }

    #[Route('GET','merch')]
    public function merch()
    {
        $type=$this->request->get('type');
        $prefix=getDbPrefix();
        if($type=='before'){
            $list=Db::name('parking_merchant')
                ->alias('merch')
                ->leftJoin("(select merch_id,count(1) as count from {$prefix}parking_merchant_coupon_list where status=0) list0","list0.merch_id=merch.id")
                ->leftJoin("(select merch_id,count(1) as count from {$prefix}parking_merchant_coupon_list where status=2) list2","list2.merch_id=merch.id")
                ->field('merch.id,merch.merch_name,merch.balance,list0.count as count0,list2.count as count2')
                ->where(['merch.parking_id'=>$this->parking->id,'merch.settle_type'=>$type])
                ->order('merch.balance desc')
                ->limit(10)
                ->select();
        }
        if($type=='after'){
            $list=Db::name('parking_merchant')
                ->alias('merch')
                ->leftJoin("(select merch_id,count(1) as count from {$prefix}parking_merchant_coupon_list where status=0) list0","list0.merch_id=merch.id")
                ->leftJoin("(select merch_id,count(1) as count from {$prefix}parking_merchant_coupon_list where status=2) list2","list2.merch_id=merch.id")
                ->field('merch.id,merch.merch_name,merch.balance,list0.count as count0,list2.count as count2')
                ->where(['merch.parking_id'=>$this->parking->id,'merch.settle_type'=>$type])
                ->order('merch.balance asc')
                ->limit(10)
                ->select();
        }
        $table=[];
        foreach ($list as $key=>$value){
            $table[]=[
                'sort'=>$key+1,'merch_name'=>$value['merch_name'],'balance'=>$value['balance'],'count0'=>$value['count0']??0,'count2'=>$value['count2']??0
            ];
        }
        $this->success('',$table);
    }

    #[Route('GET','bar')]
    public function bar()
    {
        $rules_type=$this->request->get('rules_type');
        $where='';
        if($rules_type!='all'){
            $where=" and rules_type='{$rules_type}'";
        }
        $endtime=strtotime(date('Y-m-d 23:59:59'));
        $starttime=$endtime-24*3600*10+1;
        $prefix=getDbPrefix();
        $sql="
                SELECT entry_time,COUNT(1) as count FROM
                (
                SELECT id,LEFT(FROM_UNIXTIME(entry_time),10) as entry_time FROM {$prefix}parking_records where parking_id={$this->parking->id} {$where} and entry_time BETWEEN {$starttime} AND {$endtime}
                )t1 GROUP BY entry_time
            ";
        $entry=Db::query($sql);
        $sql="
                SELECT exit_time,COUNT(1) as count FROM
                (
                SELECT id,LEFT(FROM_UNIXTIME(exit_time),10) as exit_time FROM {$prefix}parking_records where parking_id={$this->parking->id} {$where} and exit_time BETWEEN {$starttime} AND {$endtime}
                )t1 GROUP BY exit_time
            ";
        $exit=Db::query($sql);
        $bar=[
            'date'=>[],
            'name'=>['入场','离场'],
            'data'=>[[],[]]
        ];
        $j=0;
        for($i=$starttime;$i<=$endtime;$i+=24*3600){
            $bar['date'][]=date('d号',$i);
            $bar['data'][0][$j]=0;
            $bar['data'][1][$j]=0;
            foreach ($entry as $item){
                if($item['entry_time']==date('Y-m-d',$i)){
                    $bar['data'][0][$j]=$item['count'];
                }
            }
            foreach ($exit as $item){
                if($item['exit_time']==date('Y-m-d',$i)){
                    $bar['data'][1][$j]=$item['count'];
                }
            }
            $j++;
        }
        $this->success('',$bar);
    }

    #[Route('GET','month')]
    public function month()
    {
        $type=$this->request->get('type');
        $list=ParkingCars::with(['plates'=>function($query){$query->limit(1);}])->where(['parking_id'=>$this->parking->id,'rules_type'=>'monthly'])->order('endtime '.$type)->limit(10)->select();
        $table=[];
        foreach ($list as $key=>$value){
            if($value['plates_count']>1){
                $plates=$value['plates'][0];
                $plates=$plates['plate_number'].'等'.$value['plates_count'].'辆车..';
            }else{
                $plates=$value['plates'][0];
                $plates=$plates['plate_number'];
            }
            $day=floor(($value->endtime-time())/(24*3600));
            $table[]=[
                'sort'=>$key+1,'plates'=>$plates,'contact'=>$value->contact,'mobile'=>$value->mobile,'day'=>$day
            ];
        }
        $this->success('',$table);
    }

    #[Route('GET','pie')]
    public function pie()
    {
        $type=$this->request->get('type');
        $timebetween=$this->getRangeTime('string',$type);
        $pie=PayUnion::where(function ($query) use ($timebetween){
            $prefix=getDbPrefix();
            $query->where('pay_status',1);
            $query->where('parking_id',$this->parking->id);
            $query->whereRaw("id not in (select pay_id from {$prefix}parking_records_filter where pay_id is not null and parking_id={$this->parking->id})");
            if($timebetween){
                $query->whereBetween('pay_time',$timebetween);
            }
        })->group('order_type,pay_type')
        ->field('pay_type,order_type,sum(pay_price) as value')
        ->select();
        foreach ($pie as &$item){
            $item->name=$item->order_type_text.'-'.$item->pay_type_text;
            $item->value=formatNumber($item->value);
        }
        $this->success('',$pie);
    }

    #[Route('GET','line')]
    public function line()
    {
        $type=$this->request->get('type');
        $now=time();
        $line=[
            'date'=>[],
            'data'=>[]
        ];
        $timestr='';
        if($type=='month'){
            $y=intval(date('Y',$now));
            $m=intval(date('m',$now));
            $m++;
            if($m==13){
                $m=1;
                $y++;
            }
            if($m<10){
                $m='0'.$m;
            }
            $start=($y-1).'-'.$m.'-01 00:00:00';
            $end=date('Y-m-d H:i:s',$now);
            for($i=0;$i<12;$i++){
                $line['date'][]=date('Y/m',strtotime($start)+$i*31*24*3600);
            }
            $timestr='left(pay_time,7)';
        }
        if($type=='day'){
            $start=date('Y-m-d 00:00:00',$now-29*24*3600);
            $end=date('Y-m-d H:i:s',$now);
            for($i=0;$i<30;$i++){
                $line['date'][]=date('m/d',strtotime($start)+$i*24*3600);
            }
            $timestr='right(left(pay_time,10),5)';
        }
        if($type=='hour'){
            $start=date('Y-m-d 00:00:00',$now);
            $end=date('Y-m-d H:i:s',$now);
            for($i=0;$i<24;$i++){
                $line['date'][]=date('H',strtotime($start)+$i*3600).'时';
            }
            $timestr='left(right(pay_time,8),2)';
        }
        $prefix=getDbPrefix();
        $sql="
            SELECT pay_time,SUM(pay_price) as pay_price FROM
            (
            SELECT {$timestr} as pay_time,pay_price FROM {$prefix}pay_union 
            where 
            pay_status=1 
            and (pay_time BETWEEN '{$start}' and '{$end}')
            and parking_id={$this->parking->id}
            and order_type='parking'
            and pay_type!='stored'
            and id not in (select pay_id from {$prefix}parking_records_filter where pay_id is not null and parking_id={$this->parking->id})
            )t1 GROUP BY pay_time
        ";
        $list=Db::query($sql);
        for ($i=0;$i<count($line['date']);$i++){
            $data=0;
            foreach ($list as $item){
                $sts=str_replace('/','-',$line['date'][$i]);
                $sts=str_replace('时','',$sts);
                if($item['pay_time']==$sts){
                    $data=$item['pay_price'];
                    break;
                }
            }
            $line['data'][]=$data;
        }
        $this->success('',$line);
    }

    private function getRangeTime($str,$time)
    {
        $now=time();
        $start_str='';
        $end_str='';
        $start_int=0;
        $end_int=0;
        if($time=='all'){
            return false;
        }
        if($time=='today'){
            $start_str=date('Y-m-d 00:00:00',$now);
            $end_str=date('Y-m-d H:i:s',$now);
            $start_int=strtotime($start_str);
            $end_int=$now;
        }
        if($time=='yestoday'){
            $start_str=date('Y-m-d 00:00:00',$now-24*3600);
            $end_str=date('Y-m-d 23:59:59',$now-24*3600);
            $start_int=strtotime($start_str);
            $end_int=strtotime($end_str);
        }
        if($time=='week'){
            //$start_str,本周一的0点
            $start_str=date('Y-m-d 00:00:00',$now-((date('w',$now)-1)*24*3600));
            $end_str=date('Y-m-d H:i:s',$now);
            $start_int=strtotime($start_str);
            $end_int=$now;
        }
        if($time=='month'){
            //$start_str,本月1号的0点
            $start_str=date('Y-m-01 00:00:00',$now);
            $end_str=date('Y-m-d H:i:s',$now);
            $start_int=strtotime($start_str);
            $end_int=$now;
        }
        if($str=='int'){
            return [$start_int,$end_int];
        }
        if($str=='string'){
            return [$start_str,$end_str];
        }
    }

    #[Route('GET','get-qrcode')]
    public function getQrcode()
    {
        $parkingqrcode=new ParkingQrcode();
        $parkingqrcode->name='admin';
        $parkingqrcode->background='';
        $parkingqrcode->parking_id=$this->parking->id;
        $img=ParkingQrcode::getQrcode($parkingqrcode);
        Header("Content-type: image/png");
        echo $img;
        exit;
    }
}
