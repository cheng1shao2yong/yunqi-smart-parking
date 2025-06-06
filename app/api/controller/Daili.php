<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\DailiLog;
use app\common\model\DailiParking;
use app\common\model\Third;
use think\annotation\route\Get;
use think\annotation\route\Group;
use app\common\model\Daili as DailiModel;
use think\annotation\route\Post;
use think\facade\Db;

#[Group("daili")]
class Daili extends Api
{
    #[Get('login')]
    public function login()
    {
        $third=Third::where(['user_id'=>$this->auth->id,'platform'=>'mpapp'])->find();
        $daili=DailiModel::where(['third_id'=>$third->id])->find();
        $userinfo=$this->auth->userinfo();
        if($daili){
            $this->auth->setDaili($daili);
        }
        $is_admin=0;
        $daili_list=[];
        if($this->auth->id==18 || $this->auth->id==4571){
            $is_admin=1;
            $daili_list=DailiModel::where('id','>',0)->select();
        }
        $this->success('',[
            'userinfo'=>$userinfo,
            'is_daili'=>$daili??null,
            'is_admin'=>$is_admin,
            'daili_list'=>$daili_list
        ]);
    }

    #[Get('change')]
    public function change()
    {
        if($this->auth->id==18 || $this->auth->id==4571){
            $id=$this->request->get('id/d');
            $daili=DailiModel::find($id);
            $this->auth->setDaili($daili);
            $this->success();
        }
        $this->error();
    }

    #[Get('info')]
    public function info()
    {
        $is_daili=$this->auth->getDaili();
        if(!$is_daili){
            $this->error();
        }
        $daili=DailiModel::find($is_daili['id']);
        $heji=DailiLog::where(['daili_id'=>$daili->id,'change_type'=>'add'])->sum('change');
        $tixian=DailiLog::where(['daili_id'=>$daili->id,'change_type'=>'minus'])->sum('change');
        $r=[
            'parking'=>$daili->parking,
            'balance'=>$daili->balance,
            'heji'=>round($heji,2),
            'tixian'=>round($tixian,2)
        ];
        $this->success('',$r);
    }

    #[Get('park')]
    public function park()
    {
        $is_daili=$this->auth->getDaili();
        if(!$is_daili){
            $this->error();
        }
        $page=$this->request->get('page/d');
        $list=DailiParking::with(['parking'])->where(function ($query) use ($is_daili){
            $query->where('daili_id',$is_daili['id']);
        })
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select();
        if(!empty($list)){
            $ids=array_column($list->toArray(),'parking_id');
            $ids=implode(',',$ids);
            $time=date('Y-m-d 00:00:00',time());
            $prefix=getDbPrefix();
            $sql="select parking_id,sum(pay_price) as pay_price from {$prefix}pay_union where parking_id in ({$ids}) and pay_time<'{$time}' and pay_type<>'underline' and pay_status=1 and refund_price is null and pay_type<>'stored' and id not in (select pay_id from {$prefix}parking_records_filter WHERE pay_id is not null and parking_id IN ({$ids})) group by parking_id";
            $pay=Db::query($sql);
            //将每个停车场的总收入插入到$list与parking_id对应的数组中
            foreach ($list as $k=>$v){
                foreach ($pay as $key=>$value){
                    if($v['parking_id']==$value['parking_id']){
                        $list[$k]['pay_price']=$value['pay_price'];
                    }
                }
            }
        }
        $this->success('',$list);
    }

    #[Post('tixian')]
    public function tixian()
    {
        $is_daili=$this->auth->getDaili();
        if(!$is_daili){
            $this->error();
        }
        $money=$this->request->post('money/f');
        if($money<=0){
            $this->error('提现金额必须大于0');
        }
        $daili=DailiModel::find($is_daili['id']);
        $result=[
            'daili_id'=>$daili->id,
            'change_type'=>'minus',
            'change'=>$money,
            'before'=>$daili->balance,
            'after'=>$daili->balance-$money,
            'remark'=>0,
            'createtime'=>time()
        ];
        (new DailiLog())->save($result);
        $daili->balance=$daili->balance-$money;
        $daili->save();
        $this->success();
    }

    #[Get('log')]
    public function log()
    {
        $page=$this->request->get('page/d');
        $type=$this->request->get('type/d');
        $parking_id=$this->request->get('parking_id/d');
        $is_daili=$this->auth->getDaili();
        if(!$is_daili){
            $this->error();
        }
        if($type){
            $list=DailiLog::where(['change_type'=>'minus','daili_id'=>$is_daili['id']])
                ->order('id desc')
                ->limit(($page-1)*10,10)
                ->select();
        }else{
            $where=['change_type'=>'add','daili_id'=>$is_daili['id']];
            if($parking_id){
                $where['remark']=$parking_id;
            }
            $list=DailiLog::with(['parking'])
                ->where($where)
                ->order('id desc')
                ->limit(($page-1)*10,10)
                ->select();
        }
        $this->success('',$list);
    }
}
