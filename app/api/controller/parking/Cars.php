<?php
declare (strict_types = 1);

namespace app\api\controller\parking;

use app\common\model\parking\ParkingBlack;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingCarsApply;
use app\common\model\parking\ParkingCarsLogs;
use app\common\model\parking\ParkingCarsOccupat;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingMonthlyRecharge;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingStoredLog;
use app\common\model\PayUnion;
use app\common\model\Third;
use app\common\service\msg\WechatMsg;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Db;

#[Group("parking/cars")]
class Cars extends Base
{

    #[Get('list')]
    public function list()
    {
        $page=$this->request->get('page/d',1);
        $count=$this->request->get('count/d',0);
        $type=$this->request->get('type');
        $recyclebin=$this->request->get('recyclebin');
        $now=time();
        if($recyclebin){
            $list=ParkingCars::with(['plates'=>function ($query) {
                $query->limit(1);
            }])->onlyTrashed()->where(function ($query) use ($type,$recyclebin){
                $plate_number=$this->request->get('plate_number');
                $mobile=$this->request->get('mobile');
                $contact=$this->request->get('contact');
                $query->where('parking_id','=',$this->parking_id);
                if($mobile){
                    $query->where('mobile','like','%'.$mobile.'%');
                }
                if($contact){
                    $query->where('contact','like','%'.$contact.'%');
                }
                if($plate_number){
                    $cars_id=ParkingPlate::where('plate_number','like','%'.$plate_number.'%')->where('parking_id',$this->parking_id)->column('cars_id');
                    $query->whereIn('id',$cars_id);
                }
            })
            ->order('deletetime desc')
            ->limit(($page-1)*10,10)
            ->select()
            ->each(function ($res) use ($now){
                if($res['plates_count']>1){
                    $plates=$res['plates'][0];
                    $plates['plate_number']=$plates['plate_number'].'等'.$res['plates_count'].'辆车..';
                    $res['plates']=[$plates];
                }
            });
        }else{
            $where=function ($query) use ($type){
                $plate_number=$this->request->get('plate_number');
                $mobile=$this->request->get('mobile');
                $contact=$this->request->get('contact');
                $query->where('parking_id','=',$this->parking_id);
                $query->where('rules_type','=',$type);
                if($mobile){
                    $query->where('mobile','like','%'.$mobile.'%');
                }
                if($contact){
                    $query->where('contact','like','%'.$contact.'%');
                }
                if($plate_number){
                    $cars_id=ParkingPlate::where('plate_number','like','%'.$plate_number.'%')->where('parking_id',$this->parking_id)->column('cars_id');
                    $query->whereIn('id',$cars_id);
                }
            };
            $list=ParkingCars::with(['plates'=>function($query){
                $query->limit(1);
            }])
            ->where($where)
            ->order('endtime asc,id desc')
            ->limit(($page-1)*10,10)
            ->select()
            ->each(function ($res) use ($now){
                $res['isexpire']=0;
                if($now>($res['endtime']-7*24*3600)){
                    $res['isexpire']=2;
                }
                if($now>$res['endtime']){
                    $res['isexpire']=1;
                }
                if($res['plates_count']>1){
                    $plates=$res['plates'][0];
                    $plates['plate_number']=$plates['plate_number'].'等'.$res['plates_count'].'辆车..';
                    $res['plates']=[$plates];
                }
            });
            if($count){
                $count=ParkingCars::where($where)->count();
                $this->success('',compact('count','list'));
            }
        }
        $this->success('',$list);
    }

    #[Get('blacklist')]
    public function blacklist()
    {
        $page=$this->request->get('page/d');
        $list=ParkingBlack::with(['admin'])->where(function ($query){
            $plate_number=$this->request->get('plate_number');
            $query->where('parking_id','=',$this->parking_id);
            if($plate_number){
                $query->where('plate_number','like','%'.$plate_number.'%');
            }
        })
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select();
        $this->success('',$list);
    }

    #[Get('apply')]
    public function apply()
    {
        $page=$this->request->get('page/d');
        $list=ParkingCarsApply::with(['pay'])->where(function ($query){
            $plate_number=$this->request->get('plate_number');
            $query->where('parking_id','=',$this->parking_id);
            if($plate_number){
                $query->where('plate_number','like','%'.$plate_number.'%');
            }
        })
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select();
        $this->success('',$list);
    }

