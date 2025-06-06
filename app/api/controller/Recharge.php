<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingCarsApply;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRules;
use app\common\model\PayUnion;
use app\common\model\Third;
use app\common\service\msg\WechatMsg;
use app\common\service\PayService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Db;

#[Group("recharge")]
class Recharge extends Api
{
    #[Get('parking')]
    public function parking()
    {
        $type=$this->request->get('type');
        $plate_number=$this->request->get('plate_number');
        $rules_type=[
            'monthly_renew'=>'monthly',
            'stored_renew'=>'stored'
        ];
        $type=$rules_type[$type];
        $prefix=getDbPrefix();
        $sql="
            SELECT id,title FROM {$prefix}parking where id in(
            SELECT pc.parking_id FROM {$prefix}parking_cars pc,{$prefix}parking_plate pp 
            where 
            pc.id=pp.cars_id 
            and pp.plate_number='{$plate_number}' 
            and pc.rules_type='{$type}'
            and pc.deletetime is null
        )";
        $list=Db::query($sql);
        $this->success('',$list);
    }

    #[Post('storedapply')]
    public function storedapply()
    {
        $rules_id=$this->request->post('rules_id');
        $plate_number=$this->request->post('plate_number');
        $mobile=$this->request->post('mobile');
        $contact=$this->request->post('contact');
        $totalfee=$this->request->post('totalfee');
        $remark=$this->request->post('remark','');
        $rules=ParkingRules::with(['parking'])->where('id',$rules_id)->find();
        if(!$rules){
            $this->error('储值卡规则不存在');
        }
        if($rules->rules_type!='stored'){
            $this->error('储值卡规则类型错误');
        }
        if($rules->online_apply=='no'){
            $this->error('储值卡不支持在线申请');
        }
        if($rules->min_stored>$totalfee){
            $this->error('储值卡充值金额不能小于'.$rules->min_stored);
        }
        if($this->checkCarsInParking($rules->parking_id,$plate_number)){
            $this->error($plate_number.'已经登记在该停车场');
        }
        $apply=ParkingCarsApply::where(['parking_id'=>$rules->parking_id,'plate_number'=>$plate_number,'status'=>0])->find();
        if($apply){
            $this->error('您在该停车场有一条月卡申请记录，请等待处理');
        }
        $parking=$rules->parking;
        $service=PayService::newInstance([
            'pay_type_handle'=>$parking->pay_type_handle,
            'user_id'=>$this->auth->id,
            'parking_id'=>$parking->id,
            'sub_merch_no'=>$parking->sub_merch_no,
            'split_merch_no'=>$parking->split_merch_no,
            'persent'=>$parking->parking_recharge_persent,
            'pay_price'=>$totalfee,
            'order_type'=>PayUnion::ORDER_TYPE('停车储值卡充值'),
            'order_body'=>'用户'.$contact.'储值卡缴费'.$totalfee.'元',
            'attach'=>json_encode([
                'rules_id'=>$rules_id,
                'parking_title'=>$parking->title,
                'contact'=>$contact,
                'mobile'=>$mobile,
                'plate_number'=>$plate_number,
                'remark'=>$remark
            ],JSON_UNESCAPED_UNICODE)
        ]);
        $r=$service->wechatMiniappPay();
        $this->success('',$r);
    }

    #[Post('storedrenew')]
    public function storedrenew()
    {
        $cars_id=$this->request->post('cars_id');
        $plate_number=$this->request->post('plate_number');
        $totalfee=$this->request->post('totalfee');
        $cars=ParkingCars::with(['rules','parking'])->where('id',$cars_id)->find();
        if(!$cars){
            $this->error('储值卡不存在');
        }
        if($cars->status!='normal'){
            $this->error('储值卡已经被禁止');
        }
        if($cars->endtime<time()){
            $this->error('储值卡已过期');
        }
        if($cars->rules->min_stored>$totalfee){
            $this->error('储值卡充值金额不能小于'.$cars->rules->min_stored);
        }
        $parking=$cars->parking;
        $service=PayService::newInstance([
            'pay_type_handle'=>$parking->pay_type_handle,
            'user_id'=>$this->auth->id,
            'parking_id'=>$parking->id,
            'sub_merch_no'=>$parking->sub_merch_no,
            'split_merch_no'=>$parking->split_merch_no,
            'persent'=>$parking->parking_recharge_persent,
            'pay_price'=>$totalfee,
            'order_type'=>PayUnion::ORDER_TYPE('停车储值卡充值'),
            'order_body'=>'用户'.$cars->contact.'储值卡缴费'.$totalfee.'元',
            'attach'=>json_encode([
                'cars_id'=>$cars->id,
                'parking_title'=>$parking->title,
                'plate_number'=>$plate_number,
            ],JSON_UNESCAPED_UNICODE)
        ]);
        $r=$service->wechatMiniappPay();
        $this->success('',$r);
    }

