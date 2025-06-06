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

namespace app\admin\controller\finance;

use app\admin\controller\parking\Base;
use app\admin\traits\Actions;
use app\common\controller\Backend;
use app\common\library\Etc;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsDetail;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\PayUnion;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("finance/pay")]
class Pay extends Backend
{
    use Actions{
        download as _download;
    }

    public function _initialize()
    {
        parent::_initialize();
        $this->assign('orderType',PayUnion::ORDER_TYPE);
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
        $where[]=['yun_pay_union.pay_status','=',1];
        $plate_number=$this->filter('plate_number');
        if($plate_number){
            $where[]=['yun_pay_union.order_type','=',PayUnion::ORDER_TYPE('停车缴费')];
            $where[]=['yun_pay_union.detail','like',$plate_number.'%'];
        }
        if($pay_type=='underline'){
            $where[]=['yun_pay_union.pay_type','=',PayUnion::PAYTYPE('线下支付')];
        }else{
            $where[]=['yun_pay_union.pay_type','in',[PayUnion::PAYTYPE('微信小程序支付'),PayUnion::PAYTYPE('微信H5支付'),PayUnion::PAYTYPE('支付宝支付'),PayUnion::PAYTYPE('付款码支付')]];
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['park'])
            ->alias('yun_pay_union')
            ->field(' 
                yun_pay_union.*,
                parking_records_pay.records_id
            ')
            ->leftJoin('parking_records_pay','parking_records_pay.pay_id=yun_pay_union.id')
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

    #[Route('GET,POST','detail')]
    public function detail()
    {
        $ids=$this->request->get('ids');
        $records=ParkingRecords::where(['id'=>$ids])->find();
        $detail=ParkingRecordsDetail::where(['records_id'=>$ids])->select();
        $pay=ParkingRecordsPay::with(['unionpay'])->whereNotNull('pay_id')->where(['records_id'=>$ids])->select();
        $this->assign('records',$records);
        $this->assign('parking_detail',$detail);
        $this->assign('pay_detail',$pay);
        return $this->fetch();
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

    #[Route('POST,GET','refund')]
    public function refund()
    {
        if (false === $this->request->isPost()) {
            $pay_id=$this->request->get('ids');
            $pay=PayUnion::where(['id'=>$pay_id])->find();
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
        $pay=PayUnion::where(['id'=>$pay_id])->find();
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
}