    #[Get('apply-detail')]
    public function applyDetail()
    {
       $id=$this->request->get('apply_id');
       $detail=ParkingCarsApply::where(['parking_id'=>$this->parking_id,'id'=>$id])->find();
       $car_models=ParkingPlate::CARMODELS;
       $plate_type=ParkingMode::PLATETYPE;
       $this->success('',compact('detail','car_models','plate_type'));
    }

    #[Post('do-apply')]
    public function doApply()
    {
        $apply_id=$this->request->post('apply_id');
        $status=$this->request->post('status');
        $plates=$this->request->post('plates');
        $contact=$this->request->post('contact');
        $mobile=$this->request->post('mobile');
        $apply=ParkingCarsApply::with(['cars','pay'])->where(['parking_id'=>$this->parking_id,'id'=>$apply_id])->find();
        if(!$apply || $apply->status!==0){
            $this->error('申请不存在或者已经被审核，请刷新后再试');
        }
        if($status==1){
            try{
                Db::startTrans();
                if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('过期月卡申请续费')){
                    ParkingMonthlyRecharge::recharge($apply->cars,'now',$apply->pay);
                    ParkingCarsLogs::addLog($apply->cars,$this->parkingAdmin['id'],'审核通过月卡过期续费');
                    WechatMsg::successMonthlyApply($apply,true);
                }
                if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('申请月卡')){
                    $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking_id])->find();
                    $third=Third::where(['user_id'=>$apply->user_id,'platform'=>'miniapp'])->find();
                    $cars=ParkingCars::addCars($rules,$contact,$mobile,$apply->user_id,[$plates],['third_id'=>$third->id]);
                    ParkingMonthlyRecharge::recharge($cars,'end',$apply->pay);
                    ParkingCarsLogs::addLog($cars,$this->parkingAdmin['id'],'审核通过月卡申请');
                    WechatMsg::successMonthlyApply($apply,true);
                }
                if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('申请日租卡')){
                    $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking_id])->find();
                    $time=strtotime(date('Y-m-d 00:00:00',time()));
                    $endtime=$rules->online_apply_days*3600*24+$time-1;
                    $third=Third::where(['user_id'=>$apply->user_id,'platform'=>'miniapp'])->find();
                    $cars=ParkingCars::addCars($rules,$contact,$mobile,$apply->user_id,[$plates],['endtime'=>$endtime,'third_id'=>$third->id]);
                    ParkingCarsLogs::addLog($cars,$this->parkingAdmin['id'],'审核通过日租卡申请');
                    WechatMsg::successDayApply($apply,true);
                }
                if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('过期日租卡续期')){
                    $cars=$apply->cars;
                    $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking_id])->find();
                    $time=strtotime(date('Y-m-d 00:00:00',time()));
                    $endtime=$rules->online_renew_days*3600*24+$time-1;
                    $cars->endtime=$endtime;
                    $cars->save();
                    ParkingCarsLogs::addLog($cars,$this->parkingAdmin['id'],'审核通过日租卡续期');
                    WechatMsg::successDayApply($apply,true);
                }
                if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('申请储值卡')){
                    $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking_id])->find();
                    $time=strtotime(date('Y-m-d 00:00:00',time()));
                    $endtime=$rules->online_apply_days*3600*24+$time-1;
                    $third=Third::where(['user_id'=>$apply->user_id,'platform'=>'miniapp'])->find();
                    $cars=ParkingCars::addCars($rules,$contact,$mobile,$apply->user_id,[$plates],['endtime'=>$endtime,'third_id'=>$third->id]);
                    ParkingStoredLog::addRechargeLog($cars,$apply->pay);
                    ParkingCarsLogs::addLog($cars,$this->parkingAdmin['id'],'审核通过储值卡申请');
                }
                $apply->status=1;
                $apply->save();
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        if($status==2){
            try{
                $pay=$apply->pay;
                if($pay){
                    $pay->refund((float)$pay->pay_price,'申请被管理员拒绝');
                }
                $apply->status=2;
                $apply->save();
                if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('过期月卡申请续费') || $apply->apply_type==ParkingCarsApply::APPLY_TYPE('申请月卡')){
                    WechatMsg::successMonthlyApply($apply,false);
                }
                if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('过期日租卡续期') || $apply->apply_type==ParkingCarsApply::APPLY_TYPE('申请日租卡')){
                    WechatMsg::successDayApply($apply,false);
                }
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
        }
        $this->success('审核成功');
    }

    #[Post('add-black')]
    public function addBlack()
    {
        $plate_number=$this->request->post('plate_number');
        $remark=$this->request->post('remark');
        $black=ParkingBlack::where(['plate_number'=>$plate_number,'parking_id'=>$this->parking_id])->find();
        if($black){
            $this->error('车牌号已存在');
        }
        $black=new ParkingBlack();
        $black->plate_number=$plate_number;
        $black->remark=$remark;
        $black->parking_id=$this->parking_id;
        $black->admin_id=$this->parkingAdmin['id'];
        $black->save();
        $this->success();
    }

    #[Post('del-black')]
    public function delBlack()
    {
        $plate_number=$this->request->post('plate_number');
        $black=ParkingBlack::where(['plate_number'=>$plate_number,'parking_id'=>$this->parking_id])->find();
        if($black){
            $black->delete();
            $this->success();
        }else{
            $this->error('车牌号不存在');
        }
    }

    #[Get('detail')]
    public function detail()
    {
        $id=$this->request->get('id');
        $cars=ParkingCars::with(['plates'=>function($query){$query->limit(10);},'rules','third'])->where(['id'=>$id,'parking_id'=>$this->parking_id])->find();
        $this->success('',compact('cars'));
    }

    #[Get('recharge-detail')]
    public function rechargeDetail()
    {
        $id=$this->request->get('id');
        $plate_number=$this->request->get('plate_number');
        $rules_type=$this->request->get('rules_type');
        if($id){
            $cars=ParkingCars::with(['rules','plates'])->where(['id'=>$id,'parking_id'=>$this->parking_id])->find();
            if($cars->status=='hidden'){
                $this->error(ParkingRules::RULESTYPE[$cars->rules_type].'已经被禁用');
            }
            $this->success('',$cars);
        }
        if($plate_number && $rules_type){
            $plate=ParkingPlate::withJoin(['cars'=>function ($query) use ($rules_type){
                $query->where('deletetime','=',null);
                $query->where('rules_type','=',$rules_type);
            }])->where(['parking_plate.plate_number'=>$plate_number,'parking_plate.parking_id'=>$this->parking_id])->find();
            if($plate){
                $cars=$plate->cars;
                if($cars->status=='hidden'){
                    $this->error(ParkingRules::RULESTYPE[$cars->rules_type].'已经被禁用');
                }
                $cars->rules=ParkingRules::find($cars->rules_id);
                $cars->plates=ParkingPlate::where(['plate_number'=>$plate_number,'parking_id'=>$this->parking_id])->field('id,plate_number')->select();
                $this->success('',$cars);
            }else{
                $this->error(ParkingRules::RULESTYPE[$rules_type].'不存在');
            }
        }
    }

    #[Post('delplate')]
    public function delplate()
    {
        $cars_id=$this->request->post('cars_id');
        $ids=$this->request->post('id');
        $number=ParkingPlate::where(['parking_id'=>$this->parking_id,'cars_id'=>$cars_id])->count();
        if($number==1){
            $this->error('至少保留一个车牌');
        }
        ParkingPlate::where(['parking_id'=>$this->parking_id,'id'=>$ids])->delete();
        ParkingCars::where(['parking_id'=>$this->parking_id,'id'=>$cars_id])->update(['plates_count'=>$number-1]);
        $this->success('删除成功');
    }

    #[Post('addplate')]
    public function addplate()
    {
        $postdata=$this->request->post();
        $postdata['parking_id']=$this->parking_id;
        $havaplate=ParkingPlate::where(function($query) use ($postdata){
            $query->where('plate_number',$postdata['plate_number']);
            $query->where('parking_id',$this->parking_id);
            $query->where('cars_id','<>',null);
        })->find();
        if($havaplate){
            $this->error('车牌号已经存在');
        }
        $model=new ParkingPlate();
        $model->save($postdata);
        $number=ParkingPlate::where(['parking_id'=>$this->parking_id,'cars_id'=>$postdata['cars_id']])->count();
        ParkingCars::where(['parking_id'=>$this->parking_id,'id'=>$postdata['cars_id']])->update(['plates_count'=>$number]);
        $model['plate_type']=ParkingMode::PLATETYPE[$model['plate_type']];
        $model['car_models']=ParkingPlate::CARMODELS[$model['car_models']];
        $this->success('添加成功',$model);
    }

    #[Get('plates')]
    public function plates()
    {
        $page=$this->request->get('page/d');
        $model=new ParkingPlate();
        $list=$model->where(function ($query){
            $plate_number=$this->request->get('plate_number');
            $cars_id=$this->request->get('cars_id/d');
            $query->where('parking_id','=',$this->parking_id);
            $query->where('cars_id','=',$cars_id);
            if($plate_number){
                $query->where('plate_number','like','%'.$plate_number.'%');
            }
        })
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select()
        ->each(function ($res){
            $res['plate_type']=ParkingMode::PLATETYPE[$res['plate_type']];
            $res['car_models']=ParkingPlate::CARMODELS[$res['car_models']];
        });
        $this->success('',$list);
    }

    #[Get('log')]
    public function log()
    {
        $page=$this->request->get('page/d');
        $type=$this->request->get('type');
        $rules_type=$this->request->get('rules_type');
        $cars_id=$this->request->get('cars_id');
        if($type=='logs'){
            $model=new ParkingCarsLogs();
            $withlog=['admin'];
        }
        if($type=='recharge'){
            if($rules_type=='monthly'){
                $model=new ParkingMonthlyRecharge();
                $withlog=['payunion'];
            }
            if($rules_type=='stored') {
                $model = new ParkingStoredLog();
                $withlog = ['payunion'];
            }
        }
        if($type=='balance'){
            $model=new ParkingStoredLog();
            $withlog=['payunion'];
        }
        $list=$model->where(function ($query) use ($rules_type,$type,$cars_id){
            $starttime=$this->request->get('starttime');
            $endtime=$this->request->get('endtime');
            $query->where('parking_id','=',$this->parking_id);
            $query->where('cars_id','=',$cars_id);
            if($type=='recharge' && $rules_type=='stored') {
                $query->where('pay_id','<>',null);
            }
            if($starttime){
                $query->where('createtime','>=',strtotime($starttime." 00:00:00"));
            }
            if($endtime){
                $query->where('createtime','<=',strtotime($endtime." 23:59:59"));
            }
        })->with($withlog)
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select();
        $this->success('',$list);
    }

    #[Post('recharge')]
    public function recharge()
    {
        $cars_id=$this->request->post('cars_id');
        $money=$this->request->post('money/f');
        $change_type=$this->request->post('change_type');
        $starttime=$this->request->post('starttime');
        $endtime=$this->request->post('endtime');
        $remark=$this->request->post('remark');
        $cars=ParkingCars::with(['rules','plates'])->find($cars_id);
        if(!$cars || $cars->parking_id!=$this->parking_id){
            $this->error('车辆不存在');
        }
        if($cars->status=='hidden'){
            $this->error('车辆已被禁用');
        }
        if($money<=0){
            $this->error('充值金额不能小于0');
        }
        if($cars->rules_type=='monthly'){
            Db::startTrans();
            try{
                $payunion=PayUnion::underline(
                    $money,
                    PayUnion::ORDER_TYPE('停车月租缴费'),
                    ['parking_id'=>$this->parking_id],
                    $cars->plates[0]->plate_number.'停车月租缴费'
                );
                ParkingMonthlyRecharge::recharge($cars,$change_type,$payunion,$starttime,$endtime);
                $logremark="手机端充值".$money.'元';
                if($remark){
                    $logremark.="，备注：".$remark;
                }
                ParkingCarsLogs::addLog($cars,$this->parkingAdmin['id'],$logremark);
                Db::commit();
            }catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        if($cars->rules_type=='stored'){
            Db::startTrans();
            try{
                ParkingStoredLog::addAdminLog($cars,$change_type,$money,$remark);
                ParkingCarsLogs::addLog($cars,$this->parkingAdmin['id'],$change_type=='add'?'充值'.$money.'元':'修改余额'.$money.'元');
                Db::commit();
            }catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        $this->success('充值成功');
    }

    #[Get('info')]
    public function info()
    {
        $rules_type=$this->request->get('rules_type');
        $rules=ParkingRules::where(['parking_id'=>$this->parking_id,'rules_type'=>$rules_type])->select();
        $car_models=ParkingPlate::CARMODELS;
        $plate_type=ParkingMode::PLATETYPE;
        $this->success('',compact('rules','car_models','plate_type'));
    }

    #[Get('occupat')]
    public function occupat()
    {
        $cars_id=$this->request->get('cars_id'); 
        $list=ParkingCarsOccupat::with(['records'])->where(['parking_id'=>$this->parking_id,'cars_id'=>$cars_id])->select();
        foreach ($list as $k=>$v){
            if(!$v['entry_time']){
                $v['entry_time']='';
                $v['plate_number']='无车辆';
                $v['records']=[
                    'entry_time'=>0,
                    'entry_time_txt'=>''
                ];
                continue;
            }
            $v['provisional']=$v['entry_time']-$v['records']['entry_time'];
            $list[$k]['entry_time']=date('Y-m-d H:i',$v['entry_time']);
        }
        $this->success('',$list);
    }

    #[Post('del')]
    public function del()
    {
        $id=$this->request->post('id');
        $cars=ParkingCars::where(['id'=>$id,'parking_id'=>$this->parking_id])->find();
        if($cars){
            $cars->delete();
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

    #[Post('restore')]
    public function restore()
    {
        $id=$this->request->post('id');
        $row=$this->model->onlyTrashed()->find($id);
        $plate=ParkingPlate::where(function ($query) use ($id){
            $prefix=getDbPrefix();
            $query->whereRaw("cars_id in (select id from {$prefix}parking_cars where deletetime is null and parking_id={$this->parking_id})");
            $query->where('cars_id','<>',$id);
        })->find();
        if($plate){
            $this->error('车牌号【'.$plate['plate_number'].'】已存在');
        }
        if($row && $row['parking_id']==$this->parking_id){
            $row->restore();
        }
        $this->success();
    }

    #[Post('destroy')]
    public function destroy()
    {
        $id=$this->request->post('id');
        $row=$this->model->onlyTrashed()->find($id);
        if($row && $row['parking_id']==$this->parking_id){
            $row->force()->delete();
        }
        ParkingPlate::where(['parking_id'=>$this->parking_id,'cars_id'=>$id])->delete();
        $this->success();
    }

    #[Post('edit')]
    public function edit()
    {
        $postdata=$this->request->post();
        $remark_line=null;
        if($postdata['remark_line']){
            $remark_line=[];
            foreach ($postdata['remark_line'] as $value){
                $remark_line[$value['remark']]=$value['value'];
            }
            $remark_line=json_encode($remark_line,JSON_UNESCAPED_UNICODE);
        }
        $options=[
            'starttime'=>strtotime($postdata['starttime'].' 00:00:00'),
            'endtime'=>strtotime($postdata['endtime'].' 23:59:59'),
            'remark'=>$postdata['remark'],
            'status'=>$postdata['status'],
            'contact'=>$postdata['contact'],
            'mobile'=>$postdata['mobile'],
            'rules_id'=>$postdata['rules_id'],
            'occupat_number'=>$postdata['occupat_number'],
            'third_id'=>$postdata['third_id'],
            'remark_line'=>$remark_line
        ];
        if($postdata['id']){
            $cars=ParkingCars::where(['parking_id'=>$this->parking_id,'id'=>$postdata['id']])->find();
            try{
                Db::startTrans();
                ParkingCars::editCars($cars,$postdata['plates'],$options);
                ParkingCarsLogs::addLog($cars,$this->parkingAdmin['id'],'手机端编辑');
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }else{
            $rules=ParkingRules::where(['parking_id'=>$this->parking_id,'id'=>$postdata['rules_id']])->find();
            if($postdata['third_id']){
                $third=Third::find($postdata['third_id']);
            }
            try {
                Db::startTrans();
                $cars=ParkingCars::addCars($rules,$postdata['contact'],$postdata['mobile'],$postdata['third_id']?$third->user_id:null,$postdata['plates'],$options);
                if($rules->rules_type==ParkingRules::RULESTYPE('月租车') && $postdata['pay']){
                    $payunion=PayUnion::underline(
                        $postdata['pay'],
                        PayUnion::ORDER_TYPE('停车月租缴费'),
                        ['parking_id'=>$cars->parking_id],
                        $postdata['plates'][0]['plate_number'].'停车月租缴费'
                    );
                    (new ParkingMonthlyRecharge())->save([
                        'parking_id'=>$cars->parking_id,
                        'cars_id'=>$cars->id,
                        'rules_id'=>$cars->rules_id,
                        'pay_id'=>$payunion->id,
                        'money'=>$postdata['pay'],
                        'starttime'=>$options['starttime'],
                        'endtime'=>$options['endtime']
                    ]);
                }
                if($rules->rules_type==ParkingRules::RULESTYPE('储值车') && $postdata['pay']){
                    ParkingStoredLog::addAdminLog($cars,'add',$postdata['pay'],$options['remark']);
                }
                $log='手机端添加';
                if($postdata['pay']){
                    $log.="，充值￥".$postdata['pay'];
                }
                ParkingCarsLogs::addLog($cars,$this->parkingAdmin['id'],$log);
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        $this->success('操作成功');
    }
}
