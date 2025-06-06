<?php
declare (strict_types = 1);

namespace app\parking\controller;

use app\common\controller\ParkingBase;
use app\common\model\parking\ParkingMerchantCoupon;
use think\annotation\route\Group;
use app\parking\traits\Actions;
use think\annotation\route\Route;
use think\facade\Cache;

#[Group("merchant-coupon")]
class MerchantCoupon extends ParkingBase
{
    use Actions{
        add as _add;
        edit as _edit;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = new ParkingMerchantCoupon();
        $this->assign('couponType',ParkingMerchantCoupon::COUPON_TYPE);
        Cache::delete('parking_coupon_'.$this->parking->id);
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
            ->where($where)
            ->order("weigh desc,id desc")
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if ($this->request->isPost()) {
            $coupon_type=$this->request->post('row.coupon_type');
            $effective=$this->request->post('row.effective/d');
            $this->postParams['parking_id']=$this->parking->id;
            $this->postParams['timespan']='';
            if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时长券')){
                $time=$this->request->post('row.time/d');
                if($effective && $effective*60<$time){
                    $this->error('有效时间不能小于优惠时长');
                }
            }
            if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时效券')){
                $period=$this->request->post('row.period/d');
                if($effective && $effective<$period){
                    $this->error('有效时间不能小于优惠时效');
                }
            }
            if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时段券')){
                $timespan=$this->request->post('row.timespan/a');
                $this->postParams['timespan']=json_encode($timespan);
            }
        }
        return $this->_add();
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if ($this->request->isPost()) {
            $coupon_type=$this->request->post('row.coupon_type');
            $effective=$this->request->post('row.effective/d');
            $this->postParams['timespan']='';
            if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时长券')){
                $time=$this->request->post('row.time/d');
                if($effective && $effective*60<$time){
                    $this->error('有效时间不能小于优惠时长');
                }
            }
            if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时效券')){
                $period=$this->request->post('row.period/d');
                if($effective && $effective<$period){
                    $this->error('有效时间不能小于优惠时效');
                }
            }
            if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时段券')){
                $timespan=$this->request->post('row.timespan/a');
                $this->postParams['timespan']=json_encode($timespan);
            }
        }
        return $this->_edit();
    }
}