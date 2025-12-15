<?php
declare (strict_types = 1);

namespace app\api\controller\sentrybox;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecovery;
use app\common\model\parking\ParkingSentryboxOperate;
use app\common\model\parking\ParkingTrigger;
use app\common\service\barrier\Utils;
use app\common\controller\BaseController;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingScreen;
use app\common\model\parking\ParkingSentrybox;
use app\common\service\msg\WechatMsg;
use app\common\service\ParkingService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\exception\HttpResponseException;
use think\facade\Db;
use think\Response;

function getParkingIds(int $parking_id){
    $r=[$parking_id];
    $parking=Parking::cache('parking_'.$parking_id,24*3600)->withJoin(['setting'])->find($parking_id);
    if($parking->property_id){
        return Parking::where('property_id',$parking->property_id)->column('id');
    }else{
        return $r;
    }
}

#[Group("sentrybox/index")]
class Index extends BaseController
{

    private $sentrybox;

    public function _initialize()
    {
        parent::_initialize();
        if($this->request->action()=='login' || $this->request->action()=='download'){
            return;
        }
        $token=$this->request->header('token');
        if(!$token){
            $response = Response::create('没有操作权限!','html', 401);
            throw new HttpResponseException($response);
        }
        $sentrybox=ParkingSentrybox::where('token',$token)->find();
        if(!$sentrybox){
            $response = Response::create('没有操作权限!','html', 401);
            throw new HttpResponseException($response);
        }
        $this->sentrybox=$sentrybox;
    }

    #[Post('login')]
    public function login()
    {
        $uniqid=$this->request->post('uniqid');
        $password=$this->request->post('password');
        $sentrybox=ParkingSentrybox::where('uniqid',$uniqid)->find();
        if(!$sentrybox){
            $this->error('找不到岗亭!');
        }
        if($sentrybox->password!=$password){
            $this->error('密码错误!');
        }
        $token=md5(uniqid().time());
        $sentrybox->token=$token;
        $sentrybox->save();
        $this->success('',['token'=>$token]);
    }

    #[Get('config')]
    public function config()
    {
        $screen=ParkingBarrier::with(['fuji'=>function ($query) {
            $query->where('status','normal');
        }])->whereIn('id',$this->sentrybox->barriers)->where('status','normal')->select();
        foreach ($screen as &$item){
            if($item->local_ip){
                $item->url='ws://'.$item->local_ip.':9080/ws.flv?channel=0';
            }else{
                $item->url='';
            }
            foreach ($item->fuji as &$fuji){
                if($fuji->local_ip){
                    $fuji->url='ws://'.$fuji->local_ip.':9080/ws.flv?channel=0';
                }else{
                    $fuji->url='';
                }
            }
        }
        $mqttConfig=site_config('mqtt');
        $clientId='mqtt-receive-sentrybox-'.$this->sentrybox->parking_id.'-'.$this->sentrybox->id;
        $tips=$this->sentrybox->remark;
        $parking=Parking::cache('parking_'.$this->sentrybox->parking_id,24*3600)->withJoin(['setting'])->find($this->sentrybox->parking_id);
        $parking->sentrybox=$this->sentrybox->title;
        $parking->open_set=$this->sentrybox->open_set;
        $this->success('',compact('parking','screen','mqttConfig','clientId','tips'));
    }
    #[Get('client')]
    public function client()
    {
        $update=[
            'v1.0.0'=>false,
            'v1.0.1'=>false,
            'v1.0.2'=>false,
            'v1.0.3'=>false
        ];
        $screen=ParkingBarrier::with(['fuji'=>function ($query) {
            $query->where('status','normal');
        }])->whereIn('id',$this->sentrybox->barriers)
            ->where('status','normal')
            ->field('id,pid,parking_id,title,barrier_type,local_ip,serialno,camera')
            ->select();
        foreach ($screen as &$item){
            if($item->local_ip){
                $item->url='rtsp://'.$item->local_ip.':8557/h264';
            }else{
                $item->url='';
            }
            foreach ($item->fuji as &$fuji){
                if($fuji->local_ip){
                    $fuji->url='rtsp://'.$fuji->local_ip.':8557/h264';
                }else{
                    $fuji->url='';
                }
            }
        }
        $mqttConfig=site_config('mqtt');
        $md5=md5($this->sentrybox->parking_id.'-'.$this->sentrybox->id.'-'.rand(0,100));
        $mqttConfig['mqtt_client_id']='mqtt-receive-sentrybox-'.$md5;
        $tips=$this->sentrybox->remark;
        $hide_window=$this->sentrybox->hide_window;
        $title=Parking::where('id',$this->sentrybox->parking_id)->value("title")." - ".$this->sentrybox->title;
        $open_set=$this->sentrybox->open_set;
        $operator=$this->sentrybox->operator;
        $show_operate=false;
        if($operator && count($operator)>0){
            $operate=ParkingSentryboxOperate::where(['parking_id'=>$this->sentrybox->parking_id,'sentrybox_id'=>$this->sentrybox->id])->order('id desc')->find();
            if(!$operate){
                $show_operate=true;
            }
        }
        $coupon=[];
        if($this->sentrybox->merch_id){
            $couponids=ParkingMerchant::where('id',$this->sentrybox->merch_id)->value("coupon");
            $coupon=ParkingMerchantCoupon::whereIn('id',$couponids)->field('id,title,coupon_type')->select();
        }
        $this->success('',compact('title','open_set','coupon','operator','show_operate','screen','mqttConfig','tips','update','hide_window'));
    }

