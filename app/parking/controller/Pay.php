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

use app\parking\traits\Actions;
use app\common\controller\ParkingBase;
use app\common\model\parking\ParkingDailyCashFlow;
use app\common\model\PayRefund;
use app\common\model\PayUnion;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("pay")]
class Pay extends ParkingBase
{
    use Actions{
        download as _download;
    }

    public function _initialize()
    {
        parent::_initialize();
        $this->assign('orderType',PayUnion::ORDER_TYPE);
        $this->assign('payType',PayUnion::PAYTYPE);
        $this->model=new PayUnion();
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $pay_type=$this->request->get('pay_type');
        $where=[];
        $where[]=['yun_pay_union.parking_id','=',$this->parking->id];
        $where[]=['yun_pay_union.pay_status','=',1];
        $plate_number=$this->filter('plate_number');
        if($plate_number){
            $where[]=['yun_pay_union.order_type','=',PayUnion::ORDER_TYPE('停车缴费')];
            $where[]=['yun_pay_union.detail','like',$plate_number.'%'];
        }
        if($pay_type=='underline'){
            $where[]=['yun_pay_union.pay_type','=',PayUnion::PAYTYPE('线下支付')];
        }else{
            $where[]=['yun_pay_union.pay_type','<>','underline'];
            $where[]=['yun_pay_union.pay_type','<>','stored'];
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $pay_price = $this->model
            ->where($where)
            ->sum('pay_price');
        $refund_price = $this->model
            ->where($where)
            ->sum('refund_price');
        $handling_fees = $this->model
            ->where('yun_pay_union.refund_price',null)
            ->where($where)
            ->sum('handling_fees');
        $summary='金额：'.$pay_price.'元，退款金额：'.$refund_price.'元，手续费：'.formatNumber($handling_fees/100).'元';
        $result = ['total' => $list->total(), 'rows' => $list->items(),'summary'=>$summary];
        return json($result);
    }

    #[Route('GET,POST','del')]
    public function del()
    {
        $this->error();
    }

    #[Route('GET,JSON','download')]
    public function download()
    {
        if($this->request->isAjax()){
            $entry_time=$this->filter('pay_time');
            if(!$entry_time){
                $this->error('请选择支付时间');
            }
        }
        return $this->_download();
    }

    #[Route('GET,JSON','downsettle')]
    public function downsettle()
    {
        return $this->_download();
    }

    #[Route('POST,GET','refund')]
    public function refund()
    {
        if (false === $this->request->isPost()) {
            $pay_id=$this->request->get('ids');
            $pay=PayUnion::where(['id'=>$pay_id,'parking_id'=>$this->parking->id])->find();
            if(!$pay){
                $this->error('支付记录不存在');
            }
            if($pay->refund_price==$pay->pay_price){
                $this->error('该订单已退款');
            }
            if($pay->refund_price){
                $pay->refund_price_ed=$pay->refund_price;
            }else{
                $pay->refund_price_ed=0;
            }
            $this->assign('pay',$pay);
            return $this->fetch();
        }
        $pay_id=$this->request->post('pay_id');
        $refund_price=$this->request->post('refund_price');
        $refund_cause=$this->request->post('refund_cause');
        $pay=PayUnion::where(['id'=>$pay_id,'parking_id'=>$this->parking->id])->find();
        if(!$pay){
            $this->error('支付记录不存在');
        }
        try{
            $pay->refund(floatval($refund_price),'管理员后台退款，'.$refund_cause);
        }catch (\Exception $e){
            $this->error('退款失败，'.$e->getMessage());
        }
        $this->success('退款成功');
    }

    #[Route('GET,JSON','refund-list')]
    public function refundList()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $this->model=new PayRefund();
        $this->relationField=['pay'];
        $where=[];
        $where[]=['pay_refund.parking_id','=',$this->parking->id];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->withJoin($with,'left')
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,JSON','settle')]
    public function settle()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $this->model=new ParkingDailyCashFlow();
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->where($where)
            ->order('date desc')
            ->paginate($limit);
        $rows=$list->items();
        $heji=$this->model->where($where)->field("0 as parking_id,'合计:' as date,sum(total_income) as total_income,sum(parking_income) as parking_income,sum(parking_monthly_income) as parking_monthly_income,sum(parking_stored_income) as parking_stored_income,sum(merch_recharge_income) as merch_recharge_income,sum(handling_fees) as handling_fees,sum(total_refund) as total_refund,sum(net_income) as net_income")->select();
        $rows[]=$heji[0];
        $result = ['total' => $list->total(), 'rows' => $rows];
        return json($result);
    }
}
