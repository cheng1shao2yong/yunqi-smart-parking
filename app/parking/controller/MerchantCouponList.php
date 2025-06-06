<?php
declare (strict_types = 1);

namespace app\parking\controller;

use app\common\controller\ParkingBase;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantCouponList;
use think\annotation\route\Group;
use app\parking\traits\Actions;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("merchant-coupon-list")]
class MerchantCouponList extends ParkingBase
{
    use Actions{
        add as _add;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = new ParkingMerchantCouponList();
        $this->assign('coupon',ParkingMerchantCoupon::where(['parking_id'=>$this->parking->id])->column('title','id'));
        $this->assign('merchList',ParkingMerchant::where(['parking_id'=>$this->parking->id,'is_self'=>1,'status'=>'normal'])->column('merch_name','id'));
        $this->assign('couponType',ParkingMerchantCoupon::COUPON_TYPE);
        $this->assign('status',ParkingMerchantCouponList::STATUS);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_merchant_coupon_list.parking_id','=',$this->parking->id];
        if($this->request->post('selectpage')){
            return $this->selectpage($where);
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->withJoin(['merch'=>function ($query) {
                $query->where('deletetime',null);
            },'coupon'=>function ($query) {
                $query->where('deletetime',null);
            }],'inner')
            ->where($where)
            ->order($order)
            ->paginate($limit)
            ->each(function ($item) {
                $item['expiretime']=date('Y-m-d H:i',$item['expiretime']);
            });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('POST','del')]
    public function del()
    {
        $ids=$this->request->post('ids');
        $coupon_list=ParkingMerchantCouponList::where(['parking_id'=>$this->parking->id])
            ->whereIn('status',[0,2])
            ->whereIn('id',$ids)
            ->select();
        try{
            Db::startTrans();
            foreach ($coupon_list as $list){
                $list->status=ParkingMerchantCouponList::STATUS('已作废');
                $list->save();
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('作废成功');
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if ($this->request->isPost()) {
            $plate_number=trim(strtoupper($this->request->post('row.plate_number')));
            $merch_id=$this->request->post('row.merch_id');
            $merchant=ParkingMerchant::find($merch_id);
            if(!is_car_license($plate_number)){
                $this->error('车牌号格式错误');
            }
            $coupon_id=$this->request->post('row.coupon_id');
            $coupon=ParkingMerchantCoupon::where(['id'=>$coupon_id,'parking_id'=>$this->parking->id])->find();
            if(!$coupon || $coupon->status!='normal'){
                $this->error('优惠券不存在或者被禁用');
            }
            try{
                ParkingMerchantCouponList::given($merchant,$coupon,$plate_number);
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
            $this->success('发券成功');
        }
        return $this->_add();
    }
}