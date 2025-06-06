<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Accesskey;
use app\common\model\Admin;
use app\common\model\parking\ParkingAdmin;
use app\common\model\Third;
use think\annotation\route\Get;
use think\annotation\route\Group;
use app\common\model\Qrcode;
use think\annotation\route\Post;

#[Group("user")]
class User extends Api
{
    protected $noNeedLogin = ['getAccessToken'];

    #[Get('userinfo')]
    public function userinfo()
    {
        $user=$this->auth->userinfo();
        $this->success('',$user);
    }

    #[Post('qrcode')]
    public function qrcode()
    {
        $type=$this->request->post('type');
        $foreign_key=$this->request->post('foreign_key');
        $qrcode=Qrcode::where(['type'=>$type,'foreign_key'=>$foreign_key])->find();
        if(!$qrcode){
            $qrcode=new Qrcode();
            $qrcode->type=$type;
            $qrcode->foreign_key=$foreign_key;
            $qrcode->save();
        }
        $config=[
            'appid'=>site_config("addons.uniapp_mpapp_id"),
            'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
        ];
        $wechat=new \WeChat\Qrcode($config);
        $ticket = $wechat->create($qrcode->id)['ticket'];
        $url=$wechat->url($ticket);
        $this->success('',$url);
    }

    #[Post('get-access-token')]
    public function getAccessToken()
    {
        $access_key=$this->request->post('access_key');
        $access_secret=$this->request->post('access_secret');
        if(!$access_key || !$access_secret){
            $this->error('参数错误');
        }
        $access=Accesskey::where(['access_key'=>$access_key,'access_secret'=>$access_secret])->find();
        if(!$access){
            $this->error('api账号不存在');
        }
        if($access->status!='normal'){
            $this->error('api账号被禁用');
        }
        try{
            $this->auth->loginByUserId($access->user_id);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->auth->setAccess($access);
        $token=$this->auth->getToken();
        $this->success('成功',[
            'token'=>$token,
            'expire_time'=>time()+7200
        ]);
    }
}
