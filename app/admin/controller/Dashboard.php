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

namespace app\admin\controller;

use app\admin\command\Queue as QueueCommand;
use app\common\model\manage\Parking;
use app\common\model\MpSubscribe;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRules;
use app\common\model\User;
use app\common\controller\Backend;
use think\annotation\route\Route;
use think\facade\Db;

/**
 * 控制台
 */
class Dashboard extends Backend
{

    public function _initialize()
    {
        parent::_initialize();
    }
    /**
     * 查看
     */
    #[Route('GET','dashboard/index')]
    public function index()
    {
        if($this->request->isAjax()){
            //模拟数据面板
            $panel=[
                User::count(),
                MpSubscribe::count(),
                Parking::count(),
                ParkingBarrier::count(),
            ];
            $prefix=getDbPrefix();
            $endtime=strtotime(date('Y-m-d H:00:00',time()));
            $startime=strtotime(date('Y-m-d H:00:00',$endtime-24*3600));
            $sql="
                SELECT createtime,COUNT(1) as count FROM
                (
                SELECT id,FROM_UNIXTIME(createtime,'%H') as createtime FROM {$prefix}parking_trigger where createtime BETWEEN {$startime} AND {$endtime}
                )t1 GROUP BY createtime
            ";
            $list=Db::query($sql);
            //模拟折线图
            $line=[
                'date'=>[],
                'data'=>[]
            ];
            while($startime<$endtime){
                foreach ($list as $item){
                    if($item['createtime']==date('H',$startime)){
                        $line['date'][]=$item['createtime'].'时';
                        $line['data'][]=$item['count'];
                        break;
                    }
                }
                $startime+=3600;
            }
            $this->success('',compact('panel','line'));
        }
        $this->assign('ruletype',array_merge(['all'=>'全部'],ParkingRules::RULESTYPE));
        return $this->fetch();
    }

    #[Route('GET','dashboard/bar')]
    public function bar()
    {
        $starttime=strtotime($this->request->get('starttime'));
        $endtime=strtotime($this->request->get('endtime'));
        $rules_type=$this->request->get('rules_type');
        $where='';
        if($rules_type!='all'){
            $where="and rules_type='{$rules_type}'";
        }
        $prefix=getDbPrefix();
        $sql="
            SELECT t1.id,t1.title,t2.count FROM {$prefix}parking t1,
            (
            SELECT parking_id,count(1) as count FROM {$prefix}parking_records where entry_time BETWEEN {$starttime} and {$endtime} {$where} GROUP BY parking_id
            )t2 where t1.id=t2.parking_id ORDER BY t2.count desc limit 10 
        ";
        $list=Db::query($sql);
        $bar=[
            'title'=>[],
            'name'=>['入场','离场'],
            'data'=>[[],[]]
        ];
        $parking_id=[];
        $parking_data=[];
        foreach ($list as $item){
            $title=$item['title'];
            if(mb_strlen($title)>8){
                $title=mb_substr($title,0,8).'...';
            }
            $bar['title'][]=$title;
            $parking_data[$item['id']]['entry']=$item['count'];
            $parking_data[$item['id']]['exit']=0;
            $parking_id[]=$item['id'];
        }
        if(count($parking_id)>0){
            $sql="
                SELECT parking_id,count(1) as count FROM {$prefix}parking_records where exit_time BETWEEN {$starttime} and {$endtime} and parking_id in (".implode(',',$parking_id).") {$where} GROUP BY parking_id
            ";
            $list=Db::query($sql);
            foreach ($list as $item){
                $parking_id=$item['parking_id'];
                $parking_data[$parking_id]['exit']=$item['count'];
            }
        }
        foreach ($parking_data as $data){
            $bar['data'][0][]=$data['entry'];
            $bar['data'][1][]=$data['exit'];
        }
        $this->success('',$bar);
    }

    #[Route('GET','dashboard/line')]
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
            and order_type='parking'
            and pay_type!='stored'
            and pay_type!='underline'
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