    #[Post('dayrenew')]
    public function dayrenew()
    {
        $cars_id=$this->request->post('cars_id');
        $plate_number=$this->request->post('plate_number');
        $remark=$this->request->post('remark','');
        $cars=ParkingCars::with(['rules','parking'])->where('id',$cars_id)->find();
        if(!$cars){
            $this->error('日租卡不存在');
        }
        if($cars->status!='normal'){
            $this->error('日租卡已经被禁止');
        }
        if($cars->endtime<time() && $cars->rules->online_renew=='no'){
            $this->error('过期日租卡不支持在线续期');
        }
        $apply=ParkingCarsApply::where(['parking_id'=>$cars->parking_id,'plate_number'=>$plate_number,'status'=>0])->find();
        if($apply){
            $this->error('您在该停车场有一条月卡申请记录，请等待处理');
        }
        if($cars->rules->auto_online_renew=='yes'){
            $time=strtotime(date('Y-m-d 00:00:00',time()));
            $endtime=$cars->rules->online_renew_days*3600*24+$time-1;
            $cars->endtime=$endtime;
            $cars->save();
            $this->success('日租卡续期成功');
        }
        if($cars->rules->auto_online_renew=='no'){
            $plate=ParkingPlate::where(['plate_number'=>$plate_number,'cars_id'=>$cars->id])->find();
            $apply=new ParkingCarsApply();
            $apply->save([
                'parking_id'=>$cars->parking_id,
                'user_id'=>$this->auth->id,
                'apply_type'=>ParkingCarsApply::APPLY_TYPE('过期日租卡续期'),
                'cars_id'=>$cars->id,
                'plate_number'=>$plate_number,
                'plate_type'=>$plate->plate_type,
                'car_models'=>$plate->car_models,
                'mobile'=>$cars->mobile,
                'contact'=>$cars->contact,
                'rules_type'=>$cars->rules_type,
                'rules_id'=>$cars->rules_id,
                'remark'=>$remark?json_encode($remark,JSON_UNESCAPED_UNICODE):'',
                'status'=>0
            ]);
            WechatMsg::dayCarApply($apply);
            $this->success('续期申请成功');
        }
    }

    #[Post('dayapply')]
    public function dayapply()
    {
        $rules_id=$this->request->post('rules_id');
        $merch_id=$this->request->post('merch_id');
        $mobile=$this->request->post('mobile');
        $contact=$this->request->post('contact');
        $plate_number=$this->request->post('plate_number');
        $remark=$this->request->post('remark','');
        $rules=ParkingRules::with(['parking'])->where('id',$rules_id)->find();
        if(!$rules){
            $this->error('日租卡规则不存在');
        }
        if($rules->rules_type!='day'){
            $this->error('日租卡规则类型错误');
        }
        if($rules->online_apply=='no'){
            $this->error('日租卡不支持在线申请');
        }
        if($this->checkCarsInParking($rules->parking_id,$plate_number)){
            $this->error($plate_number.'已经登记在该停车场');
        }
        $apply=ParkingCarsApply::where(['parking_id'=>$rules->parking_id,'plate_number'=>$plate_number,'status'=>0])->find();
        if($apply){
            $this->error('您在该停车场有一条预约车申请记录，请等待处理');
        }
        if($rules->auto_online_apply=='yes'){
            $plates=[
                'plate_number'=>$plate_number,
                'plate_type'=>'blue',
                'car_models'=>'small',
            ];
            $time=strtotime(date('Y-m-d 00:00:00',time()));
            $endtime=$rules->online_apply_days*3600*24+$time-1;
            $third=Third::where(['user_id'=>$this->auth->id,'platform'=>'miniapp'])->find();
            ParkingCars::addCars($rules,$contact,$mobile,$this->auth->id,[$plates],['endtime'=>$endtime,'third_id'=>$third->id]);
        }
        if($rules->auto_online_apply=='no'){
            $apply=new ParkingCarsApply();
            $apply->save([
                'parking_id'=>$rules->parking_id,
                'user_id'=>$this->auth->id,
                'apply_type'=>ParkingCarsApply::APPLY_TYPE('申请日租卡'),
                'plate_number'=>$plate_number,
                'mobile'=>$mobile,
                'contact'=>$contact,
                'rules_type'=>$rules->rules_type,
                'rules_id'=>$rules->id,
                'merch_id'=>$merch_id??null,
                'remark'=>$remark?json_encode($remark,JSON_UNESCAPED_UNICODE):'',
                'status'=>0
            ]);
            WechatMsg::dayCarApply($apply);
        }
        $this->success('日租卡申请成功');
    }

