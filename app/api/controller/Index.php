<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\manage\Parking;
use app\common\model\MpSubscribe;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRecovery;
use app\common\model\parking\ParkingScreen;
use app\common\model\parking\ParkingTemporary;
use app\common\model\parking\ParkingTrigger;
use app\common\model\PayUnion;
use app\common\model\PlateBinding;
use app\common\model\QrcodeScan;
use app\common\model\Third;
use app\common\model\UserNotice;
use app\common\service\barrier\Utils;
use app\common\service\msg\WechatMsg;
use app\common\service\ParkingService;
use app\common\service\PayService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\annotation\route\Route;
use think\facade\Cache;
use think\facade\Db;

#[Group("index")]
class Index extends Api
{
    protected $noNeedLogin = ['notify'];

    #[Route('GET,POST','notify/:handle')]
    public function notify($handle)
    {
        try{
            $service=PayService::newInstance([
                'pay_type_handle'=>$handle,
            ]);
            return $service->notify();
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
    }

    #[Get('parking')]
    public function parking()
    {
       $id=$this->request->get('id');
       $uniqid=$this->request->get('uniqid');
       if($uniqid){
           $parking=Parking::field('id,title,uniqid,province_id,city_id,area_id,plate_begin')->where('uniqid',$uniqid)->find();
       }
       if($id){
           $parking=Parking::field('id,title,uniqid,province_id,city_id,area_id,plate_begin')->find($id);
       }
       $showMember=false;
       $plates=PlateBinding::getUserPlate($this->auth->id);
       if(count($plates)>0){
           $monthly=ParkingPlate::with(['cars'])->where(['parking_id'=>$parking->id])->whereIn('plate_number',$plates)->find();
           $showMember=[
               'plate_number'=>$monthly?$monthly->plate_number:$plates[0],
               'monthly'=>$monthly,
           ];
       }
       $parking->showMember=$showMember;
       $this->success('',$parking);
    }

    #[Get('serialno')]
    public function serialno()
    {
        $serialno = $this->request->get('serialno');
        if ($serialno) {
            $barrier=ParkingBarrier::findBarrierBySerialno($serialno,['status'=>'normal']);
            if (!$barrier) {
                $this->error('通道不存在或已经被禁用');
            }
            $pay=ParkingRecordsPay::with(['records'])->where([
                'barrier_id'=>$barrier->id,
                'parking_id'=>$barrier->parking_id,
            ])->order('id desc')->find();
            if($pay && ($pay->pay_id || $pay->createtime<=time() - $barrier->limit_pay_time)){
                $pay=false;
            }
            if(!$pay){
                //检测逃费追缴
                $recovery_plate=Cache::get('recovery_event_'.$barrier->serialno);
                if($recovery_plate){
                    $this->success('',['type'=>'recovery','plate_number'=>$recovery_plate,'parking_id'=>$barrier->parking_id,'barrier_id'=>$barrier->id]);
                }
                $plate_number='';
                //判断是否为临牌车
                $third=Third::where(['user_id'=>$this->auth->id,'platform'=>'mpapp'])->find();
                if($third){
                    $temporary = ParkingTemporary::where(['openid' => $third->openid])->order('id desc')->find();
                    if($temporary){
                        $plate_number=$temporary->plate_number;
                    }
                }
                if(!$plate_number){
                    $this->error('没有检查到出场车辆，如果相机没有扫到车牌，请退后一点重新进场');
                }
                $this->success('',['type'=>'plate_number','plate_number'=>$plate_number]);
            }
            $records=$pay->records;
            $records->records_pay_id=$pay->id;
            $records->parking = Parking::getParkingInfo($pay->parking_id);
            $records->exit_time=$records->exit_time??time();
            $records->total_fee=$pay->total_fee;
            $records->pay_fee=$pay->pay_fee;
            $records->activities_fee=$pay->activities_fee;
            $records->pay_price=$pay->pay_price;
            ParkingScreen::sendBlackMessage($barrier,$records->plate_number.'正在支付');
            $this->success('',['type'=>'records','records'=>$records]);
        }
    }

    //主动离场
    #[Get('exit')]
    public function exit()
    {
        $serialno = $this->request->get('serialno');
        $plate_number = $this->request->get('plate_number');
        $barrier=ParkingBarrier::findBarrierBySerialno($serialno,['barrier_type'=>'exit','status'=>'normal']);
        if (!$barrier) {
            $this->error('通道不存在或已经被禁用');
        }
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $r=false;
        try{
            $endtime=time() - $barrier->limit_pay_time;
            $trigger=ParkingTrigger::where('serialno',$barrier->serialno)->order('id desc')->find();
            if($trigger && $trigger->plate_number=='无牌车' && $trigger->createtime>$endtime){
                $photo=$trigger->image;
            }else{
                $temporary_check_cars=$parking->setting->temporary_check_cars;
                if($temporary_check_cars){
                    $this->error('没有识别到无牌车，请退后重试');
                }
                $photo=Utils::makePhoto($barrier);
            }
            $service=ParkingService::newInstance([
                'parking' => $parking,
                'barrier'=>$barrier,
                'plate_number' => $plate_number,
                'plate_type' => 'blue',
                'photo' => $photo,
                'records_type'=>ParkingRecords::RECORDSTYPE('自动识别'),
                'exit_time' =>time()
            ]);
            $r=$service->exit();
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        if(!$r){
            $this->success('',0);
        }
        $this->success('',1);
    }

    #[Get('records')]
    public function records()
    {
        $recordslist=ParkingRecords::with(['rules'])->where(function ($query){
            $parking_id=$this->request->get('parking_id');
            $plate_number=$this->request->get('plate_number');
            $records_id=$this->request->get('records_id');
            if(!$parking_id && !$plate_number && !$records_id){
                $this->error('请选择停车场或车牌');
            }
            if($parking_id){
                $query->where('parking_id','=',$parking_id);
            }
            if($plate_number){
                $query->where('plate_number','=',$plate_number);
                $query->whereIn('status',[0,1,6,7]);
            }
            if($records_id){
                $query->where('id','=',$records_id);
                $query->whereIn('status',[0,1,6,7]);
            }
        })->order('id desc')->select();
        $r=[];
        foreach ($recordslist as $key=>$records){
            $parking=Parking::cache('parking_'.$records->parking_id,24*3600)->withJoin(['setting'])->find($records->parking_id);
            $exittime=time();
            if($records->status==7){
                $exittime=$records->exit_time;
            }
            $service=ParkingService::newInstance([
                'parking'=>$parking,
                'plate_number'=>$records->plate_number,
                'plate_type'=>$records->plate_type,
                'exit_time'=>$exittime
            ]);
            //追缴情况
            $recovery=ParkingRecovery::where('records_id',$records->id)->find();
            if($recovery){
                $total_fee=$recovery->total_fee;
            }else{
                $total_fee=$service->getTotalFee($records,$exittime);
            }
            if($total_fee<0){
                continue;
            }
            [$activities_fee]=$service->getActivitiesFee($records,$total_fee);
            $records->exit_time=$exittime;
            $records->total_fee=$total_fee;
            $records->activities_fee=$records->activities_fee+$activities_fee;
            $records->pay_price=formatNumber($total_fee-$records->activities_fee-$activities_fee-$records->pay_fee);
            $records->parking=[
                'id'=>$parking->id,
                'title'=>$parking->title,
                'address'=>$parking->address,
                'longitude'=>$parking->longitude,
                'latitude'=>$parking->latitude,
                'phone'=>$parking->setting->phone,
                'rules_txt'=>$parking->setting->rules_txt
            ];
            $r[]=$records;
        }
        $this->success('',$r);
    }

    #[Post('parking-pay')]
    public function parkingPay()
    {
        $records_id=$this->request->post('records_id');
        $records_pay_id=$this->request->post('records_pay_id');
        $pay_platform=$this->request->post('pay_platform','wechat-miniapp');
        $records=ParkingRecords::find($records_id);
        if(!$records){
            $this->error('记录不存在');
        }
        if(!in_array($records->status,[0,1,6,7])){
            $this->error('该记录已经付款');
        }
        if($records_pay_id){
            $recordspay=ParkingRecordsPay::find($records_pay_id);
            if($recordspay->pay_id){
                $this->error('该记录已经付款');
            }
            $parking=Parking::cache('parking_'.$recordspay->parking_id,24*3600)->withJoin(['setting'])->find($recordspay->parking_id);
        }else{
            $parking=Parking::cache('parking_'.$records->parking_id,24*3600)->withJoin(['setting'])->find($records->parking_id);
            $exittime=time();
            if($records->status==7){
                $exittime=$records->exit_time;
            }
            $parkingService=ParkingService::newInstance([
                'parking'=>$parking,
                'plate_number'=>$records->plate_number,
                'plate_type'=>$records->plate_type,
                'records_type'=>ParkingRecords::RECORDSTYPE('手动操作'),
                'exit_time'=>$exittime
            ]);
            if(!$parkingService->createOrder($records)){
                $recordspay=$parkingService->getRecordsPay();
                $records_pay_id=$recordspay->id;
            }else{
                $this->error('该记录已经付款');
            }
        }
        //限制同时间只能一个人付款
        $cacheRecordsPay=Cache::get('records_pay_'.$records_pay_id);
        if($cacheRecordsPay && $cacheRecordsPay['user_id']!=$this->auth->id){
            $lasttime=time()-$cacheRecordsPay['createtime'];
            $this->error('该记录正在付款中，请等候'.$lasttime.'秒再试');
        }
        try{
            $service=PayService::newInstance([
                'pay_type_handle'=>$parking->pay_type_handle,
                'user_id'=>$this->auth->id,
                'parking_id'=>$parking->id,
                'sub_merch_no'=>$parking->sub_merch_no,
                'sub_merch_key'=>$parking->sub_merch_key,
                'split_merch_no'=>$parking->split_merch_no,
                'persent'=>$parking->parking_records_persent,
                'pay_price'=>$recordspay->pay_price,
                'order_type'=>PayUnion::ORDER_TYPE('停车缴费'),
                'order_body'=>$records->plate_number.'停车缴费',
                'attach'=>json_encode([
                    'records_pay_id'=>$records_pay_id,
                    'records_id'=>$records->id,
                    'plate_number'=>$records->plate_number,
                    'parking_title'=>$parking->title
                ],JSON_UNESCAPED_UNICODE)
            ]);
            if($pay_platform=='wechat-miniapp'){
                $r=$service->wechatMiniappPay();
            }
            if($pay_platform=='mp-alipay'){
                $r=$service->mpAlipay();
            }
            Cache::set('records_pay_'.$records_pay_id,[
                'user_id'=>$this->auth->id,
                'createtime'=>time()
            ],60);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('',$r);
    }

    #[Get('info')]
    public function info()
    {
        $app=site_config("basic");
        $app['logo']=formatImage($app['logo']);
        $app['logo_white']=formatImage($app['logo_white']);
        $mycar=[];
        $plates=PlateBinding::getUserPlate($this->auth->id);
        if(count($plates)>0){
            $records=ParkingRecords::with(['parking'])->whereIn('plate_number',$plates)->whereIn('status',[0,1])->select();
            $inplates=[];
            foreach ($records as $plate_records){
                $parking=Parking::cache('parking_'.$plate_records->parking_id,24*3600)->withJoin(['setting'])->find($plate_records->parking_id);
                $service=ParkingService::newInstance([
                    'parking'=>$parking,
                    'plate_number'=>$plate_records->plate_number,
                    'plate_type'=>$plate_records->plate_type
                ]);
                $total_fee=$service->getTotalFee($plate_records,time());
                [$activities_fee]=$service->getActivitiesFee($plate_records,$total_fee);
                $mycar[]=[
                    'plate_number'=>$plate_records->plate_number,
                    'plate_type'=>$plate_records->plate_type,
                    'records'=>[
                        'id'=>$plate_records->id,
                        'entry_time'=>date('Y-m-d H:i',$plate_records->entry_time),
                        'total_fee'=>$total_fee,
                        'activities_fee'=>$plate_records->activities_fee+$activities_fee,
                        'parking_id'=>$plate_records->parking_id,
                        'parking_title'=>$plate_records->parking->title,
                        'pay_fee'=>$plate_records->pay_fee,
                        'status'=>$plate_records->status
                    ]
                ];
                $service->destroy();
                $inplates[]=$plate_records->plate_number;
            }
            foreach ($plates as $plate){
                if(!in_array($plate,$inplates)){
                    $mycar[]=[
                        'plate_number'=>$plate
                    ];
                }
            }
        }
        $this->success('',compact('app','mycar'));
    }

    #[Get('cars')]
    public function cars()
    {
        $bind=PlateBinding::where('user_id',$this->auth->id)->select();
        $r=[];
        $plates=[];
        foreach ($bind as $value){
            $r[]=[
                'bind_id'=>$value->id,
                'is_apply_bind'=>$value->status?false:true,
                'plate_number'=>$value->plate_number,
            ];
            $plates[]=$value->plate_number;
        }
        $list=ParkingPlate::whereIn('plate_number',$plates)->group('plate_number')->column('plate_type','plate_number');
        foreach ($r as $key=>$value){
            if(isset($list[$value['plate_number']])){
                $r[$key]['plate_type']=$list[$value['plate_number']];
            }
        }
        $this->success('',$r);
    }

    #[Post('cancel-bind')]
    public function cancelBind()
    {
        $bind_id=$this->request->post('bind_id');
        $bind=PlateBinding::find($bind_id);
        if($bind->user_id!=$this->auth->id){
            $this->error('没有权限');
        }
        $bind->delete();
        $this->success('认证取消成功');
    }

    #[Post('bind')]
    public function bind()
    {
        $plate_number=$this->request->post('plate_number');
        $fullurl=$this->request->post('fullurl');
        if(!is_car_license($plate_number)){
            $this->error('车牌号格式错误');
        }
        $bind=PlateBinding::where(['plate_number'=>$plate_number,'user_id'=>$this->auth->id])->find();
        if($bind){
            $this->error('该车牌号申请中或者已经绑定');
        }
        PlateBinding::create([
            'user_id'=>$this->auth->id,
            'plate_number'=>$plate_number,
            'licence'=>$fullurl,
            'status'=>0,
            'createtime'=>time()
        ]);
        $this->success('提交成功，请等待审核');
    }

    #[Get('coupon')]
    public function coupon()
    {
        $plates=PlateBinding::getUserPlate($this->auth->id);
        $plates=array_merge($plates,[-1]);
        $page=$this->request->get('page/d');
        $type=$this->request->get('type');
        $where="parking_merchant_coupon_list.status={$type} and (user_id={$this->auth->id} or plate_number in ('".implode("','",$plates)."'))";
        $ids=[];
        $list=ParkingMerchantCouponList::withJoin(['coupon'],'inner')
        ->whereRaw($where)
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select()
        ->each(function ($item) use (&$ids){
            $ids[]=$item->id;
        });
        if(!empty($ids)){
            $ids=implode(',',$ids);
            $prefix=getDbPrefix();
            $sql="select prc.coupon_list_id,prc.records_id,pr.entry_time,pr.exit_time from {$prefix}parking_records pr,{$prefix}parking_records_coupon prc where pr.id=prc.records_id and prc.coupon_list_id in ({$ids})";
            $records=Db::query($sql);
            foreach ($list as $k=>$v){
                $recordslist=[];
                foreach ($records as $record){
                    if($v->id==$record['coupon_list_id']){
                        $recordslist[]=$record;
                    }
                }
                $list[$k]['records']=$recordslist;
            }
        }
        $this->success('',$list);
    }

    #[Get('coupon-detail')]
    public function couponDetail()
    {
        $id=$this->request->get('id');
        $plates=PlateBinding::getUserPlate($this->auth->id);
        $detail=ParkingMerchantCouponList::with(['coupon','parking'])
            ->where(['id'=>$id])
            ->find();
        if(!in_array($detail->plate_number,$plates) && $detail->user_id!=$this->auth->id){
            $this->error('没有权限');
        }
        $prefix=getDbPrefix();
        $sql="select prc.coupon_list_id,prc.records_id,pr.entry_time,pr.exit_time,pr.activities_fee from {$prefix}parking_records pr,{$prefix}parking_records_coupon prc where pr.id=prc.records_id and prc.coupon_list_id={$id}";
        $records=Db::query($sql);
        $detail->records=$records;
        $this->success('',$detail);
    }

    #[Get('get-temporary')]
    public function getTemporary()
    {
        $scan_id=$this->request->get('scan_id');
        $scan=QrcodeScan::where(['id'=>$scan_id])->find();
        if($scan->scantime<time()-300){
            $this->error('消息已经过期，请重新扫码');
        }
        $barrier=ParkingBarrier::findBarrierBySerialno($scan->foreign_key,['barrier_type'=>'entry']);
        $temp=ParkingTemporary::getTemporary($barrier->parking_id,$scan->openid);
        $this->success('',compact('temp','barrier'));
    }

    #[Post('open-temporary')]
    public function openTemporary()
    {
        $temp_id=$this->request->post('temp_id');
        $serialno=$this->request->post('serialno');
        $temp=ParkingTemporary::find($temp_id);
        $barrier=ParkingBarrier::findBarrierBySerialno($serialno,['barrier_type'=>'entry','status'=>'normal']);
        if(!$barrier){
            $this->error('通道不存在或者已经被禁用');
        }
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $endtime=time() - $barrier->limit_pay_time;
        $trigger=ParkingTrigger::where('serialno',$barrier->serialno)->order('id desc')->find();
        if($trigger && $trigger->plate_number=='无牌车' && $trigger->createtime>$endtime){
            $photo=$trigger->image;
        }else{
            $temporary_check_cars=$parking->setting->temporary_check_cars;
            if($temporary_check_cars){
                $this->error('没有识别到无牌车，请退后重试');
            }
            $photo=Utils::makePhoto($barrier);
        }
        try{
            $parkingService=ParkingService::newInstance([
                'parking' => $parking,
                'barrier'=>$barrier,
                'plate_number' => $temp->plate_number,
                'plate_type' => 'blue',
                'photo' => $photo,
                'records_type'=>ParkingRecords::RECORDSTYPE('自动识别'),
                'entry_time' =>time(),
            ]);
            $parkingService->entry();
            WechatMsg::tempEntry($parking,$temp);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success();
    }

    #[Route('GET,POST','notice')]
    public function notice()
    {
        if($this->request->isPost()){
            $name=$this->request->post('name');
            $value=$this->request->post('value');
            UserNotice::where(['user_id'=>$this->auth->id])->update([$name=>$value]);
            $this->success('设置成功');
        }
        $notice=UserNotice::where(['user_id'=>$this->auth->id])->find();
        if(!$notice){
            $notice=UserNotice::create([
                'user_id'=>$this->auth->id,
                'records'=>1,
                'monthly'=>1,
                'stored'=>1,
                'invoice'=>0,
                'coupon'=>0,
            ]);
        }
        $third=Third::where(['user_id'=>$this->auth->id,'platform'=>'miniapp'])->find();
        $subscribe=MpSubscribe::where(['unionid'=>$third->unionid])->find();
        $notice->subscribe=$subscribe?1:0;
        $notice->mpappname=site_config('basic.sitename');
        $this->success('',$notice);
    }
}
