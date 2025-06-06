<?php
declare(strict_types=1);
namespace app\api\controller;

use app\common\library\Pay;
use app\common\model\Admin;
use app\common\model\Company;
use app\common\model\CompanyRegister;
use app\common\model\manage\Parking;
use app\common\model\MpSubscribe;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingRecovery;
use app\common\model\Minigames;
use app\common\model\Qrcode;
use app\common\model\Third;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Route;
use app\common\controller\Api;
use app\common\model\QrcodeScan;

#[Group("mpapp")]
class Mpapp extends Api{
    protected $noNeedLogin = ['*'];

    protected $config=[];

    const PAGE=[
        //用户端首页
        'index'=>'/pages/index/index',
        //小程序登录
        'miniapp'=>'/pages/index/miniapp',
        //H5登录后跳转
        'redict'=>'/pages/index/redict',
        //无牌车入场
        'entry'=>'/pages/index/entry',
        //欠费补缴
        'recovery'=>'/pages/index/recovery',
        //绑定微信
        'binduser'=>'/pages/index/binduser'
    ];

    protected function _initialize()
    {
        parent::_initialize();
        $this->config=[
            'appid'=>site_config("addons.uniapp_mpapp_id"),
            'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
            'token'=>site_config("addons.uniapp_mpapp_token"),
            'encodingaeskey'=>site_config("addons.uniapp_mpapp_aeskey")
        ];
    }
    /**
     * 发起授权
     */
    #[Get('connect')]
    public function connect()
    {
        if($this->auth->id){
            return $this->gourl();
        }else{
            $arr=$this->request->get();
            if(count($arr)>0){
                $str='';
                foreach ($arr as $k=>$v){
                    $str.=$k.'='.$v.'&';
                }
                $str=substr($str,0,strlen($str)-1);
                $callback=$this->request->domain().'/mpapp/callback?'.$str;
            }else{
                $callback=$this->request->domain().'/mpapp/callback';
            }
            $wechat = new \WeChat\Oauth($this->config);
            // 执行操作
            $result = $wechat->getOauthRedirect($callback, '', 'snsapi_userinfo');
            return redirect($result);
        }
    }

    /**
     * 授权回调
     */
    #[Get('callback')]
    public function callback()
    {
        // 授权成功后的回调
        $wechat = new \WeChat\Oauth($this->config);
        $result = $wechat->getOauthAccessToken();
        $userinfo = $wechat->getUserInfo($result['access_token'],$result['openid']);
        $result['nickname']=$userinfo['nickname'];
        $result['avatar']=$userinfo['headimgurl'];
        //判断是否启用账号绑定
        $third=Third::connect('mpapp', $result);
        $this->auth->loginByThirdPlatform(Third::PLATFORM('微信公众号'),$third);
        return $this->gourl();
    }

    private function gourl()
    {
        $arr=$this->request->get();
        $action=$arr['action'];
        if($action=='minigames'){
            $id=$arr['id'];
            $games=Minigames::find($id);
            $url=$games->url.'?token='.$this->auth->getToken();
            return redirect($url);
        }else{
            unset($arr['action']);
            unset($arr['code']);
            unset($arr['state']);
            $query='&'.http_build_query($arr);
            $url=request()->domain().'/h5/#'.self::PAGE[$action].'?token='.$this->auth->getToken().$query;
            return redirect($url);
        }
    }