    #[Post('open')]
    public function open()
    {
        $barrier_id=$this->request->post('barrier_id');
        $trigger_id=$this->request->post('trigger_id');
        $recovery_id=$this->request->post('recovery_id');
        $records_id=$this->request->post('records_id');
        $remark=$this->request->post('remark');
        $diy_remark=$this->request->post('diy_remark');
        if($remark=='填写原因'){
            $remark=$diy_remark;
        }
        $parking_ids=getParkingIds($this->sentrybox->parking_id);
        $barrier=ParkingBarrier::where(['id'=>$barrier_id])->whereIn('parking_id',$parking_ids)->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        if($trigger_id){
            $trigger=ParkingTrigger::find($trigger_id);
            if(!$trigger || $trigger->createtime < time()-5*60){
                $this->error('时间超过5分钟，请重新识别');
            }
            if($trigger->plate_number=='_无_' || $trigger->plate_number=='无牌车'){
                $trigger_id=false;
            }
        }
        if($recovery_id){
            $recovery=ParkingRecovery::find($recovery_id);
            $trigger=ParkingTrigger::where('plate_number',$recovery->plate_number)->order('id desc')->find();
        }
        if($trigger_id || $recovery_id){
            $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
            $theadkey=md5($barrier->parking_id.$barrier->id.$trigger->plate_number);
            $service=false;
            try{
                $service = ParkingService::newInstance([
                    'parking' => $parking,
                    'barrier'=> $barrier,
                    'plate_number' => $trigger->plate_number,
                    'plate_type' => $trigger->plate_type,
                    'photo' => $trigger->image,
                    'records_type'=>ParkingRecords::RECORDSTYPE('人工确认'),
                    'entry_time' =>($barrier->barrier_type=='entry')?time():null,
                    'exit_time' =>($barrier->barrier_type=='exit')?time():null,
                    'remark'=>$remark??null
                ],$theadkey);
                //外场入场
                if($barrier->barrier_type=='entry' && $barrier->trigger_type=='outfield'){
                    $isopen=$service->entry();
                    if($isopen){
                        WechatMsg::entry($parking,$trigger->plate_number);
                    }
                }
                //内场入场
                if($barrier->barrier_type=='entry' && $barrier->trigger_type=='infield'){
                    $service->infieldEntry();
                }
                //外场出场
                if($barrier->barrier_type=='exit' && $barrier->trigger_type=='outfield'){
                    $isopen=$service->exit();
                    //微信出场通知
                    if($isopen){
                        WechatMsg::exit($parking,$trigger->plate_number);
                    }
                }
                //内场出场
                if($barrier->barrier_type=='exit' && $barrier->trigger_type=='infield'){
                    $service->infieldExit();
                }
                $service->destroy();
                ParkingScreen::sendBlackMessage($barrier,'岗亭-'.$this->sentrybox->title.'，人工确认开闸');
            }catch (\Exception $e){
                if($service){
                    $service->destroy();
                }
                $message=$e->getMessage();
                Utils::openGateException($barrier,$message);
            }
        }else{
            try{
                $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
                ParkingScreen::open($parking,$barrier,$records_id,'岗亭-'.$this->sentrybox->title,$remark);
                ParkingScreen::sendBlackMessage($barrier,'岗亭-'.$this->sentrybox->title.'，手动开闸');
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
        }
        $this->success('开闸成功');
    }

    #[Post('close')]
    public function close()
    {
        $barrier_id=$this->request->post('barrier_id');
        $parking_ids=getParkingIds($this->sentrybox->parking_id);
        $barrier=ParkingBarrier::where(['id'=>$barrier_id])->whereIn('parking_id',$parking_ids)->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        Utils::close($barrier,ParkingRecords::RECORDSTYPE('人工确认'),function ($res) use ($barrier){
            if($res){
                ParkingScreen::sendBlackMessage($barrier,'岗亭-'.$this->sentrybox->title.'手动关闸');
                $this->success('关闸成功');
            }else{
                $this->error('关闸失败');
            }
        });
    }

    #[Post('trigger')]
    public function trigger()
    {
        $barrier_id=$this->request->post('barrier_id');
        $parking_ids=getParkingIds($this->sentrybox->parking_id);
        $barrier=ParkingBarrier::where(['id'=>$barrier_id])->whereIn('parking_id',$parking_ids)->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        try{
            $photo=Utils::trigger($barrier);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('识别成功',$photo);
    }

    #[Post('photo')]
    public function photo()
    {
        $barrier_id=$this->request->post('barrier_id');
        $parking_ids=getParkingIds($this->sentrybox->parking_id);
        $barrier=ParkingBarrier::where(['id'=>$barrier_id])->whereIn('parking_id',$parking_ids)->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        try{
            $result=Utils::makePhoto($barrier,false);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('',$result);
    }

    #[Post('entry')]
    public function entry()
    {
        $postdata=$this->request->post();
        $parking_ids=getParkingIds($this->sentrybox->parking_id);
        $barrier=ParkingBarrier::where(['id'=>$postdata['barrier_id']])->whereIn('parking_id',$parking_ids)->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $parkingService=ParkingService::newInstance([
            'parking'=>$parking,
            'barrier'=>$barrier,
            'plate_number'=>$postdata['platenumber'],
            'plate_type'=>$postdata['plate_type'],
            'records_type'=>ParkingRecords::RECORDSTYPE('手动操作'),
            'entry_time'=>time(),
            'remark'=>'岗亭-'.$this->sentrybox->title.'手动入场'
        ]);
        try{
            $parkingService->entry();
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('入场成功');
    }

    #[Post('exit')]
    public function exit()
    {
        $postdata=$this->request->post();
        $parking_ids=getParkingIds($this->sentrybox->parking_id);
        $barrier=ParkingBarrier::where(['id'=>$postdata['barrier_id']])->whereIn('parking_id',$parking_ids)->find();
        if(!$barrier){
            $this->error('通道不存在');
        }
        if($barrier->status!='normal'){
            $this->error('通道已经被禁用');
        }
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $statusnum=[
            'x3'=>3,
            'x4'=>4,
            'x7'=>7
        ];
        $parkingService=ParkingService::newInstance([
            'parking'=>$parking,
            'barrier'=>$barrier,
            'plate_number'=>$postdata['platenumber'],
            'plate_type'=>$postdata['plate_type'],
            'records_type'=>ParkingRecords::RECORDSTYPE('手动操作'),
            'exit_time'=>time(),
            'pay_status'=>$statusnum[$postdata['pay_status']],
            'remark'=>'岗亭-'.$this->sentrybox->title.'手动出场'
        ]);
        try{
            $parkingService->exit();
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('出场成功');
    }

    #[Post('operate')]
    public function operate()
    {
        $postdata=$this->request->post();
        if($postdata['operate']){
            unset($postdata['operate']['createtime']);
            unset($postdata['operate']['updatetime']);
            $postdata['operate']['endtime']=date('Y-m-d H:i:s');
            ParkingSentryboxOperate::where('id',$postdata['operate']['id'])->update($postdata['operate']);
        }
        $insert=[
            'parking_id'=>$this->sentrybox->parking_id,
            'sentrybox_id'=>$this->sentrybox->id,
            'operator_name'=>$postdata['operator_name'],
            'operator_desc'=>$postdata['operator_desc'],
            'starttime'=>date('Y-m-d H:i:s'),
        ];
        $operate=new ParkingSentryboxOperate();
        $operate->save($insert);
        $this->success('',$operate);
    }

    #[Get('get-operate')]
    public function getOperate()
    {
        $operate=ParkingSentryboxOperate::where(['parking_id'=>$this->sentrybox->parking_id,'sentrybox_id'=>$this->sentrybox->id])->order('id desc')->find();
        if($operate){
            $prefix=getDbPrefix();
            $starttime=strtotime($operate->starttime);
            $endtime=time();
            $operate->starttime=date('Y-m-d H:i',$starttime);
            $operate->endtime=date('Y-m-d H:i',$endtime);
            $sql="select count(1) as count from {$prefix}parking_records where parking_id={$operate->parking_id} and entry_barrier in ({$this->sentrybox->barriers}) and entry_time between {$starttime} and {$endtime}";
            $operate->entry=Db::query($sql)[0]['count'];
            $sql="select count(1) as count from {$prefix}parking_records where parking_id={$operate->parking_id} and exit_barrier in ({$this->sentrybox->barriers}) and exit_time between {$starttime} and {$endtime}";
            $operate->exit=Db::query($sql)[0]['count'];
            $sql="select sum(pay_price) as fee from {$prefix}parking_records_pay where parking_id={$operate->parking_id} and barrier_id in ({$this->sentrybox->barriers}) and pay_id is not null and pay_id not in (select pay_id from {$prefix}parking_records_filter where pay_id is not null and parking_id={$operate->parking_id}) and createtime between {$starttime} and {$endtime}";
            $operate->online_fee=Db::query($sql)[0]['fee']??0;
            $sql="select sum(pay_fee) as fee from {$prefix}parking_records where parking_id={$operate->parking_id} and exit_barrier in ({$this->sentrybox->barriers}) and status=9 and exit_time between {$starttime} and {$endtime}";
            $operate->underline_fee=Db::query($sql)[0]['fee']??0;
        }
        $this->success('',$operate);
    }

    #[Post('send-coupon')]
    public function sendCoupon()
    {
        $barrier_id=$this->request->post('barrier_id');
        $coupon_id=$this->request->post('coupon_id');
        $records_id=$this->request->post('records_id');
        if(!$records_id){
            $this->error('没有记录，发券失败');
        }
        if(!$this->sentrybox->merch_id){
            $this->error('商户不存在');
        }
        $merchant=ParkingMerchant::find($this->sentrybox->merch_id);
        $coupon=ParkingMerchantCoupon::find($coupon_id);
        $records=ParkingRecords::find($records_id);
        try{
            ParkingMerchantCouponList::given($merchant,$coupon,$records->plate_number,'岗亭发券');
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $barrier=ParkingBarrier::find($barrier_id);
        $trigger=ParkingTrigger::where(['serialno'=>$barrier->serialno,'plate_number'=>$records->plate_number])->order('id desc')->find();
        $this->success('发券成功',['trigger_id'=>$trigger->id]);
    }

    #[Get('download')]
    public function download()
    {
        $file=root_path().'public/gangting.zip';
        return download($file,'gangting.zip');
    }
}