    #[Post('monthrenew')]
    public function monthrenew()
    {
        $cars_id=$this->request->post('cars_id');
        $month=$this->request->post('month');
        $change_type=$this->request->post('change_type');
        $plate_number=$this->request->post('plate_number');
        $remark=$this->request->post('remark','');
        $cars=ParkingCars::with(['rules','parking'])->where('id',$cars_id)->find();
        if(!$cars){
            $this->error('月卡不存在');
        }
        if($cars->status!='normal'){
            $this->error('月卡已经被禁止');
        }
        //月卡已经过期
        if(($cars->endtime+$cars->rules->renew_limit_day*3600*24)<time()){
            if($cars->rules->online_renew=='no'){
                $this->error('过期月卡不支持在线续期');
            }
            if($cars->rules->online_renew=='yes'){
                $apply=ParkingCarsApply::where(['parking_id'=>$cars->parking_id,'plate_number'=>$plate_number,'status'=>0])->find();
                if($apply){
                    $this->error('您在该停车场有一条月卡申请记录，请等待处理');
                }
            }
        }
        if($cars->rules->fee<=0){
            $this->error('月卡费用为0不支持在线续期');
        }
        if($cars->rules->online_recharge=='no'){
            $this->error('月卡不支持在线续费');
        }
        $pay_price=bcmul($cars->rules->fee,(string)$month,2);
        $parking=$cars->parking;
        $service=PayService::newInstance([
            'pay_type_handle'=>$parking->pay_type_handle,
            'user_id'=>$this->auth->id,
            'parking_id'=>$parking->id,
            'sub_merch_no'=>$parking->sub_merch_no,
            'split_merch_no'=>$parking->split_merch_no,
            'persent'=>$parking->parking_recharge_persent,
            'pay_price'=>$pay_price,
            'order_type'=>PayUnion::ORDER_TYPE('停车月租缴费'),
            'order_body'=>'用户'.$cars->contact.'月租缴费'.$pay_price.'元',
            'attach'=>json_encode([
                'cars_id'=>$cars->id,
                'parking_title'=>$parking->title,
                'plate_number'=>$plate_number,
                'change_type'=>$change_type,
                'remark'=>$remark
            ],JSON_UNESCAPED_UNICODE)
        ]);
        $r=$service->wechatMiniappPay();
        $this->success('',$r);
    }

    #[Post('monthapply')]
    public function monthapply()
    {
        $rules_id=$this->request->post('rules_id');
        $mobile=$this->request->post('mobile');
        $contact=$this->request->post('contact');
        $month=$this->request->post('month');
        $plate_number=$this->request->post('plate_number');
        $remark=$this->request->post('remark','');
        $rules=ParkingRules::with(['parking'])->where('id',$rules_id)->find();
        if(!$rules){
            $this->error('月卡规则不存在');
        }
        if($rules->rules_type!='monthly'){
            $this->error('月卡规则类型错误');
        }
        if($rules->online_apply=='no'){
            $this->error('月卡不支持在线申请');
        }
        if($this->checkCarsInParking($rules->parking_id,$plate_number)){
            $this->error($plate_number.'已经登记在该停车场');
        }
        $apply=ParkingCarsApply::where(['parking_id'=>$rules->parking_id,'plate_number'=>$plate_number,'status'=>0])->find();
        if($apply){
            $this->error('您在该停车场有一条月卡申请记录，请等待处理');
        }
        if($rules->fee<=0){
            $this->error('月卡费用为0不支持在线申请');
        }
        $pay_price=bcmul($rules->fee,(string)$month,2);
        $parking=$rules->parking;
        $service=PayService::newInstance([
            'pay_type_handle'=>$parking->pay_type_handle,
            'user_id'=>$this->auth->id,
            'parking_id'=>$parking->id,
            'sub_merch_no'=>$parking->sub_merch_no,
            'split_merch_no'=>$parking->split_merch_no,
            'persent'=>$parking->parking_recharge_persent,
            'pay_price'=>$pay_price,
            'order_type'=>PayUnion::ORDER_TYPE('停车月租缴费'),
            'order_body'=>'用户'.$contact.'月租缴费'.$pay_price.'元',
            'attach'=>json_encode([
                'rules_id'=>$rules_id,
                'parking_title'=>$parking->title,
                'contact'=>$contact,
                'mobile'=>$mobile,
                'plate_number'=>$plate_number,
                'remark'=>$remark
            ],JSON_UNESCAPED_UNICODE)
        ]);
        $r=$service->wechatMiniappPay();
        $this->success('',$r);
    }