    /**
     * 创建菜单
     */
    #[Get('menu')]
    public function menu()
    {
        $menu = new \WeChat\Menu($this->config);
        $json=array('button'=>[
            //跳转到公众号页面
            [
                "name"=>"充值缴费",
                "sub_button"=>[
                    [
                        "name"=>"临停缴费",
                        "appid"=>site_config("addons.uniapp_miniapp_id"),
                        "type"=>"miniprogram",
                        "pagepath"=>"pages/index/plate?type=parking",
                    ],
                    [
                        "name"=>"月卡充值",
                        "appid"=>site_config("addons.uniapp_miniapp_id"),
                        "type"=>"miniprogram",
                        "pagepath"=>"pages/index/plate?type=monthly_renew",
                    ],
                    [
                        "name"=>"储值卡充值",
                        "appid"=>site_config("addons.uniapp_miniapp_id"),
                        "type"=>"miniprogram",
                        "pagepath"=>"pages/index/plate?type=stored_renew",
                    ]
                ]
            ],
            [
                "name"=>"管理入口",
                "sub_button"=>[
                    [
                        "name"=>"停车场管理员",
                        "appid"=>site_config("addons.uniapp_miniapp_id"),
                        "type"=>"miniprogram",
                        "pagepath"=>"pages/parking/index",
                    ],
                    [
                        "name"=>"商户管理员",
                        "appid"=>site_config("addons.uniapp_miniapp_id"),
                        "type"=>"miniprogram",
                        "pagepath"=>"pages/merchant/index",
                    ],
                    [
                        "name"=>"代理商",
                        "type"=>"view",
                        "url"=>"https://guiyang.1p23.com/h5/#/pages/daili/index",
                    ],
                    [
                        "name"=>"平台管理员",
                        "appid"=>site_config("addons.uniapp_miniapp_id"),
                        "type"=>"miniprogram",
                        "pagepath"=>"pages/admin/parking",
                    ]
                ]
            ],
            [
                "name"=>"车主主页",
                "appid"=>site_config("addons.uniapp_miniapp_id"),
                "type"=>"miniprogram",
                "pagepath"=>"pages/index/index",
            ]
        ]);
        // 执行创建菜单
        $menu->create($json);
        $this->success('创建菜单成功');
    }

    /**
     * 公众号事件接收方法
     */
    #[Route('POST,GET','event')]
    public function event()
    {
        $api = new \WeChat\Receive($this->config);
        $msgtype=$api->getMsgType();
        if($msgtype=='text'){
            $api->text('尊敬客户您好，感谢您使用【'.site_config("basic.sitename").'】公众号！')->reply();
            return;
        }
        if($msgtype=='event'){
            $message = $api->getReceive();
            event('write_log','微信消息:'.json_encode($message));
            $event = $message['Event'];
            $eventkey = isset($message['EventKey'])? $message['EventKey'] : '';
            $openid=$message['FromUserName'];
            switch ($event) {
                //添加关注
                case 'subscribe':
                    $user = new \WeChat\User($this->config);
                    $userinfo=$user->getUserInfo($openid);
                    $unionid=isset($userinfo['unionid'])?$userinfo['unionid'] : '';
                    //记录关注
                    MpSubscribe::create([
                        'openid'=>$openid,
                        'unionid'=>$unionid,
                    ]);
                    //普通关注
                    if(is_array($eventkey)){
                        $api->text('尊敬客户您好，感谢您使用【'.site_config("basic.sitename").'】公众号！')->reply();
                        return;
                    }
                    //扫码关注
                    if(strpos($eventkey,'qrscene_')===0){
                        $eventkey=substr($eventkey,8);
                        $resp=$this->scanQrcode($openid,$unionid,$eventkey);
                        $api->text($resp)->reply();
                        return;
                    }
                //取消关注
                case 'unsubscribe':
                    MpSubscribe::where(['openid'=>$openid])->delete();
                    return;
                //扫二维码
                case 'SCAN':
                    $user = new \WeChat\User($this->config);
                    $userinfo=$user->getUserInfo($openid);
                    $unionid=isset($userinfo['unionid'])?$userinfo['unionid'] : '';
                    $resp=$this->scanQrcode($openid,$unionid,$eventkey);
                    $api->text($resp)->reply();
                    return;
                //跳转链接
                case 'VIEW':
            }
        }
    }

