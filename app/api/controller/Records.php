<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingRecords;
use app\common\model\PlateBinding;
use think\annotation\route\Get;
use think\annotation\route\Group;

#[Group("records")]
class Records extends Api
{
    #[Get('list')]
    public function list()
    {
        $plate_number=PlateBinding::getUserPlate($this->auth->id);
        $page=$this->request->get('page/d');
        $list=ParkingRecords::whereIn('plate_number',$plate_number)
            ->with(['coupon'])
            ->order('id desc')
            ->limit(($page-1)*10,10)
            ->select()
            ->each(function ($res){
                $res['coupon_txt']=$res->getCouponTxt();
            });
        $this->success('',$list);
    }

    #[Get('detail')]
    public function detail()
    {
        $id=$this->request->get('id');
        $plate_number=PlateBinding::getUserPlate($this->auth->id);
        $records=ParkingRecords::where(['id'=>$id])->find();
        if(!in_array($records->plate_number,$plate_number)){
            $this->error('没有权限');
        }
        $records->coupon=$records->getCouponTxt();
        $barrier=ParkingBarrier::where(['parking_id'=>$records->parking_id,'status'=>'normal'])->column('title','id');
        if($records->entry_barrier && isset($barrier[$records->entry_barrier])){
            $records->entry_barrier=$barrier[$records->entry_barrier];
        }
        if($records->exit_barrier && isset($barrier[$records->exit_barrier])){
            $records->exit_barrier=$barrier[$records->exit_barrier];
        }
        $this->success('',$records);
    }
}
