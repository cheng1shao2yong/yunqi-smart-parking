<?php
declare (strict_types = 1);

namespace app\api\controller\parking;

use app\common\library\ParkingAccount;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingMerchantLog;
use app\common\model\parking\ParkingMerchantSetting;
use app\common\model\parking\ParkingMerchantUser;
use app\common\model\parking\ParkingRecords;
use app\common\model\PayUnion;
use app\common\model\Third;
use app\common\model\UserToken;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Db;


#[Group("parking/merch")]
class Merch extends Base
{
    #[Get('list')]
    public function list()
    {
        $page=$this->request->get('page/d');
        $list=ParkingMerchant::where(function ($query){
            $query->where('parking_id',$this->parking_id);
            $merch_name=$this->request->get('merch_name');
            $ziying=$this->request->get('ziying');
            if($merch_name){
                $query->where('merch_name','like','%'.$merch_name.'%');
            }
            if($ziying){
                $query->where('is_self',1);
            }else{
                $query->where('is_self',0);
            }
        })
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select();
        $this->success('',$list);
    }

    #[Get('coupon-list')]
    public function couponList()
    {
        $page=$this->request->get('page/d');
        $list=ParkingMerchantCouponList::with(['merch','coupon'])->where(function ($query){
            $query->where('parking_id',$this->parking_id);
            $type=$this->request->get('type');
            $merch_name=$this->request->get('merch_name');
            $plate_number=$this->request->get('plate_number');
            $starttime=$this->request->get('starttime');
            $endtime=$this->request->get('endtime');
            if($merch_name){
                $query->where('merch_name','like','%'.$merch_name.'%');
            }
            if($plate_number){
                $query->where('plate_number','like','%'.$plate_number.'%');
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
            $query->where('status','=',$type);
        })
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select();
        $this->success('',$list);
    }

    #[Get('get-coupon')]
    public function getCoupon()
    {
        $id=$this->request->get('id');
        $coupon=ParkingMerchantCoupon::where('parking_id',$this->parking_id)->field('id,title,coupon_type')->select();
        $merch=ParkingMerchant::where(['parking_id'=>$this->parking_id,'id'=>$id])->find();
        $this->success('',compact('coupon','merch'));
    }

    #[Post('coupon-send')]
    public function couponSend()
    {
        $plate_number=$this->request->post('plate_number');
        $merch_id=$this->request->post('merch_id');
        $coupon_id=$this->request->post('coupon_id');
        $remark=$this->request->post('remark');
        $merchant=ParkingMerchant::where(['parking_id'=>$this->parking_id,'id'=>$merch_id])->find();
        if(!$merchant){
            $this->error('商户不存在');
        }
        $coupon=ParkingMerchantCoupon::where(['id'=>$coupon_id,'parking_id'=>$this->parking_id])->find();
        if(!$coupon || $coupon->status!='normal'){
            $this->error('优惠券不存在或者被禁用');
        }
        try{
            ParkingMerchantCouponList::given($merchant,$coupon,$plate_number,$remark);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('发券成功');
    }

    #[Post('coupon-trush')]
    public function couponTrush()
    {
        $id=$this->request->post('id');
        $list=ParkingMerchantCouponList::find($id);
        if(!$list || $list->parking_id!=$this->parking_id){
            $this->error('停车券不存在');
        }
        if($list->status!==0 && $list->status!==2){
            $this->error('该券不可作废');
        }
        $list->status=4;
        $list->save();
        $this->success('操作成功');
    }

    #[Get('coupon-detail')]
    public function couponDetail()
    {
        $id=$this->request->get('id');
        $detail=ParkingMerchantCouponList::with(['coupon','merch'])
            ->where(['parking_id'=>$this->parking_id,'id'=>$id])
            ->find();
        $prefix=getDbPrefix();
        $sql="select prc.coupon_list_id,prc.records_id,pr.entry_time,pr.exit_time,pr.activities_fee from {$prefix}parking_records pr,{$prefix}parking_records_coupon prc where pr.id=prc.records_id and prc.coupon_list_id={$id}";
        $records=Db::query($sql);
        $detail->records=$records;
        $this->success('',$detail);
    }

    #[Get('coupon-setting-list')]
    public function couponSettingList()
    {
        $page=$this->request->get('page/d');
        $list=ParkingMerchantCoupon::where(function ($query){
            $query->where('parking_id',$this->parking_id);
        })
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select();
        $this->success('',$list);
    }

    #[Post('change-coupon-status')]
    public function changeCouponStatus()
    {
        $id=$this->request->post('id');
        $status=$this->request->post('status');
        $model=ParkingMerchantCoupon::find($id);
        if($model->parking_id!=$this->parking_id){
            $this->error('停车券不存在');
        }
        $model->status=$status;
        $model->save();
        $this->success('操作成功');
    }

    #[Post('del-coupon')]
    public function delCoupon()
    {
        $id=$this->request->post('id');
        $model=ParkingMerchantCoupon::find($id);
        if($model->parking_id!=$this->parking_id){
            $this->error('停车券不存在');
        }
        $model->delete();
        $this->success('操作成功');
    }

    #[Post('coupon-setting')]
    public function couponSetting()
    {
        $postdata=$this->request->post();
        $effective=$this->request->post('effective/d');
        $coupon_type=$this->request->post('coupon_type');
        $time=$this->request->post('time/d');
        $period=$this->request->post('period/d');
        if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时长券')){
            if($effective && $effective*60<$time){
                $this->error('有效时间不能小于优惠时长');
            }
        }
        if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时效券')){
            if($effective && $effective<$period){
                $this->error('有效时间不能小于优惠时效');
            }
        }
        if($coupon_type==ParkingMerchantCoupon::COUPON_TYPE('时段券')){
            $arr=explode("\n", str_replace("\r\n", "\n", $postdata['timespan']));
            $timespan=[];
            foreach ($arr as $v){
                $span=explode('-',$v);
                $timespan[]=['starttime'=>$span[0],'endtime'=>$span[1]];
            }
            $postdata['timespan']=json_encode($timespan,JSON_UNESCAPED_UNICODE);
        }
        if($postdata['id']){
            $model=ParkingMerchantCoupon::find($postdata['id']);
            if($model->parking_id!=$this->parking_id){
                $this->error('停车券不存在');
            }
        }else{
            $model=new ParkingMerchantCoupon();
            $postdata['parking_id']=$this->parking_id;
        }
        try{
            $model->save($postdata);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('操作成功');
    }

    #[Get('coupon-setting-detail')]
    public function couponSettingDetail()
    {
        $id=$this->request->get('id');
        $detail=ParkingMerchantCoupon::find($id);
        if($detail->parking_id!=$this->parking_id){
            $this->error('停车券不存在');
        }
        $this->success('',$detail);
    }

    #[Get('search')]
    public function search()
    {
        $list=ParkingMerchant::where(function ($query){
            $query->where('parking_id',$this->parking_id);
            $type=$this->request->get('type');
            $merch_name=$this->request->get('merch_name');
            if($merch_name){
                $query->where('merch_name','like','%'.$merch_name.'%');
            }
            if($type=='send'){
                $query->where('is_self','=',1);
            }
        })
        ->order('id desc')
        ->select();
        $this->success('',$list);
    }

    #[Get('info')]
    public function info()
    {
        $coupon=ParkingMerchantCoupon::where(['parking_id'=>$this->parking_id,'status'=>'normal'])->column('title','id');
        $settle_type=ParkingMerchant::SETTLE_TYPE;
        $uniqid=Parking::where('id',$this->parking_id)->value('uniqid');
        $this->success('',compact('coupon','settle_type','uniqid'));
    }

    #[Get('detail')]
    public function detail()
    {
        $merch_id=$this->request->get('merch_id');
        $merch=ParkingMerchant::with(['user'])->where(['id'=>$merch_id,'parking_id'=>$this->parking_id])->find();
        $merch->coupon=explode(',',$merch->coupon);
        $uniqid=Parking::where('id',$this->parking_id)->value('uniqid');
        $merch->username=str_replace($uniqid.'-','',$merch->username);
        $merch->third=[];
        if(!empty($merch->user)){
            $third_id=[];
            foreach ($merch->user as $value){
                $third_id[]=$value->third_id;
            }
            $merch->third=Third::whereIn('id',$third_id)->field('id,avatar,openname,user_id')->select();
        }
        $merch->password='';
        unset($merch->salt);
        $this->success('',$merch);
    }

    #[Post('edit')]
    public function edit()
    {
        $postdata=$this->request->post();
        if($postdata['id']){
            $model=ParkingMerchant::where(['id'=>$postdata['id'],'parking_id'=>$this->parking_id])->find();
        }else{
            $model=new ParkingMerchant();
            $postdata['parking_id']=$this->parking_id;
        }
        $uniqid=Parking::where('id',$this->parking_id)->value('uniqid');
        $postdata['username']=$uniqid.'-'.$postdata['username'];
        if($postdata['password']){
            $salt = str_rand(4);
            $postdata['salt']=$salt;
            $postdata['password']=md5(md5($postdata['password']) . $salt);
        }
        $postdata['coupon']=implode(',',$postdata['coupon']);
        try{
            Db::startTrans();
            $model->save($postdata);
            $coupon=[];
            $cinfo=ParkingMerchantCoupon::where(['parking_id'=>$this->parking_id])->column('title','id');
            //处理停车券设置
            if($postdata['id']){
                $setting=ParkingMerchantSetting::where(['parking_id'=>$this->parking_id,'merch_id'=>$model->id])->select();
                foreach (explode(',',$model->coupon) as $coupon_id){
                    foreach ($setting as $value){
                        if($value->coupon_id==$coupon_id){
                            $coupon[]=[
                                'parking_id'=>$model->parking_id,
                                'merch_id'=>$model->id,
                                'coupon_id'=>$coupon_id,
                                'coupon_title'=>$cinfo[$coupon_id],
                                'limit_send'=>$value->limit_send,
                                'limit_type'=>$value->limit_type,
                                'limit_number'=>$value->limit_number,
                                'limit_money'=>$value->limit_money,
                                'settle_type'=>$value->settle_type,
                                'settle_money'=>$value->settle_money,
                            ];
                            continue 2;
                        }
                    }
                    $coupon[]=[
                        'parking_id'=>$model->parking_id,
                        'merch_id'=>$model->id,
                        'coupon_id'=>$coupon_id,
                        'coupon_title'=>$cinfo[$coupon_id]
                    ];
                }
                ParkingMerchantSetting::where(['parking_id'=>$this->parking_id,'merch_id'=>$model->id])->delete();
                ParkingMerchantUser::where(['merch_id'=>$postdata['id'],'parking_id'=>$this->parking_id])->delete();
            }else{
                foreach (explode(',',$model->coupon) as $coupon_id){
                    $coupon[]=[
                        'parking_id'=>$model->parking_id,
                        'merch_id'=>$model->id,
                        'coupon_id'=>$coupon_id,
                        'coupon_title'=>$cinfo[$coupon_id]
                    ];
                }
            }
            (new ParkingMerchantSetting())->saveAll($coupon);
            $merchuser=ParkingMerchantUser::where(['merch_id'=>$model->id,'parking_id'=>$this->parking_id])->select();
            $tokens=UserToken::where('merch_admin','<>', null)->where('expire','>',time())->select();
            foreach ($merchuser as $user){
                foreach ($tokens as $token){
                    if(!$token->merch_admin){
                        continue;
                    }
                    $merch_admin=json_decode($token->merch_admin,true);
                    if(
                        $merch_admin['id']==$user->merch_id &&
                        $merch_admin['parking_id']==$user->parking_id
                    ){
                        $token->merch_admin=null;
                        $token->save();
                    }
                }
                $user->delete();
            }
            //处理微信登录
            if(!empty($postdata['third'])){
                $insert=[];
                foreach ($postdata['third'] as $value){
                    $insert[]=[
                        'merch_id'=>$model->id,
                        'parking_id'=>$this->parking_id,
                        'third_id'=>$value['id']
                    ];
                }
                (new ParkingMerchantUser())->saveAll($insert);
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('操作成功');
    }

    #[Post('del')]
    public function del()
    {
        $merch_id=$this->request->post('merch_id');
        $merch=ParkingMerchant::where(['id'=>$merch_id,'parking_id'=>$this->parking_id])->find();
        if($merch){
            $merch->delete();
        }
        $this->success('操作成功');
    }

    #[Get('recharge-detail')]
    public function rechargeDetail()
    {
        $merch_id=$this->request->get('merch_id');
        $merch=ParkingMerchant::where(['id'=>$merch_id,'parking_id'=>$this->parking_id])->find();
        unset($merch->password);
        unset($merch->salt);
        $this->success('',$merch);
    }

    #[Post('recharge')]
    public function recharge()
    {
        $postdata=$this->request->post();
        $merch=ParkingMerchant::where(['id'=>$postdata['merch_id'],'parking_id'=>$this->parking_id])->find();
        if(!$merch){
            $this->error('商户不存在');
        }
        if($merch->status=='hidden'){
            $this->error('商户已被禁用');
        }
        Db::startTrans();
        try{
            $pay_id=null;
            if($postdata['change_type']=='add'){
                $payunion=PayUnion::underline(
                    $postdata['money'],
                    PayUnion::ORDER_TYPE('商户充值'),
                    ['parking_id'=>$merch->parking_id],
                    '商户【'.$merch->merch_name.'】手机端充值'
                );
                $pay_id=$payunion->id;
            }
            $change=$postdata['money'];
            if($merch->settle_type=='time'){
                $change= $postdata['time'];
            }
            ParkingMerchantLog::addAdminLog($merch,$postdata['change_type'],$change,$postdata['remark'],$pay_id);
            Db::commit();
        }catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('充值成功');
    }

    #[Get('log')]
    public function log()
    {
        $page=$this->request->get('page/d');
        $list = ParkingMerchantLog::where(function ($query){
            $type=$this->request->get('type');
            $merch_id=$this->request->get('merch_id');
            if($type=='bill'){
                $query->where('log_type','=','records');
            }
            if($type=='recharge'){
                $query->where('pay_id','<>',null);
            }
            $query->where('merch_id','=',$merch_id);
            $query->where('parking_id','=',$this->parking_id);
        })
        ->with(['payunion','records'])
        ->order('id desc')
        ->limit(($page-1)*10,10)
        ->select();
        $this->success('', $list);
    }
}
