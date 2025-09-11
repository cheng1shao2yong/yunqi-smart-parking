<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingInvoice;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingMonthlyRecharge;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRecovery;
use app\common\model\parking\ParkingScreen;
use app\common\model\parking\ParkingStoredLog;
use app\common\model\parking\ParkingTemporary;
use app\common\model\PayUnion;
use app\common\service\PayService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Cache;
use think\facade\Db;

#[Group("orders")]
class Orders extends Api
{
    #[Get('list')]
    public function list()
    {
        $page=$this->request->get('page/d');
        $limit=($page-1)*10;
        $type=$this->request->get('type');
        $list=PayUnion::where(['user_id'=>$this->auth->id,'order_type'=>$type,'pay_status'=>1])->limit($limit,10)->select();
        foreach ($list as &$item){
            $attach=json_decode($item['attach'],true);
            $item['parking_title']=$attach['parking_title'];
            $item['plate_number']=isset($attach['plate_number'])?$attach['plate_number']:'';
        }
        $this->success('',$list);
    }

    #[Get('invoice')]
    public function invoice()
    {
        $pay_id=$this->request->get('pay_id');
        if($pay_id){
            $payunion=PayUnion::where([
                'user_id'=>$this->auth->id,
                'id'=>$pay_id,
                'invoicing'=>0,
                'pay_status'=>1,
                'pay_type'=>'wechat-miniapp',
                'refund_price'=>null
            ])->field('id,pay_price,pay_time,order_type,transaction_id,pay_type')->select();
        }else{
            $page=$this->request->get('page/d');
            $limit=($page-1)*10;
            $payunion=PayUnion::where([
                'user_id'=>$this->auth->id,
                'invoicing'=>0,
                'pay_status'=>1,
                'pay_type'=>'wechat-miniapp',
                'refund_price'=>null
            ])->field('id,pay_price,pay_time,order_type,transaction_id,pay_type')
            ->limit($limit,10)
            ->order('id desc')
            ->select();
        }
        $parking=[];
        $parking_monthly=[];
        $parking_stored=[];
        $merch_recharge=[];
        foreach ($payunion as $item){
            if($item->order_type=='parking'){
                $parking[]=$item->id;
            }
            if($item->order_type=='parking_monthly'){
                $parking_monthly[]=$item->id;
            }
            if($item->order_type=='parking_stored'){
                $parking_stored[]=$item->id;
            }
            if($item->order_type=='merch_recharge'){
                $merch_recharge[]=$item->id;
            }
        }
        $sql='';
        $prefix=getDbPrefix();
        if(!empty($parking)){
            $parking=implode(',',$parking);
            $sql.="select prp.parking_id,prp.pay_id,p.title as parking_title,ps.invoice_entity,ps.invoice_type,ps.phone as parking_phone FROM {$prefix}parking_records_pay prp,{$prefix}parking p,{$prefix}parking_setting ps where prp.parking_id=p.id and ps.parking_id=p.id and prp.pay_id in ({$parking})";
        }
        if(!empty($parking_monthly)){
            if($sql){
                $sql.=" union all ";
            }
            $parking_monthly=implode(',',$parking_monthly);
            $sql.="select pmr.parking_id,pmr.pay_id,p.title as parking_title,ps.invoice_entity,ps.invoice_type,ps.phone as parking_phone FROM {$prefix}parking_monthly_recharge pmr,{$prefix}parking p,{$prefix}parking_setting ps where pmr.parking_id=p.id and ps.parking_id=p.id and pmr.pay_id in ({$parking_monthly})";
        }
        if(!empty($parking_stored)){
            if($sql){
                $sql.=" union all ";
            }
            $parking_stored=implode(',',$parking_stored);
            $sql.="select psl.parking_id,psl.pay_id,p.title as parking_title,ps.invoice_entity,ps.invoice_type,ps.phone as parking_phone FROM {$prefix}parking_stored_log psl,{$prefix}parking p,{$prefix}parking_setting ps where psl.parking_id=p.id and ps.parking_id=p.id and psl.pay_id in ({$parking_stored})";
        }
        if(!empty($merch_recharge)){
            if($sql){
                $sql.=" union all ";
            }
            $merch_recharge=implode(',',$merch_recharge);
            $sql.="select pml.parking_id,pml.pay_id,p.title as parking_title,ps.invoice_entity,ps.invoice_type,ps.phone as parking_phone FROM {$prefix}parking_merchant_log pml,{$prefix}parking p,{$prefix}parking_setting ps where pml.parking_id=p.id and ps.parking_id=p.id and pml.pay_id in ({$merch_recharge})";
        }
        if($sql){
            $parking=Db::query($sql);
            foreach ($payunion as $item1){
                foreach ($parking as $item2){
                    if($item1->id==$item2['pay_id']){
                        $item1->checked=false;
                        $item1->parking_title=$item2['parking_title'];
                        $item1->invoice_type=$item2['invoice_type'];
                        $item1->parking_phone=$item2['parking_phone'];
                        $item1->invoice_entity=$item2['invoice_entity'];
                        $item1->parking_id=$item2['parking_id'];
                    }
                }
            }
        }
        $this->success('',$payunion);
    }

    #[Get('invoice-list')]
    public function invoiceList()
    {
        $page=$this->request->get('page/d');
        $type=$this->request->get('type');
        $limit=($page-1)*10;
        $list=ParkingInvoice::with(['setting'])->where(['user_id'=>$this->auth->id,'status'=>$type])->limit($limit,10)->order('id desc')->select()->each(function (&$item){
            $item->parking=$item->setting;
        });
        $this->success('',$list);
    }

