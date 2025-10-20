<?php
declare(strict_types=1);
namespace app\common\service\msg;

use app\common\model\manage\Parking;
use app\common\model\MpSubscribe;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingCarsApply;
use app\common\model\parking\ParkingInvoice;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecovery;
use app\common\model\parking\ParkingTemporary;
use app\common\model\PlateBinding;
use app\common\model\Third;
use app\common\service\MsgService;
use app\common\model\Msg;
use think\facade\Db;

class WechatMsg extends MsgService{

    protected $msg_type='wechat';

    //模板列表
    const 入场通知='X79ZcW3IScBPtdNUGHnUbTMs5ks5-VWPku92RHMS_3A';
    const 出场通知='dwfuTquswfRvhapSjedzeEcaKJSEnAR4QEqrCSDBPtU';
    const 开票申请='UKF16ySisN1Hi0RkzTmPAD5V4M0EnETrNGstoy5rGIE';
    const 开票完成='47n0VjbGFsJuQsFhfO6S9z-jS5I1hp6YUx24uJ6edYo';
    const 欠费车辆入场='_dVvJAJMva1GZNTueDDmVd6W1-dgJsV92vItg9CiPBg';
    const 月租到期通知='5_zvwv2pY9yRx6xpHGUgKVun4fbqgk3sarowSaH-UCc';
    const 车牌认证成功='B2KRNrFdR1UXvDNswHVhWYZueNydi2E5TR1FhwGVViE';
    const 月租车申请审批='yEJqFPGywr9Zf7DmKvi0WEVMC9Y0TAtV2qG5g9N5iKM';
    const 月租车申请审核结果='3vVD42Yvo2GIf2EoEGXClGVUxsj20vOzvFPWxHrB7vk';
    const 预约车申请审批='rddxESqY_y8XVkqcX2-gXUNv7MP5nEiSrMostBMKEh4';
    const 预约车申请审核结果='dOFFRJqDr2UTSHIROvwO20kgoUJb53HVZT_dIWr48mQ';

    protected function sendEvent(Msg $msg): bool
    {
        $config=[
            'appid'=>site_config("addons.uniapp_mpapp_id"),
            'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
        ];
        // 实例接口
        $wechat = new \WeChat\Template($config);
        // 执行操作
        try{
            $wechat->send(json_decode($msg->content,true));
            return true;
        }catch (\Exception $e){
            $this->error=$e->getMessage();
            return false;
        }
    }

    public static function applyInvince(int $parking_id,int $orders,float $money)
    {
        $prefix=getDbPrefix();
        $sql="SELECT openid FROM {$prefix}third where id in (SELECT third_id FROM {$prefix}admin where id in (SELECT admin_id FROM {$prefix}parking_admin where parking_id={$parking_id} and (role='treasurer' or role='admin')))";
        $openids=Db::query($sql);
        foreach ($openids as $value){
            $openid=$value['openid'];
            $postdata=[
                'touser'=>$openid,
                'template_id'=>self::开票申请,
                //跳转到小程序
                'miniprogram'=>[
                    'appid'=>site_config("addons.uniapp_miniapp_id"),
                    'pagepath'=>'/pages/parking/finance/invoice',
                ],
                'data'=>[
                    'amount4'=>['value'=>$money],
                    'thing1'=>['value'=>'您有'.$orders.'张停车发票需要处理'],
                ]
            ];
            $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
            $service=self::newInstance();
            $service->create($postdata,$openid);
        }
    }

