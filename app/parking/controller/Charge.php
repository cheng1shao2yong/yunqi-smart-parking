<?php
declare (strict_types = 1);

namespace app\parking\controller;

use app\admin\traits\Actions;
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
    use Actions{
        add as _add;
        edit as _edit;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = new ParkingCharge();
        $this->assign('channel',ParkingCharge::CHANNEL);
        $this->assign('trigger',ParkingCharge::TRIGGER);
        $this->assign('coupon',ParkingMerchantCoupon::where(['parking_id'=>$this->parking->id,'status'=>'normal'])->column('title','id'));
        $this->assign('merch',ParkingMerchant::where(['parking_id'=>$this->parking->id,'status'=>'normal'])->column('merch_name','id'));
        $this->assign('rules',ParkingRules::where(['parking_id'=>$this->parking->id,'status'=>'normal'])->where('rules_type','<>','provisional')->column('title','id'));
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        [$where, $order, $limit, $with] = $this->buildparams();
        $list = $this->model
            ->with(['merch'])
            ->where(['parking_id'=>$this->parking->id])
            ->order($order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('POST','setting')]
    public function setting()
    {
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

    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $merch_id=$this->request->post('row.merch_id');
            $rules_value=$this->request->post('row.rules_value');
            $coupon=ParkingMerchant::where(['id'=>$merch_id,'parking_id'=>$this->parking->id])->value('coupon');
            foreach ($rules_value as $item){
                if(!in_array($item['coupon_id'],explode(',',$coupon))){
                    $title=ParkingMerchantCoupon::where(['id'=>$item['coupon_id']])->value('title');
                    $this->error('商户没有配置优惠券：'.$title);
                }
            }
            $this->postParams['parking_id']=$this->parking->id;
            $this->postParams['rules_value']=json_encode($rules_value);
        }
        return $this->_add();
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if($this->request->isPost()){
            $merch_id=$this->request->post('row.merch_id');
            $rules_value=$this->request->post('row.rules_value');
            $coupon=ParkingMerchant::where(['id'=>$merch_id,'parking_id'=>$this->parking->id])->value('coupon');
            foreach ($rules_value as $item){
                if(!in_array($item['coupon_id'],explode(',',$coupon))){
                    $title=ParkingMerchantCoupon::where(['id'=>$item['coupon_id']])->value('title');
                    $this->error('商户没有配置优惠券：'.$title);
                }
            }
            $this->postParams['rules_value']=json_encode($rules_value);
        }
        return $this->_edit();
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