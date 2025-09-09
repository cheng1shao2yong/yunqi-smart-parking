<?php
declare (strict_types = 1);

namespace app\api\controller\merchant;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingMerchantSetting;
use app\common\model\parking\ParkingRecords;
use app\common\model\Qrcode;
use app\common\model\QrcodeScan;
use app\common\model\Third;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;

#[Group("merchant/index")]
class Index extends Base
{
    protected $noNeedLogin=['checkScan','qrcodeLogin','checkLogin','downloadImg'];

    #[Get('info')]
    public function info()
    {
        $user=$this->auth->userinfo();
        $merchant=ParkingMerchant::where('id',$this->merch_id)->find();
        unset($merchant->password);
        unset($merchant->salt);
        $parking=Parking::where('id',$merchant->parking_id)->field('id,title,plate_begin')->find();
        $this->success('',compact('user','merchant','parking'));
    }

    #[Post('search')]
    public function search()
    {
        $plate_number=$this->request->post('plate_number');
        $plate_number=strtoupper(trim($plate_number));
        $records=ParkingRecords::where(['plate_number'=>$plate_number,'parking_id'=>$this->parking_id])->order('id desc')->find();
        if(!$records || !in_array($records->status,[0,1,6])){
            $this->error('该车辆未在场内');
        }
        $records->records_time=time()-$records->entry_time;
        $this->success('',$records);
    }

    #[Get('download-img')]
    public function downloadImg()
    {
        $qrcode_id=$this->request->get('qrcode_id');
        $content=file_get_contents(root_path().'public/qrcode/'.$qrcode_id.'.jpg');
        //下载图片
        $filename=date('YmdHis').'.png';
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Type: Image/png");
        echo $content;
        exit;
    }

    #[Get('coupon')]
    public function coupon()
    {
        $type=$this->request->get('type');
        $merchant=ParkingMerchant::find($this->merch_id);
        if($merchant->status!='normal'){
            $this->error('该商户已经被禁用');
        }
        if($type=='static' && !$merchant->static_able){
            $this->error('该停车场未开启静态优惠券');
        }
        if($merchant->settle_type=='after' && -$merchant->balance>$merchant->allow_arrears){
            $this->error('账单超额，请先缴费');
        }
        if($merchant->settle_type=='before' && -$merchant->balance>$merchant->allow_arrears){
            $this->error('余额不足，请先充值');
        }
        $coupon=ParkingMerchantCoupon::where(['parking_id'=>$this->parking_id])->whereIn('id',explode(',',$merchant->coupon))->select();
        $setting=ParkingMerchantSetting::where(['parking_id'=>$this->parking_id,'merch_id'=>$this->merch_id])->select();
        foreach ($coupon as $k1=>$v1){
            foreach ($setting as $v2){
                if($v1->id==$v2->coupon_id){
                    $coupon[$k1]->tips=ParkingMerchantCouponList::getMerchantLastCoupon($merchant,$v2);
                }
            }
        }
        $this->success('',$coupon);
    }

    #[Get('check-scan/:type')]
    public function checkScan($type)
    {
        $qrcode_id=$this->request->get('qrcode_id');
        if($type=='dynamic'){
            $scan=QrcodeScan::withJoin(['third'])->where(['qrcode_id'=>$qrcode_id])->find();
        }
        if($type=='static'){
            $scan=QrcodeScan::withJoin(['third'])->where(['qrcode_id'=>$qrcode_id])->where('scantime','>',time()-5*60)->select();
        }
        if($scan){
            $this->success('',$scan);
        }else{
            $this->error();
        }
    }