    public static function remindArrearsParking(ParkingRecovery $recovery,string $entryParkingTitle,string $entryBarrierTitle)
    {
        $prefix=getDbPrefix();
        $sql="SELECT openid FROM {$prefix}third where id in (SELECT third_id FROM {$prefix}admin where id in (SELECT admin_id FROM {$prefix}parking_admin where parking_id={$recovery->parking_id}))";
        $openids=Db::query($sql);
        foreach ($openids as $value){
            $openid=$value['openid'];
            $postdata=[
                'touser'=>$openid,
                'template_id'=>self::欠费车辆入场,
                //跳转到小程序
                'miniprogram'=>[
                    'appid'=>site_config("addons.uniapp_miniapp_id"),
                    'pagepath'=>'/pages/parking/records/recovery',
                ],
                'data'=>[
                    'car_number1'=>['value'=>$recovery->plate_number],
                    'thing2'=>['value'=>self::substrThing($entryParkingTitle)],
                    'amount5'=>['value'=>$recovery->total_fee.'元'],
                    'thing7'=>['value'=>$entryBarrierTitle],
                ]
            ];
            $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
            $service=self::newInstance();
            $service->create($postdata,$openid);
        }
    }

    public static function successInvince(ParkingInvoice $invoice)
    {
        $prefix=getDbPrefix();
        $sql="SELECT {$prefix}mp_subscribe.openid FROM {$prefix}mp_subscribe,{$prefix}third where {$prefix}mp_subscribe.unionid={$prefix}third.unionid and {$prefix}third.user_id={$invoice->user_id} GROUP BY {$prefix}third.user_id";
        $list=Db::query($sql);
        if(empty($list)){
            return false;
        }
        $openid=$list[0]['openid'];
        $postdata=[
            'touser'=>$openid,
            'template_id'=>self::开票完成,
            //跳转到小程序
            'miniprogram'=>[
                'appid'=>site_config("addons.uniapp_miniapp_id"),
                'pagepath'=>'/pages/index/invoice',
            ],
            'data'=>[
                'thing6'=>['value'=>self::substrThing($invoice->title)],
                'amount3'=>['value'=>$invoice->total_price],
            ]
        ];
        $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
        $service=self::newInstance();
        $service->create($postdata,$openid);
    }

    public static function dayCarApply(ParkingCarsApply $apply)
    {
        $prefix=getDbPrefix();
        if($apply->merch_id){
            $page='/pages/merchant/shenhe';
            $sql="SELECT openid FROM yun_third where id in (SELECT third_id FROM yun_parking_merchant_user where merch_id={$apply->merch_id})";
        }else{
            $page='/pages/parking/cars/apply';
            $sql="SELECT openid FROM {$prefix}third where id in (SELECT third_id FROM {$prefix}admin where id in (SELECT admin_id FROM {$prefix}parking_admin where parking_id={$apply->parking_id}))";
        }
        $remark=json_decode($apply->remark,true);
        $openids=Db::query($sql);
        foreach ($openids as $value){
            $openid=$value['openid'];
            $postdata=[
                'touser'=>$openid,
                'template_id'=>self::预约车申请审批,
                //跳转到小程序
                'miniprogram'=>[
                    'appid'=>site_config("addons.uniapp_miniapp_id"),
                    'pagepath'=>$page,
                ],
                'data'=>[
                    'thing3'=>['value'=>$apply->contact],
                    'car_number2'=>['value'=>$apply->plate_number],
                    'time4'=>['value'=>$remark['入场时间']],
                    'time5'=>['value'=>$remark['离开时间']]
                ]
            ];
            $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
            $service=self::newInstance();
            $service->create($postdata,$openid);
        }
    }

    public static function successDayApply(ParkingCarsApply $apply,bool $success)
    {
        $prefix=getDbPrefix();
        $sql="SELECT {$prefix}mp_subscribe.openid FROM {$prefix}mp_subscribe,{$prefix}third where {$prefix}mp_subscribe.unionid={$prefix}third.unionid and {$prefix}third.user_id={$apply->user_id} GROUP BY {$prefix}third.user_id";
        $list=Db::query($sql);
        if(empty($list)){
            return false;
        }
        $openid=$list[0]['openid'];
        $postdata=[
            'touser'=>$openid,
            'template_id'=>self::预约车申请审核结果,
            //跳转到小程序
            'miniprogram'=>[
                'appid'=>site_config("addons.uniapp_miniapp_id"),
                'pagepath'=>'/pages/index/index',
            ],
            'data'=>[
                'thing2'=>['value'=>self::substrThing($apply->parking->title)],
                'car_number1'=>['value'=>$apply->plate_number],
                'const3'=>['value'=>$success?'成功':'失败'],
            ]
        ];
        $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
        $service=self::newInstance();
        $service->create($postdata,$openid);
    }