    /**
     * 扫码回调事件
     */
    private function scanQrcode($openid,$unionid,$qrcode_id)
    {
        $qrcode=Qrcode::find($qrcode_id);
        $str="尊敬客户您好：\n\n感谢您使用【".site_config('basic.sitename')."】公众号平台！\n\n";
        if(!$qrcode){
            return $str;
        }
        //生成的扫码记录表，可以在用户注册时，查询该表从而绑定推荐人
        $scan=false;
        if($qrcode->set_mpapp_scan){
            $scan=QrcodeScan::create([
                'openid'=>$openid,
                'unionid'=>$unionid,
                'qrcode_id'=>$qrcode->id,
                'foreign_key'=>$qrcode->foreign_key,
                'type'=>$qrcode->type,
                'scantime'=>time(),
            ]);
        }
        //根据业务场景返回不同的消息
        switch ($qrcode->type){
            case 'parking-entry-qrcode':
                $barrier=ParkingBarrier::findBarrierBySerialno($qrcode->foreign_key,['barrier_type'=>'entry','status'=>'normal']);
                if(!$barrier){
                    return "{$str}入场通道不存在，请联系管理员！\n\n";
                }
                $parking=Parking::find($barrier->parking_id);
                $str="{$str}识别到您所在的位置为：【{$parking->title}-{$barrier->title}】\n\n";
                $path1=$this->request->domain()."/mpapp/connect?scan_id={$scan->id}&action=entry";
                $end1="<a href=\"{$path1}\">👉👉无牌车入场点这里👈️👈️</a>";
                $end2='';
                $recovery=ParkingRecovery::where(['entry_barrier'=>$qrcode->foreign_key,'pay_id'=>null])->where('entry_time','>',time()-15*60)->find();
                if($recovery){
                    $path='pages/index/parking?serialno='.$barrier->serialno;
                    $miniapp_id=site_config('addons.uniapp_miniapp_id');
                    $end2="\n\n<a data-miniprogram-appid=\"{$miniapp_id}\" data-miniprogram-path=\"{$path}\" data-miniprogram-type=\"text\">👉👉欠费补缴点这里👈️👈️</a>";
                }
                return "{$str}{$end1}{$end2}";
            case 'parking-mpapp-index':
                $str="{$str}请点击菜单跳转到相应页面\n\n";
                return $str;
            case 'backend-login':
            case 'parking-login':
                $str="{$str}您正在使用微信扫码授权登录【云起停车】管理后台\n\n";
                $third=Third::where(['platform'=>Third::PLATFORM('微信公众号'),'openid'=>$openid])->find();
                if(!$third){
                    return "{$str}您的微信没有绑定管理员！";
                }
                $count=Admin::where(['third_id'=>$third->id])->count();
                if(!$count){
                    return "{$str}您的微信没有绑定管理员！";
                }
                return "{$str}扫码成功！";
            case 'merchant-login':
                $str="{$str}您正在使用微信扫码授权登录【云起停车】商户端\n\n";
                $path=$this->request->domain()."/mpapp/connect?action=binduser";
                $end="<a href=\"{$path}\">👉👉点击这里授权👈️👈️</a>";
                return "{$str}{$end}";
            case 'bind-third-user':
                $path=$this->request->domain()."/mpapp/connect?action=binduser";
                $end="<a href=\"{$path}\">👉👉点击这里授权👈️👈️</a>";
                return "{$str}您正在使用微信扫码授权获取您的微信头像、昵称\n\n{$end}";
            case 'merchant-dynamic-qrcode':
            case 'merchant-static-qrcode':
                $path='pages/merchant/plate?qrcode_id='.$qrcode->id;
                $miniapp_id=site_config('addons.uniapp_miniapp_id');
                $end="<a data-miniprogram-appid=\"{$miniapp_id}\" data-miniprogram-path=\"{$path}\" data-miniprogram-type=\"text\">👉👉点击这里登记👈️👈️</a>";
                return "{$str}您正在扫码登记停车优惠信息\n\n{$end}";
            case 'parking-entry-apply':
                $parking=Parking::cache('parking_'.$qrcode->foreign_key,24*3600)->withJoin(['setting'])->find($qrcode->foreign_key);
                $path='pages/index/plate?type=day_apply&parking_id='.$parking->id;
                $miniapp_id=site_config('addons.uniapp_miniapp_id');
                $end="<a data-miniprogram-appid=\"{$miniapp_id}\" data-miniprogram-path=\"{$path}\" data-miniprogram-type=\"text\">👉👉点击这里提交车牌资料👈️👈️</a>";
                return "{$str}您正在申请停车场【{$parking->title}】预约入场\n\n{$end}";
            case 'merchant-entry-apply':
                [$parking_id,$merch_id,$rules_id]=explode(',',$qrcode->foreign_key);
                $parking=Parking::cache('parking_'.$parking_id,24*3600)->withJoin(['setting'])->find($parking_id);
                $path='pages/index/plate?type=day_apply&parking_id='.$parking->id.'&merch_id='.$merch_id.'&rules_id='.$rules_id;
                $miniapp_id=site_config('addons.uniapp_miniapp_id');
                $end="<a data-miniprogram-appid=\"{$miniapp_id}\" data-miniprogram-path=\"{$path}\" data-miniprogram-type=\"text\">👉👉点击这里提交车牌资料👈️👈️</a>";
                return "{$str}您正在申请停车场【{$parking->title}】预约入场\n\n{$end}";
        }
    }
}