    #[Get('qrcode')]
    public function qrcode()
    {
        $type=$this->request->get('type');
        $coupon_id=$this->request->get('coupon_id');
        $merchant=ParkingMerchant::find($this->merch_id);
        $coupon=ParkingMerchantCoupon::where(['id'=>$coupon_id,'parking_id'=>$this->parking_id])->find();
        if(!$coupon){
            $this->error('优惠券类型不存在');
        }
        if($type=='static' && !$merchant->static_able){
            $this->error('该商户未开启静态优惠券');
        }
        try{
            ParkingMerchantCouponList::checkMerchantSendCoupon($merchant,$coupon);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        if($type=='dynamic'){
            $expiretime=5*60;
        }
        if($type=='static'){
            $expiretime=$merchant->static_expire*24*3600;
        }
        $qrcodeType='merchant-'.$type.'-qrcode';
        $foreign_key=$this->parking_id.','.$this->merch_id.','.$coupon_id.','.$type;
        $set_mpapp_scan=$coupon->subscribe_mpapp?0:1;
        $qrcode=Qrcode::createQrcode($qrcodeType,$foreign_key,$expiretime,$set_mpapp_scan);
        if($coupon->subscribe_mpapp){
            $config=[
                'appid'=>site_config("addons.uniapp_mpapp_id"),
                'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
            ];
            $wechat=new \WeChat\Qrcode($config);
            $ticket = $wechat->create($qrcode->id,$expiretime)['ticket'];
            $url=$wechat->url($ticket);
            $filepath=root_path().'public'.DS.'qrcode'.DS.$qrcode->id.'.jpg';
            $data=file_get_contents($url);
            file_put_contents($filepath,$data);
            $url=$this->request->domain().'/qrcode/'.$qrcode->id.'.jpg';
        }else{
            $config=[
                'appid'=>site_config("addons.uniapp_miniapp_id"),
                'appsecret'=>site_config("addons.uniapp_miniapp_secret"),
            ];
            $mini=new \WeMini\Qrcode($config);
            $path='pages/merchant/plate';
            $data=$mini->createMiniScene($qrcode->id,$path);
            $filepath=root_path().'public'.DS.'qrcode'.DS.$qrcode->id.'.jpg';
            file_put_contents($filepath,$data);
            $url=$this->request->domain().'/qrcode/'.$qrcode->id.'.jpg';
        }
        $this->success('',compact('coupon','url','qrcode'));
    }

    #[Post('expire')]
    public function expire()
    {
        $coupon_id=$this->request->post('coupon_id');
        $qrcode_id=$this->request->post('qrcode_id');
        $key=md5(Qrcode::TYPE('商家固定优惠券').$this->parking_id.','.$this->merch_id.','.$coupon_id.',static');
        $qrcode=Qrcode::find($qrcode_id);
        if($qrcode->key!=$key){
            $this->error('二维码不存在');
        }
        $qrcode->expiretime=time();
        $qrcode->save();
        $this->success('操作成功');
    }

    #[Get('qrcode-login')]
    public function qrcodeLogin()
    {
        $config=[
            'appid'=>site_config("addons.uniapp_mpapp_id"),
            'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
        ];
        $foreign_key=$this->request->get('foreign_key');
        $qrcode=Qrcode::createQrcode(Qrcode::TYPE('商户PC扫码登录'),$foreign_key,5*60);
        $wechat=new \WeChat\Qrcode($config);
        $ticket = $wechat->create($qrcode->id,5*60)['ticket'];
        $url=$wechat->url($ticket);
        $this->success('',$url);
    }

    #[Get('check-login')]
    public function checkLogin()
    {
        $foreign_key=$this->request->get('foreign_key');
        $scan=QrcodeScan::where(['type'=>'merchant-login','foreign_key'=>$foreign_key])->order('id desc')->find();
        if($scan){
            $third=Third::where(['platform'=>Third::PLATFORM('微信公众号'),'openid'=>$scan->openid])->find();
            if($third){
                $this->auth->loginByThirdPlatform(Third::PLATFORM('微信公众号'),$third);
                $this->success('',['token'=>$this->auth->getToken()]);
            }
        }
        $this->error();
    }
}