    public static function monthlyCarApply(ParkingCarsApply $apply)
    {
        $prefix=getDbPrefix();
        $sql="SELECT openid FROM {$prefix}third where id in (SELECT third_id FROM {$prefix}admin where id in (SELECT admin_id FROM {$prefix}parking_admin where parking_id={$apply->parking_id}))";
        $openids=Db::query($sql);
        foreach ($openids as $value){
            $openid=$value['openid'];
            $postdata=[
                'touser'=>$openid,
                'template_id'=>self::月租车申请审批,
                //跳转到小程序
                'miniprogram'=>[
                    'appid'=>site_config("addons.uniapp_miniapp_id"),
                    'pagepath'=>'/pages/parking/cars/apply',
                ],
                'data'=>[
                    'thing1'=>['value'=>$apply->contact],
                    'phone_number2'=>['value'=>$apply->mobile],
                    'car_number3'=>['value'=>$apply->plate_number],
                ]
            ];
            $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
            $service=self::newInstance();
            $service->create($postdata,$openid);
        }
    }

    public static function successMonthlyApply(ParkingCarsApply $apply,bool $success)
    {
        $prefix=getDbPrefix();
        $sql="SELECT {$prefix}mp_subscribe.openid FROM {$prefix}mp_subscribe,{$prefix}third where {$prefix}mp_subscribe.unionid={$prefix}third.unionid and {$prefix}third.user_id={$apply->user_id} GROUP BY {$prefix}third.user_id";
        $list=Db::query($sql);
        if(empty($list)){
            return false;
        }
        $openid=$list[0]['openid'];
        $postdata=[
            'touser'=>$openid,
            'template_id'=>self::月租车申请审核结果,
            //跳转到小程序
            'miniprogram'=>[
                'appid'=>site_config("addons.uniapp_miniapp_id"),
                'pagepath'=>'/pages/index/index',
            ],
            'data'=>[
                'thing5'=>['value'=>self::substrThing($apply->parking->title)],
                'car_number1'=>['value'=>$apply->plate_number],
                'const2'=>['value'=>$success?'成功':'失败'],
            ]
        ];
        $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
        $service=self::newInstance();
        $service->create($postdata,$openid);
    }
    public static function successPlate(PlateBinding $binding)
    {
        $prefix=getDbPrefix();
        $sql="SELECT {$prefix}mp_subscribe.openid FROM {$prefix}mp_subscribe,{$prefix}third where {$prefix}mp_subscribe.unionid={$prefix}third.unionid and {$prefix}third.user_id={$binding->user_id} GROUP BY {$prefix}third.user_id";
        $list=Db::query($sql);
        if(empty($list)){
            return false;
        }
        $openid=$list[0]['openid'];
        $postdata=[
            'touser'=>$openid,
            'template_id'=>self::车牌认证成功,
            //跳转到小程序
            'miniprogram'=>[
                'appid'=>site_config("addons.uniapp_miniapp_id"),
                'pagepath'=>'/pages/index/index',
            ],
            'data'=>[
                'car_number1'=>['value'=>$binding->plate_number],
                'time4'=>['value'=>date('Y-m-d H:i:s',time())],
            ]
        ];
        $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
        $service=self::newInstance();
        $service->create($postdata,$openid);
    }

