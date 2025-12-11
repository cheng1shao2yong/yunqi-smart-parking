<?php
declare (strict_types = 1);

namespace app\api\controller\merchant;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantLog;
use app\common\model\PayUnion;
use app\common\service\PayService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;

#[Group("merchant/bill")]
class Bill extends Base
{
    #[Get('list')]
    public function list()
    {
        $page=$this->request->get('page/d');
        $platform=$this->request->get('platform');
        $rows=10;
        if($platform=='PC'){
            $rows=20;
        }
        $list=ParkingMerchantLog::with(['payunion','records','merch'])
            ->where(function ($query){
                $type=$this->request->get('type');
                $starttime=$this->request->get('starttime');
                $endtime=$this->request->get('endtime');
                $plate_number=$this->request->get('plate_number');
                $query->where('parking_id','=',$this->parking_id);
                $query->where('merch_id','=',$this->merch_id);
                if($type=='bill'){
                    $query->where('log_type','records');
                }
                if($type=='recharge'){
                    $query->where('pay_id','<>',null);
                }
                if($plate_number){
                    $plate_number=strtoupper(trim($plate_number));
                    $query->whereRaw('records_id in (select id from '.getDbPrefix().'parking_records where plate_number like "%'.$plate_number.'%")');
                }
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
            ->limit(($page-1)*$rows,$rows)
            ->select();
        $this->success('',$list);
    }

    #[Post('recharge')]
    public function recharge()
    {
        $pay_price=$this->request->post('money/f');
        if($pay_price<=0){
            $this->error('充值金额必须大于0');
        }
        $pay_platform=$this->request->post('pay_platform','wechat-miniapp');
        $parking=Parking::find($this->parking_id);
        $merch=ParkingMerchant::find($this->merch_id);
        $service=PayService::newInstance([
            'pay_type_handle'=>$parking->pay_type_handle,
            'user_id'=>$this->auth->id,
            'parking_id'=>$parking->id,
            'sub_merch_config'=>$parking->sub_merch_config,
            'sub_merch_no'=>$parking->sub_merch_no,
            'sub_merch_key'=>$parking->sub_merch_key,
            'split_merch_no'=>$parking->split_merch_no,
            'persent'=>$parking->parking_merch_persent,
            'pay_price'=>$pay_price,
            'order_type'=>PayUnion::ORDER_TYPE('商户充值'),
            'order_body'=>$merch->merch_name.'充值'.$pay_price.'元',
            'attach'=>json_encode([
                'parking_id'=>$this->parking_id,
                'merch_id'=>$this->merch_id
            ])
        ]);
        try{
            if($pay_platform=='wechat-miniapp'){
                $r=$service->wechatMiniappPay();
            }
            if($pay_platform=='mp-alipay'){
                $r=$service->mpAlipay();
            }
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('',$r);
    }
}
