<?php
declare (strict_types = 1);

namespace app\api\controller\sentrybox;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecovery;
use app\common\model\parking\ParkingTemporary;
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
        $this->success('',compact('title','open_set','screen','mqttConfig','tips','update','hide_window'));
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
            $photo=Utils::makePhoto($barrier);
            [$isplate,$plate_number,$plate_type]=Utils::checkPlate($photo);
            sleep(1);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('',compact('isplate','plate_number','plate_type','photo'));
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

    #[Get('download')]
    public function download()
    {
        $file=root_path().'public/gangting.zip';
        return download($file,'gangting.zip');
    }
}