    //无牌车入场通知
    public static function tempEntry(Parking $parking,ParkingTemporary $temporary)
    {
        $postdata=[
            'touser'=>$temporary->openid,
            'template_id'=>self::入场通知,
            //跳转到小程序
            'miniprogram'=>[
                'appid'=>site_config("addons.uniapp_miniapp_id"),
                'pagepath'=>'/pages/index/records',
            ],
            'data'=>[
                'car_number1'=>['value'=>$temporary->plate_number],
                'thing2'=>['value'=>self::substrThing($parking->title)],
                'thing5'=>['value'=>self::substrThing($parking->address)]
            ]
        ];
        $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
        $service=self::newInstance();
        $service->create($postdata,$temporary->openid);
    }

    //月租到期通知
    public static function monthlynotice(array $data)
    {
        foreach ($data as $temporary){
            $postdata=[
                'touser'=>$temporary['openid'],
                'template_id'=>self::月租到期通知,
                //跳转到小程序
                'miniprogram'=>[
                    'appid'=>site_config("addons.uniapp_miniapp_id"),
                    'pagepath'=>'/pages/index/monthly?plate_number='.$temporary['plate_number'].'&parking_id='.$temporary['parking_id'],
                ],
                'data'=>[
                    'thing6'=>['value'=>self::substrThing($temporary['parking_title'])],
                    'car_number2'=>['value'=>$temporary['plate_number']],
                    'time7'=>['value'=>$temporary['endtime']],
                    'character_string9'=>['value'=>$temporary['lastday']],
                ]
            ];
            $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
            $service=self::newInstance();
            $service->create($postdata,$temporary['openid']);
        }
    }

    public static function entry(Parking $parking,string $plate_number)
    {
        $prefix=getDbPrefix();
        $sql="select {$prefix}mp_subscribe.openid from {$prefix}mp_subscribe,{$prefix}third where {$prefix}mp_subscribe.unionid={$prefix}third.unionid and {$prefix}third.user_id in (select user_id FROM {$prefix}plate_binding where plate_number='{$plate_number}') GROUP BY {$prefix}third.user_id";
        $openids=Db::query($sql);
        foreach ($openids as $value){
            $openid=$value['openid'];
            $postdata=[
                'touser'=>$openid,
                'template_id'=>self::入场通知,
                //跳转到小程序
                'miniprogram'=>[
                    'appid'=>site_config("addons.uniapp_miniapp_id"),
                    'pagepath'=>'/pages/index/records',
                ],
                'data'=>[
                    'car_number1'=>['value'=>$plate_number],
                    'thing2'=>['value'=>self::substrThing($parking->title)],
                    'thing5'=>['value'=>self::substrThing($parking->address)]
                ]
            ];
            $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
            $service=self::newInstance();
            $service->create($postdata,$openid);
        }
    }

    public static function exit(Parking $parking,string $plate_number)
    {
        $prefix=getDbPrefix();
        $sql="select {$prefix}mp_subscribe.openid from {$prefix}mp_subscribe,{$prefix}third where {$prefix}mp_subscribe.unionid={$prefix}third.unionid and {$prefix}third.user_id in (select user_id FROM {$prefix}plate_binding where plate_number='{$plate_number}') GROUP BY {$prefix}third.user_id";
        $openids=Db::query($sql);
        foreach ($openids as $value){
            $openid=$value['openid'];
            $postdata=[
                'touser'=>$openid,
                'template_id'=>self::出场通知,
                //跳转到小程序
                'miniprogram'=>[
                    'appid'=>site_config("addons.uniapp_miniapp_id"),
                    'pagepath'=>'/pages/index/records',
                ],
                'data'=>[
                    'car_number1'=>['value'=>$plate_number],
                    'thing2'=>['value'=>self::substrThing($parking->title)],
                    'thing6'=>['value'=>self::substrThing($parking->address)],
                ]
            ];
            $postdata=json_encode($postdata,JSON_UNESCAPED_UNICODE);
            $service=self::newInstance();
            $service->create($postdata,$openid);
        }
    }

    private static function substrThing($str)
    {
        if(!$str){
            return '未知';
        }
        if(mb_strlen($str,'utf-8')<=20){
            return $str;
        }else{
            return mb_substr($str,0,17,'utf-8').'...';
        }
    }
}