    //开取发票
    #[Post('apply-invoice')]
    public function applyInvoice()
    {
        $postdata=$this->request->post();
        $postdata['user_id']=$this->auth->id;
        $count=PayUnion::whereIn('id',$postdata['pay_id'])->where(['user_id'=>$this->auth->id,'invoicing'=>0])->count();
        $minus=$count-count($postdata['pay_id']);
        if($minus>0){
            $this->error('有'.$minus.'个订单已经申请过开票，请重新选择');
            return;
        }
        $total_price=PayUnion::whereIn('id',$postdata['pay_id'])->where(['user_id'=>$this->auth->id,'invoicing'=>0])->sum('pay_price');
        $postdata['pay_id']=implode(',',$postdata['pay_id']);
        $postdata['total_price']=$total_price;
        $postdata['title']=Parking::where('id',$postdata['parking_id'])->value('title');
        $parking=Parking::cache('parking_'.$postdata['parking_id'],24*3600)->withJoin(['setting'])->find($postdata['parking_id']);
        $postdata['invoice_send']=$parking->setting->invoice_type;
        try{
            Db::startTrans();
            PayUnion::whereIn('id',$postdata['pay_id'])->where('user_id',$this->auth->id)->update(['invoicing'=>1]);
            (new ParkingInvoice())->save($postdata);
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error('提交失败');
        }
        $this->success('提交成功，请等候停车场处理开票');
    }

    #[Get('detail')]
    public function detail()
    {
        $out_trade_no=$this->request->get('out_trade_no');
        while(true){
            $union=PayUnion::where(['out_trade_no'=>$out_trade_no,'user_id'=>$this->auth->id])->find();
            if(!$union){
                $this->error('订单不存在');
                return;
            }
            if($union->pay_status==1){
                break;
            }
            sleep(1);
        }
        $attach=json_decode($union->attach,true);
        $parking=[
            'id'=>$union->parking_id,
            'title'=>$attach['parking_title']
        ];
        if($union->order_type==PayUnion::ORDER_TYPE('停车缴费')){
            $records=ParkingRecords::with(['detail'])->where('id',$attach['records_id'])->find();
            if(!$records){
                $this->error('停车记录已经被删除');
            }
            $records->parking_time=$records->exit_time-$records->entry_time;
            if($records->coupon_id){
                $coupon=ParkingMerchantCouponList::with(['coupon'])->where('id',$records->coupon_id)->find();
                $records->coupon=$coupon['coupon']->title;
            }
            $this->success('',compact('union','records','parking'));
        }
        if($union->order_type==PayUnion::ORDER_TYPE('停车月租缴费')){
            $recharge=ParkingMonthlyRecharge::where('pay_id',$union->id)->find();
            $plate_number=Db::name('parking_plate')->where('cars_id',$recharge->cars_id)->column('plate_number');
            $recharge->plate_number=implode(',',$plate_number);
            $this->success('',compact('union','recharge','parking'));
        }
        if($union->order_type==PayUnion::ORDER_TYPE('停车储值卡充值')){
            $stored=ParkingStoredLog::where('pay_id',$union->id)->find();
            $plate_number=Db::name('parking_plate')->where('cars_id',$stored->cars_id)->column('plate_number');
            $stored->plate_number=implode(',',$plate_number);
            $this->success('',compact('union','stored','parking'));
        }
    }

    #[Get('recovery-list')]
    public function recoveryList()
    {
        $plate_number=$this->request->get('plate_number');
        $recordslist=ParkingRecovery::with(['records'])->where(['plate_number'=>$plate_number,'pay_id'=>null])->select();
        $this->success('',$recordslist);
    }

    #[Post('recovery-pay')]
    public function recoveryPay()
    {
        $pay_type=$this->request->post('pay_type');
        $recovery_id=$this->request->post('recovery_id');
        $barrier_id=$this->request->post('barrier_id');
        $plate_number=$this->request->post('plate_number');
        $recoverylist=ParkingRecovery::whereIn('id',$recovery_id)->select();
        $totalfee=0;
        $parking_id=false;
        foreach ($recoverylist as $recovery) {
            if($recovery->pay_id){
                $this->error('有订单已经支付，请勿重复支付');
            }
            if($parking_id && $parking_id!=$recovery->parking_id){
                $this->error('请勿选择不同停车场的订单');
            }
            $parking_id=$recovery->parking_id;
            $totalfee+=$recovery->total_fee;
        }
        $parking=Parking::cache('parking_'.$parking_id,24*3600)->withJoin(['setting'])->find($parking_id);
        $totalfee=round($totalfee,2);
        $service=PayService::newInstance([
            'pay_type_handle'=>$parking->pay_type_handle,
            'user_id'=>$this->auth->id,
            'parking_id'=>$parking->id,
            'sub_merch_no'=>$parking->sub_merch_no,
            'split_merch_no'=>$parking->split_merch_no,
            'persent'=>$parking->parking_records_persent,
            'pay_price'=>$totalfee,
            'order_type'=>PayUnion::ORDER_TYPE('逃费追缴'),
            'order_body'=>$plate_number.'补缴欠费'.$totalfee.'元',
            'attach'=>json_encode([
                'recovery_id'=>$recovery_id,
                'plate_number'=>$plate_number,
                'barrier_id'=>$barrier_id
            ],JSON_UNESCAPED_UNICODE)
        ]);
        if($pay_type=='wechat-miniapp'){
            $r=$service->wechatMiniappPay();
        }
        if($pay_type=='mp-alipay'){
            $r=$service->mpAlipay();
        }
        if($barrier_id){
            $barrier=ParkingBarrier::find($barrier_id);
            ParkingScreen::sendGreenMessage($barrier,'正在支付欠费：'.$totalfee.'元');
        }
        $this->success('',$r);
    }
}