    #[Route('GET','dashboard/table')]
    public function table()
    {
        $where="";
        $type=$this->request->get('type');
        $sort=$this->request->get('sort');
        $page=$this->request->get('page');
        if($type=='today'){
            $starttime=date('Y-m-d 00:00:00');
            $endtime=date('Y-m-d 23:59:59');
            $where="and (pay_time>'{$starttime}' and pay_time<'{$endtime}')";
        }elseif ($type=='week'){
            $now=time();
            $day_of_week = date('N', $now);
            $monday_timestamp = $now - ($day_of_week - 1) * 24 * 60 * 60;
            $starttime = date("Y-m-d 00:00:00", $monday_timestamp);
            $endtime=date('Y-m-d 23:59:59');
            $where="and (pay_time>'{$starttime}' and pay_time<'{$endtime}')";
        }elseif ($type=='month'){
            $starttime=date('Y-m-01 00:00:00');
            $endtime=date('Y-m-d 23:59:59');
            $where="and (pay_time>'{$starttime}' and pay_time<'{$endtime}')";
        }elseif ($type=='lastmonth'){
            $now=time();
            $first_day_of_last_month = strtotime('first day of last month', $now);
            $starttime = date("Y-m-d 00:00:00", $first_day_of_last_month);
            $endtime=date('Y-m-d H:i:s',strtotime(date('Y-m-01 00:00:00'))-1);
            $where="and (pay_time>'{$starttime}' and pay_time<'{$endtime}')";
        }
        $prefix=getDbPrefix();
        $offset=($page-1)*10;
        $sql="
                SELECT t1.id,t1.title,t2.pay_price,t2.handling_fees FROM {$prefix}parking t1 left join
                (
                SELECT parking_id,SUM(pay_price) as pay_price,SUM(handling_fees/100) as handling_fees FROM {$prefix}pay_union where pay_status=1 and pay_type!='underline' and pay_type!='stored' {$where} GROUP BY parking_id
                )t2
                on t1.id=t2.parking_id where t1.id<>13 ORDER BY t2.pay_price {$sort} limit {$offset},10 
            ";
        $list=Db::query($sql);
        $parking=array_column($list,'id');
        $trigger_sql='';
        $pay_sql='';
        foreach ($parking as $parking_id){
            $trigger_sql.="(SELECT parking_id, createtime FROM {$prefix}parking_trigger WHERE parking_id = {$parking_id} ORDER BY id DESC LIMIT 1)";
            $trigger_sql.="UNION ALL";
            $pay_sql.="(SELECT parking_id, createtime FROM {$prefix}pay_union WHERE parking_id = {$parking_id} AND pay_type NOT IN ('underline', 'stored') ORDER BY id DESC LIMIT 1)";
            $pay_sql.="UNION ALL";
        }
        $trigger_sql=substr($trigger_sql,0,-9);
        $pay_sql=substr($pay_sql,0,-9);
        $triggerlist=Db::query($trigger_sql);
        $paylist=Db::query($pay_sql);
        //模拟表格
        $table=[];
        $now=time();
        foreach ($list as $key=>$item){
            $item['trigger_time']=null;
            $item['pay_time']=null;
            $trigger=array_values(array_filter($triggerlist,function ($row) use ($item){
                return $row['parking_id']==$item['id'];
            }));
            if(!empty($trigger)){
                $trigger=$trigger[0];
                $trigger_time=$now-$trigger['createtime'];
                $item['trigger_time']=$trigger_time;
            }
            $lastpay=array_values(array_filter($paylist,function ($row) use ($item){
                return $row['parking_id']==$item['id'];
            }));
            if(!empty($lastpay)){
                $lastpay=$lastpay[0];
                $pay_time=$now-$lastpay['createtime'];
                $item['pay_time']=$pay_time;
            }
            $table[]=[
                'sort'=>$key+1,'trigger_time'=>$item['trigger_time'],'pay_time'=>$item['pay_time'],'title'=>$item['title'],'pay_price'=>formatNumber($item['pay_price']),'handling_fees'=>formatNumber($item['handling_fees'])
            ];
        }
        $this->success('',$table);
    }
}