    #[Get('cars')]
    public function cars()
    {
        $parking_id=$this->request->get('parking_id');
        $type=$this->request->get('type');
        $plate_number=$this->request->get('plate_number');
        $plate=ParkingPlate::withJoin([
            'cars'=>function ($query) use ($type){
                $query->where('rules_type','=',$type);
                $query->where('deletetime','=',null);
            }
        ],'inner')->where(['parking_plate.plate_number'=>$plate_number,'parking_plate.parking_id'=>$parking_id])->find();
        if($plate && $plate->cars->endtime<time()){
            $plate->cars->is_expire=1;
        }
        $rules=ParkingRules::where(['parking_id'=>$parking_id,'rules_type'=>$type])->select();
        $mode=ParkingMode::where(['parking_id'=>$parking_id])->column('title','id');
        $time=time();
        foreach ($rules as $k=>$v){
            if($v->rules_type==ParkingRules::RULESTYPE('日租车')){
                $tremark=[];
                $tremark[]=[
                    'remark'=>'入场时间',
                    'type'=>'datetime',
                    'value'=>date('Y-m-d H:00',$time)
                ];
                $tremark[]=[
                    'remark'=>'离开时间',
                    'type'=>'datetime',
                    'value'=>date('Y-m-d H:00',$time+24*3600)
                ];
                $tremark[]=[
                    'remark'=>'申请事由',
                    'type'=>'txt'
                ];
                if($v->online_apply_remark){
                    $rules[$k]->online_apply_remark=array_merge($v->online_apply_remark,$tremark);
                }else{
                    $rules[$k]->online_apply_remark=$tremark;
                }
                if($v->online_renew_remark){
                    $rules[$k]->online_renew_remark=array_merge($v->online_renew_remark,$tremark);
                }else{
                    $rules[$k]->online_renew_remark=$tremark;
                }
            }
            if($v->rules_type==ParkingRules::RULESTYPE('月租车')){
                $rechargeFee=[];
                for($i=$v->min_month;$i<=$v->max_month;$i++){
                    $starttime=date('Y-m-d',$time);
                    $endtime=date('Y-m-d',strtotime("+{$i} month",strtotime($starttime)-1));
                    $rechargeFee[]=[
                        'month'=>$i,
                        'fee'=>bcmul($v->fee,(string)$i,2),
                        'txt'=>'￥'.($v->fee*$i).'/'.$i.'个月',
                        'starttime'=>$starttime,
                        'endtime'=>$endtime
                    ];
                }
                $rules[$k]->rechargeFee=$rechargeFee;
                if($plate && $plate->cars->rules_id==$v->id){
                    $xuFee=[];
                    for($i=$v->min_renew;$i<=$v->max_renew;$i++){
                        if(($plate->cars->endtime+$v->renew_limit_day*3600*24)<$time){
                            $plate->cars->is_xufee_expire=1;
                            $starttime=date('Y-m-d',$time);
                        }else{
                            $starttime=date('Y-m-d',$plate->cars->endtime+1);
                        }
                        $endtime=date('Y-m-d',strtotime("+{$i} month",strtotime($starttime)-1));
                        $xuFee[]=[
                            'month'=>$i,
                            'fee'=>bcmul($v->fee,(string)$i,2),
                            'txt'=>'￥'.($v->fee*$i).'/'.$i.'个月',
                            'starttime'=>$starttime,
                            'endtime'=>$endtime
                        ];
                    }
                    $rules[$k]->xuFee=$xuFee;
                }
            }
        }
        $parking=Parking::getParkingInfo($parking_id);
        $this->success('',[
            'parking'=>$parking,
            'rules'=>$rules,
            'plate'=>$plate,
            'mode'=>$mode
        ]);
    }

    private function checkCarsInParking($parking_id,$plate_number):bool
    {
        $havaplate=ParkingPlate::where(function($query) use ($parking_id,$plate_number){
            $query->where('plate_number',strtoupper($plate_number));
            $query->where('parking_id',$parking_id);
            $prefix=getDbPrefix();
            $query->whereRaw("cars_id in (select id from {$prefix}parking_cars where deletetime is null and parking_id={$parking_id})");
        })->find();
        if($havaplate){
           return true;
        }
        return false;
    }
}
