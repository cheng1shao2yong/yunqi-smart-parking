<?php
declare (strict_types = 1);

namespace app\api\controller\merchant;

use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingCarsApply;
use app\common\model\parking\ParkingCarsLogs;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRules;
use app\common\model\Qrcode;
use app\common\model\manage\Parking;
use app\common\model\Qrcode as QrcodeModel;
use app\common\model\Third;
use app\common\service\msg\WechatMsg;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Db;

#[Group("merchant/car")]
class Car extends Base
{
    #[Get('rules')]
    public function rules()
    {
        $merchant=ParkingMerchant::find($this->merch_id);
        if(!$merchant->day_shenhe){
            $this->error('您没有预约车审批权限！');
        }
        $day=explode(',',$merchant->day_shenhe);
        $rules=ParkingRules::where(['parking_id'=>$this->parking_id])->whereIn('id',$day)->select();
        $shenhe=ParkingCarsApply::where(['parking_id'=>$this->parking_id,'status'=>0,'merch_id'=>$this->merch_id])->count();
        $this->success('',compact('rules','shenhe'));
    }

    #[Get('qrcode')]
    public function qrcode()
    {
        $rules_id=$this->request->get('rules_id');
        $config=[
            'appid'=>site_config("addons.uniapp_mpapp_id"),
            'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
        ];
        $qrcode= QrcodeModel::createQrcode('merchant-entry-apply',$this->parking_id.','.$this->merch_id.','.$rules_id,24*3600*30);
        $wechat=new \WeChat\Qrcode($config);
        $ticket = $wechat->create($qrcode->id,24*3600*30)['ticket'];
        $url=$wechat->url($ticket);
        $this->success('',compact('url','qrcode'));
    }

    #[Get('write')]
    public function write()
    {
        $merchant=ParkingMerchant::find($this->merch_id);
        if(!$merchant->day_shenhe){
            $this->error('您没有预约车审批权限！');
        }
        $parking=Parking::field('id,title,plate_begin')->find($this->parking_id);
        $this->success('',compact('parking'));
    }

    #[Get('apply')]
    public function apply()
    {
        $shenhe=ParkingCarsApply::where(['parking_id'=>$this->parking_id,'status'=>0,'merch_id'=>$this->merch_id])->select();
        $this->success('',$shenhe);
    }

    #[Post('shenhe')]
    public function shenhe()
    {
        $id=$this->request->post('id');
        $result=$this->request->post('result');
        $apply=ParkingCarsApply::where(['id'=>$id,'status'=>0,'merch_id'=>$this->merch_id])->find();
        if(!$apply){
            $this->error('申请不存在或已处理');
        }
        if($result==1){
            try{
                Db::startTrans();
                if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('申请日租卡')){
                    $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking_id])->find();
                    $time=strtotime(date('Y-m-d 00:00:00',time()));
                    $endtime=$rules->online_apply_days*3600*24+$time-1;
                    $third=Third::where(['user_id'=>$apply->user_id,'platform'=>'miniapp'])->find();
                    $plates=array(['plate_number'=>$apply->plate_number,'plate_type'=>'blue','car_models'=>'small']);
                    $cars=ParkingCars::addCars($rules,$apply->contact,$apply->mobile,$apply->user_id,$plates,['endtime'=>$endtime,'third_id'=>$third->id]);
                    ParkingCarsLogs::addMerchLog($cars,$this->merch_id,'商户审核通过预约车');
                    WechatMsg::successDayApply($apply,true);
                }
                if($apply->apply_type==ParkingCarsApply::APPLY_TYPE('过期日租卡续期')){
                    $cars=$apply->cars;
                    $rules=ParkingRules::where(['id'=>$apply->rules_id,'parking_id'=>$this->parking_id])->find();
                    $time=strtotime(date('Y-m-d 00:00:00',time()));
                    $endtime=$rules->online_renew_days*3600*24+$time-1;
                    $cars->endtime=$endtime;
                    $cars->save();
                    ParkingCarsLogs::addMerchLog($cars,$this->merch_id,'商户审核通过预约车续租');
                    WechatMsg::successDayApply($apply,true);
                }
                $apply->status=1;
                $apply->save();
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        if($result==2){
            $apply->status=2;
            $apply->save();
            WechatMsg::successDayApply($apply,false);
        }
        $this->success('审核完成');
    }

    #[Post('confirm')]
    public function confirm()
    {
        $plate_number=$this->request->post('plate_number');
        if(!is_car_license($plate_number)){
            $this->error('车牌号格式错误');
        }
        $qrcode_id=$this->request->post('qrcode_id');
        $mobile=$this->request->post('mobile');
        $contact=$this->request->post('contact');
        $plate=ParkingPlate::where(['plate_number'=>$plate_number,'parking_id'=>$this->parking_id])->find();
        if($plate){
            $this->error('该车牌已经被录入过');
        }
        $qrcode=Qrcode::find($qrcode_id);
        [$parking_id,$merch_id,$rules_id]=explode(',',$qrcode->foreign_key);
        if($this->parking_id!=$parking_id || $this->merch_id!=$merch_id){
            $this->error('二维码无效');
        }
        $rules=ParkingRules::find($rules_id);
        $starttime=strtotime(date('Y-m-d 00:00:00',time()));
        $endtime=$rules->online_apply_days*3600*24+$starttime-1;
        try {
            $cars=ParkingCars::addCars($rules,$contact,$mobile,null,array(['plate_number'=>$plate_number,'plate_type'=>'blue','car_models'=>'small']),['starttime'=>$starttime,'endtime'=>$endtime]);
            ParkingCarsLogs::addMerchLog($cars,$this->merch_id,'商户添加预约车');
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success();
    }
}
