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
use app\common\model\parking\ParkingCarsApply;
use app\common\model\parking\ParkingCarsLogs;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingMonthlyRecharge;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingStoredLog;
use app\common\model\Third;
use app\common\service\msg\WechatMsg;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("apply")]
class Apply extends ParkingBase
{
    public function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingCarsApply();
        $this->assign('applyType',ParkingCarsApply::APPLY_TYPE);
        $this->assign('car_models',ParkingPlate::CARMODELS);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        if($this->request->post('selectpage')){
            return $this->selectpage($where);
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['pay'])
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,POST','shenhe')]
    public function shenhe()
    {
        if (false === $this->request->isPost()) {
            $id=$this->request->get('ids');
            $apply=ParkingCarsApply::with(['cars','pay','rules'])->where(['parking_id'=>$this->parking->id,'id'=>$id])->find();
            if(!$apply || $apply->status!==0){
                $this->error('申请不存在或者已经被审核，请刷新后再试');
            }
            $plates=array([
                'plate_number'=>$apply->plate_number,
                'plate_type'=>$apply->plate_type,
                'car_models'=>$apply->car_models,
            ]);
            $apply->plates=$plates;
            unset($apply->plate_number);
            unset($apply->plate_type);
            unset($apply->car_models);
            $this->assign('apply',$apply);
            $this->assign('rules_type',ParkingRules::RULESTYPE);
            $this->assign('plate_type',ParkingMode::PLATETYPE);
            $this->assign('car_models',ParkingPlate::CARMODELS);
            return $this->fetch();
        }else{
            $ids=$this->request->post('ids');
            $status=$this->request->post('status');
            $plates=$this->request->post('plates');
            $contact=$this->request->post('contact');
            $mobile=$this->request->post('mobile');
            $apply=ParkingCarsApply::with(['cars','pay'])->where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
            if(!$apply || $apply->status!==0){
                $this->error('申请不存在或者已经被审核，请刷新后再试');
            }
            if($status==1){
                try{
                    Db::startTrans();
                    if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('过期月卡申请续费')){
                        ParkingMonthlyRecharge::recharge($apply->cars,'now',$apply->pay);
                        ParkingCarsLogs::addLog($apply->cars,$this->auth->id,'审核通过月卡过期续费');
                        WechatMsg::successMonthlyApply($apply,true);
                    }
                    if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('申请月卡')){
                        $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking->id])->find();
                        $third=Third::where(['user_id'=>$apply->user_id,'platform'=>'miniapp'])->find();
                        $cars=ParkingCars::addCars($rules,$contact,$mobile,$apply->user_id,$plates,['third_id'=>$third->id]);
                        ParkingMonthlyRecharge::recharge($cars,'end',$apply->pay);
                        ParkingCarsLogs::addLog($cars,$this->auth->id,'审核通过月卡申请');
                        WechatMsg::successMonthlyApply($apply,true);
                    }
                    if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('申请日租卡')){
                        $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking->id])->find();
                        $time=strtotime(date('Y-m-d 00:00:00',time()));
                        $endtime=$rules->online_apply_days*3600*24+$time-1;
                        $third=Third::where(['user_id'=>$apply->user_id,'platform'=>'miniapp'])->find();
                        $cars=ParkingCars::addCars($rules,$contact,$mobile,$apply->user_id,$plates,['endtime'=>$endtime,'third_id'=>$third->id]);
                        ParkingCarsLogs::addLog($cars,$this->auth->id,'审核通过日租卡申请');
                        WechatMsg::successDayApply($apply,true);
                    }
                    if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('过期日租卡续期')){
                        $cars=$apply->cars;
                        $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking->id])->find();
                        $time=strtotime(date('Y-m-d 00:00:00',time()));
                        $endtime=$rules->online_renew_days*3600*24+$time-1;
                        $cars->endtime=$endtime;
                        $cars->save();
                        ParkingCarsLogs::addLog($cars,$this->auth->id,'审核通过日租卡续期');
                        WechatMsg::successDayApply($apply,true);
                    }
                    if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('申请储值卡')){
                        $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking->id])->find();
                        $time=strtotime(date('Y-m-d 00:00:00',time()));
                        $endtime=$rules->online_apply_days*3600*24+$time-1;
                        $third=Third::where(['user_id'=>$apply->user_id,'platform'=>'miniapp'])->find();
                        $cars=ParkingCars::addCars($rules,$contact,$mobile,$apply->user_id,$plates,['endtime'=>$endtime,'third_id'=>$third->id]);
                        ParkingStoredLog::addRechargeLog($cars,$apply->pay);
                        ParkingCarsLogs::addLog($cars,$this->auth->id,'审核通过储值卡申请');
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
            $this->success('操作成功');
        }
    }
}
