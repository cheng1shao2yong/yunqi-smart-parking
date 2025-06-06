<?php
declare (strict_types = 1);

namespace app\api\controller\merchant;

use app\common\library\ParkingAccount;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingMerchantUser;
use app\common\model\parking\ParkingRecordsCoupon;
use app\common\model\Qrcode;
use app\common\model\QrcodeScan;
use app\common\model\Third;
use app\common\service\ParkingService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Cache;

#[Group("merchant/common")]
class Common extends Base
{
    protected $noNeedRight='*';

    #[Get('is-admin')]
    public function isAdmin()
    {
        $merchAdmin=$this->auth->getMerchAdmin();
        $is_merchant=$merchAdmin?true:false;
        $this->success('',$is_merchant);
    }

    #[Get('read-qrcode')]
    public function readQrcode()
    {
        $qrcode_id=$this->request->get('qrcode_id');
        $platform=$this->request->get('platform');
        $qrcode=Qrcode::find($qrcode_id);
        if($qrcode->expiretime<time()){
            $this->error('二维码已过期');
        }
        [$parking_id,$merch_id,$coupon_id,$type]=explode(',',$qrcode->foreign_key);
        $parking=Parking::field('id,title,plate_begin')->find($parking_id);
        if($type=='dynamic'){
            $scan=QrcodeScan::where(['qrcode_id'=>$qrcode_id])->find();
            if($scan){
                $this->error('二维码已经被使用');
            }
        }
        $third=Third::where(['user_id'=>$this->auth->id,'platform'=>$platform])->find();
        $scan=QrcodeScan::create([
            'openid'=>$third->openid,
            'unionid'=>$third->unionid,
            'qrcode_id'=>$qrcode_id,
            'foreign_key'=>$qrcode->foreign_key,
            'type'=>$qrcode->type,
            'scantime'=>time(),
        ]);
        $merchAdmin=$this->auth->getMerchAdmin();
        $is_merchant=$merchAdmin?true:false;
        $this->success('',compact("is_merchant","parking","scan"));
    }

    #[Post('push-result')]
    public function pushResult()
    {
        $plate_number=$this->request->post('plate_number');
        $scan_id=$this->request->post('scan_id');
        $scan=QrcodeScan::withJoin(['qrcode'],'inner')->find($scan_id);
        if($scan->type!='merchant-static-qrcode' && $scan->type!='merchant-dynamic-qrcode'){
            $this->error('二维码类型错误');
        }
        if($scan->qrcode->expiretime<time()){
            $this->error('二维码已过期');
        }
        [$parking_id,$merch_id,$coupon_id,$type]=explode(',',$scan->foreign_key);
        $merchant=ParkingMerchant::find($merch_id);
        if($type=='static' && !$merchant->static_able){
            $this->error('该停车场未开启静态优惠券');
        }
        /* @var ParkingMerchantCoupon $coupon */
        $coupon=ParkingMerchantCoupon::find($coupon_id);
        if(!$coupon || $coupon->status!='normal'){
            $this->error('优惠券类型不存在或者被禁用');
        }
        try{
            [$records,$couponlist]=ParkingMerchantCouponList::given($merchant,$coupon,$plate_number);
            $couponlist->coupon=$coupon;
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        if($records){
            $list=ParkingRecordsCoupon::where(['records_id'=>$records->id])->select();
            $is=false;
            foreach ($list as $item){
                if($item->coupon_list_id==$couponlist->id){
                    $is=true;
                }
            }
            if($is){
                $service=ParkingService::newInstance([
                    'parking'=>$records->parking,
                    'plate_number'=>$records->plate_number,
                    'plate_type'=>$records->plate_type
                ]);
                $total_fee=$service->getTotalFee($records,time());
                [$activities_fee]=$service->getActivitiesFee($records,$total_fee);
                $records->total_fee=$total_fee;
                $needpay=formatNumber($total_fee-$activities_fee-$records->activities_fee-$records->pay_fee);
                $records->activities_fee=$records->activities_fee+$activities_fee;
                $records->need_pay_fee=($needpay>0)?$needpay:0;
            }else{
                $records='';
            }
        }
        $this->success('',compact('plate_number','records','couponlist'));
    }

    #[Post('login')]
    public function login()
    {
        $username=$this->request->post('username');
        $password=$this->request->post('password');
        $loginnumber=Cache::get('merchant-userid-'.$this->auth->id);
        if($loginnumber>5){
            $this->error('登录次数过多，请稍后再试');
        }
        $merchant=ParkingMerchant::where('username',$username)->find();
        if(!$merchant){
            $loginnumber++;
            Cache::set('merchant-userid-'.$this->auth->id,$loginnumber,10*60);
            $this->error('商户不存在');
        }
        if(md5(md5($password).$merchant->salt)!=$merchant->password){
            $loginnumber++;
            Cache::set('merchant-userid-'.$this->auth->id,$loginnumber,10*60);
            $this->error('账号或密码不正确');
        }
        if($merchant->status!='normal'){
            $this->error('商户已被禁用');
        }
        $this->auth->setMerchAdmin($merchant);
        $this->success('登录成功');
    }

    #[Get('merchants')]
    public function merchants()
    {
        $thirds=Third::where('user_id',$this->auth->id)->column('id');
        $merusers=ParkingMerchantUser::withJoin(['merch'=>function ($query) {
            $query->where('deletetime',null);
        }],'inner')->whereIn('third_id',$thirds)->select();
        $time=time();
        $merch_id=null;
        $merchAdmin=$this->auth->getMerchAdmin();
        if($merchAdmin && $time<=$merchAdmin['expire']){
            $merch_id=$merchAdmin['id'];
        }
        $merchants=[];
        foreach ($merusers as $meruser){
            $merchant=$meruser->merch;
            unset($merchant->password);
            unset($merchant->salt);
            if($meruser->merch_id==$merch_id){
                $merchant->active=1;
            }
            $merchants[]=$merchant;
        }
        $this->success('',$merchants);
    }

    #[Post('change')]
    public function change()
    {
        $id=$this->request->post('id');
        $thirds=Third::where('user_id',$this->auth->id)->column('id');
        $user=ParkingMerchantUser::where('merch_id',$id)->whereIn('third_id',$thirds)->find();
        if(!$user || !in_array($user->third_id,$thirds)){
            $this->error('没有权限');
        }
        $merchant=ParkingMerchant::find($user->merch_id);
        if(!$merchant){
            $this->error('商户不存在');
        }
        $this->auth->setMerchAdmin($merchant);
        $this->success('操作成功');
    }

    #[Get('logout')]
    public function logout()
    {
        $this->auth->updateToken(['merch_admin'=>null]);
        $this->success('操作成功');
    }
}
