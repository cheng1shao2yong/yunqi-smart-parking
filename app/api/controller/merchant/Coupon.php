<?php
declare (strict_types = 1);

namespace app\api\controller\merchant;

use app\common\model\parking\ParkingMerchantCouponList;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Db;

#[Group("merchant/coupon")]
class Coupon extends Base
{
    #[Get('list')]
    public function list()
    {
        $page=$this->request->get('page/d');
        $type=$this->request->get('type');
        $list=ParkingMerchantCouponList::with(['coupon'])
            ->where(function ($query){
                $keywords=$this->request->get('keywords');
                if($keywords){
                    $query->where('plate_number','like',"%{$keywords}%");
                }
            })
            ->where(['status'=>$type,'parking_id'=>$this->parking_id,'merch_id'=>$this->merch_id])
            ->order('id desc')
            ->limit(($page-1)*10,10)
            ->select();
        $this->success('',$list);
    }

    #[Get('detail')]
    public function detail()
    {
        $id=$this->request->get('id');
        $detail=ParkingMerchantCouponList::with(['coupon'])
            ->where(['parking_id'=>$this->parking_id,'merch_id'=>$this->merch_id,'id'=>$id])
            ->find();
        $prefix=getDbPrefix();
        $sql="select prc.coupon_list_id,prc.records_id,pr.entry_time,pr.exit_time,pr.activities_fee from {$prefix}parking_records pr,{$prefix}parking_records_coupon prc where pr.id=prc.records_id and prc.coupon_list_id={$id}";
        $records=Db::query($sql);
        $detail->records=$records;
        $this->success('',$detail);
    }

    #[Post('cancel')]
    public function cancel()
    {
        $id=$this->request->post('id');
        $detail=ParkingMerchantCouponList::where(['parking_id'=>$this->parking_id,'merch_id'=>$this->merch_id,'id'=>$id])->find();
        if($detail->status==1){
            $this->error('已使用，不能作废');
        }
        $detail->status=ParkingMerchantCouponList::STATUS('已作废');
        $detail->save();
        $this->success('操作成功');
    }
}
