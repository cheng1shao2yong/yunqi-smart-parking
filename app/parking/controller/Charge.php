<?php
declare (strict_types = 1);

namespace app\parking\controller;

use app\common\controller\ParkingBase;
use app\common\model\parking\ParkingCharge;
use app\common\model\parking\ParkingChargeList;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingRules;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("charge")]
class Charge extends ParkingBase
{
    protected function _initialize()
    {
        parent::_initialize();
    }

    #[Route('GET,POST','setting')]
    public function setting()
    {
        if($this->request->isPost()){
            $postdata=$this->request->post();
            $postdata['rules_value']=json_encode($postdata['rules_value']);
            $model=ParkingCharge::where('parking_id',$this->parking->id)->find();
            if(!$model){
                $model=new ParkingCharge();
                $postdata['parking_id']=$this->parking->id;
            }
            $model->save($postdata);
            $this->success();
        }
        $coupon=ParkingMerchantCoupon::where(['parking_id'=>$this->parking->id,'status'=>'normal'])->column('title','id');
        $charge=ParkingCharge::where('parking_id',$this->parking->id)->find();
        if($charge){
            $charge->merch_id=(string)$charge->merch_id;
            $charge->rules_id=(string)$charge->rules_id;
        }
        $this->assign('charge',$charge);
        $this->assign('merch',ParkingMerchant::where(['parking_id'=>$this->parking->id,'status'=>'normal'])->column('merch_name','id'));
        $this->assign('channel',ParkingCharge::CHANNEL);
        $this->assign('trigger',ParkingCharge::TRIGGER);
        $this->assign('rules',ParkingRules::where(['parking_id'=>$this->parking->id,'status'=>'normal'])->where('rules_type','<>','provisional')->column('title','id'));
        $this->assign('coupon',$coupon);
        return $this->fetch();
    }

    #[Route('GET,JSON','list')]
    public function list()
    {
        if (false === $this->request->isAjax()) {
            $this->assign('status',ParkingMerchantCouponList::STATUS);
            return $this->fetch();
        }
        $this->model = new ParkingChargeList();
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['coupon','couponlist'])
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

}