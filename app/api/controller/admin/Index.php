<?php
declare (strict_types = 1);

namespace app\api\controller\admin;

use app\common\controller\Api;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingAdmin;
use app\common\model\parking\ParkingBarrier;
use app\common\model\property\PropertyAdmin;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Cache;
use think\facade\Db;

#[Group("admin/index")]
class Index extends Api
{
    public function _initialize()
    {
        parent::_initialize();
        if(
            $this->auth->id!=18
            && $this->auth->id!=19
            && $this->auth->id!=71
            && $this->auth->id!=4571
            && $this->auth->id!=1203
        ){
            $this->error('没有权限');
        }
    }

    #[Get('info')]
    public function info()
    {
        $user=$this->auth->userinfo();
        $this->success('',compact('user'));
    }

    #[Get('parking')]
    public function parking()
    {
        $page=$this->request->get('page/d');
        $parking=[];
        $list=Parking::where(function ($query){
            $title=$this->request->get('title');
            if($title){
                $query->where('title','like',"%$title%");
            }
            if($this->auth->id!=18 && $this->auth->id!=19){
                $query->where('id','<>',13);
            }
        })
            ->order('id desc')
            ->limit(($page-1)*10,10)
            ->select()
            ->each(function ($row) use (&$parking){
                $parking[]=$row->id;
            });
        $triggerlist=[];
        $paylist=[];
        if(!empty($parking)){
            $prefix=getDbPrefix();
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
        }
        $barrierlist=ParkingBarrier::whereIn('parking_id',$parking)->field('id,parking_id,serialno')->select()->toArray();
        $now=time();
        foreach ($list as $key=>$value){
            $barrier=array_values(array_filter($barrierlist,function ($row) use ($value){
                return $row['parking_id']==$value->id;
            }));
            $online=[0,0];
            foreach ($barrier as $v){
                $updatetime=Cache::get('barrier-online-'.$v['serialno']);
                if($now-$updatetime>60){
                    $online[0]++;
                }else{
                    $online[1]++;
                }
            }
            $list[$key]['barrier_online']=$online;
            $trigger=array_values(array_filter($triggerlist,function ($row) use ($value){
                return $row['parking_id']==$value->id;
            }));
            if(!empty($trigger)){
                $trigger=$trigger[0];
                $trigger_time=$now-$trigger['createtime'];
                $list[$key]['trigger_time']=$trigger_time;
            }
            $pay=array_values(array_filter($paylist,function ($row) use ($value){
                return $row['parking_id']==$value->id;
            }));
            if(!empty($pay)){
                $pay=$pay[0];
                $pay_time=$now-$pay['createtime'];
                $list[$key]['pay_time']=$pay_time;
            }
        }
        $this->success('',$list);
    }

    #[Post('login')]
    public function login()
    {
        $parking_id=$this->request->post('parking_id/d');
        $parkingadmin=ParkingAdmin::withJoin(['admin'],'inner')->where(['parking_id'=>$parking_id,'role'=>'admin','rules'=>'*'])->find();
        if($parkingadmin){
            $this->auth->setParkingAdmin($parkingadmin->admin,$parking_id,'*');
        }else{
            $parking=Parking::find($parking_id);
            $propertyadmin=PropertyAdmin::withJoin(['admin'],'inner')->where(['property_id'=>$parking->property_id,'role'=>'admin','rules'=>'*'])->find();
            $this->auth->setParkingAdmin($propertyadmin->admin,$parking_id,'*');
        }
        $this->success('登录成功');
    }
}
