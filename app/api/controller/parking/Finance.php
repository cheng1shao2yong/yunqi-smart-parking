<?php
declare (strict_types = 1);

namespace app\api\controller\parking;

use app\common\model\parking\ParkingInvoice;
use app\common\model\PayUnion;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Db;

#[Group("parking/finance")]
class Finance extends Base
{
    #[Get('list')]
    public function list()
    {
        $page=$this->request->get('page/d');
        $list=PayUnion::where(function ($query){
            $pay_type=$this->request->get('pay_type');
            $plate_number=$this->request->get('plate_number');
            $transaction_id=$this->request->get('transaction_id');
            $out_trade_no=$this->request->get('out_trade_no');
            $starttime=$this->request->get('starttime');
            $endtime=$this->request->get('endtime');
            if($pay_type=='underline'){
                $query->where('yun_pay_union.pay_type',$pay_type);
            }else{
                $query->where('yun_pay_union.pay_type','<>','underline');
            }
            $query->where('yun_pay_union.parking_id',$this->parking_id);
            $query->where('yun_pay_union.pay_status',1);
            if($plate_number){
                $query->where('yun_pay_union.order_type','parking');
                $query->where('yun_pay_union.detail','like','%'.$plate_number.'%停车缴费');
            }
            if($transaction_id){
                $query->where('yun_pay_union.transaction_id','=',$transaction_id);
            }
            if($out_trade_no){
                $query->where('yun_pay_union.out_trade_no','=',$out_trade_no);
            }
            if($starttime){
                $starttime=strtotime($starttime.' 00:00:00');
            }
            if($endtime){
                $endtime=strtotime($endtime.' 23:59:59');
            }
            if($starttime && $endtime){
                $query->whereBetween('yun_pay_union.createtime',[$starttime,$endtime]);
            }elseif($starttime){
                $query->where('yun_pay_union.createtime','>=',$starttime);
            }elseif($endtime){
                $query->where('yun_pay_union.createtime','<=',$endtime);
            }
        })
        ->alias('yun_pay_union')
        ->field('
            yun_pay_union.*,
            parking_records_pay.records_id
        ')
        ->leftJoin('parking_records_pay','parking_records_pay.pay_id=yun_pay_union.id')
        ->order('yun_pay_union.id desc')
        ->limit(($page-1)*10,10)
        ->select();
        $this->success('',$list);
    }

    #[Get('settle')]
    public function settle()
    {
        $page=$this->request->get('page/d');
        $offset=($page-1)*10;
        $datebetween='';
        $starttime=$this->request->get('starttime');
        $endtime=$this->request->get('endtime');
        if($starttime && $endtime){
            $datebetween='and (date between "'.$starttime.'" and "'.$endtime.'")';
        }elseif($starttime){
            $datebetween='and date >= "'.$starttime.'"';
        }elseif($endtime){
            $datebetween='and date <= "'.$endtime.'"';
        }
        $prefix=getDbPrefix();
        $sql="select * from {$prefix}parking_daily_cash_flow where parking_id={$this->parking_id} {$datebetween} order by date desc limit {$offset},10";
        $list=Db::query($sql);
        $this->success('',$list);
    }

    #[Get('invoice')]
    public function invoice()
    {
        $page=$this->request->get('page/d');
        $list=ParkingInvoice::where(function ($query){
            $status=$this->request->get('status');
            $starttime=$this->request->get('starttime');
            $endtime=$this->request->get('endtime');
            $query->where('parking_id',$this->parking_id);
            $query->where('status',$status);
            if($starttime){
                $starttime=strtotime($starttime.' 00:00:00');
            }
            if($endtime){
                $endtime=strtotime($endtime.' 23:59:59');
            }
            if($starttime && $endtime){
                $query->whereBetween('createtime',[$starttime,$endtime]);
            }elseif($starttime){
                $query->where('createtime','>=',$starttime);
            }elseif($endtime){
                $query->where('createtime','<=',$endtime);
            }
        })
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select();
        $this->success('',$list);
    }

    #[Post('refund')]
    public function refund()
    {
        $pay_id=$this->request->post('pay_id');
        $refund_price=$this->request->post('refund_price');
        $refund_cause=$this->request->post('refund_cause');
        $pay=PayUnion::where(['id'=>$pay_id,'parking_id'=>$this->parking_id])->find();
        if(!$pay){
            $this->error('支付记录不存在');
        }
        try{
            $pay->refund(floatval($refund_price),'管理员手机退款，'.$refund_cause);
        }catch (\Exception $e){
            $this->error('退款失败，'.$e->getMessage());
        }
        $this->success('退款成功');
    }
}
