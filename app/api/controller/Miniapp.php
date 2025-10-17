<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Third;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\annotation\route\Get;
use app\common\library\Http;

#[Group("miniapp")]
class Miniapp extends Api
{
    protected $noNeedLogin = ['login','getMobile','parkingIndex','kefupage'];

    #[Post('login')]
    public function login()
    {
        $code=$this->request->post('code');
        $config=[
            'appid'=>site_config("addons.uniapp_miniapp_id"),
            'appsecret'=>site_config("addons.uniapp_miniapp_secret")
        ];
        $mini = new \WeMini\Crypt($config);
        $data = $mini->session($code);
        $openid=$data['openid'];
        $unionid=$data['unionid'];
        $avatar=null;
        $mobile=null;
        $nickname='微信用户';
        //判断是否启用账号绑定
        $third=Third::connect(Third::PLATFORM('微信小程序'), compact('openid', 'unionid', 'avatar', 'nickname', 'mobile'));
        $this->auth->loginByThirdPlatform(Third::PLATFORM('微信小程序'),$third);
        $token=$this->auth->getToken();
        $userinfo=$this->auth->userinfo();
        if($userinfo['nickname']=='微信用户'){
            //$this->auth->logout();
            //$this->error();
        }
        $this->success('登录成功',compact('token','userinfo'));
    }

    #[Post('getMobile')]
    public function getMobile()
    {
        $code=$this->request->post('code');
        $config=[
            'appid'=>site_config("addons.uniapp_miniapp_id"),
            'appsecret'=>site_config("addons.uniapp_miniapp_secret")
        ];
        $mini = new \WeMini\Crypt($config);
        $result=$mini->getPhoneNumber($code);
        $this->success('',$result['phone_info']['phoneNumber']);
    }

    #[Get('parking-index')]
    public function parkingIndex()
    {
        $config=[
            'appid'=>site_config("addons.uniapp_miniapp_id"),
            'appsecret'=>site_config("addons.uniapp_miniapp_secret")
        ];
        $mini = new \WeMini\Crypt($config);
        $access_token=$mini->getAccessToken();
        $url="https://api.weixin.qq.com/wxa/generatescheme?access_token=".$access_token;
        $response=Http::post($url,json_encode([
            'jump_wxa'=>[
                'path'=>'/pages/parking/index',
                'query'=>'',
                'env_version'=>'release'
            ]
        ]));
        if($response->isSuccess()){
            echo "<div style='text-align: center;padding: 130px 0;'><a style='font-size: 135px;' href='".$response->content['openlink']."'>点击这里跳转</a></div>";
            exit;
        }
    }

    #[Get('kefupage')]
    public function kefupage()
    {
        $config=[
            'appid'=>site_config("addons.uniapp_miniapp_id"),
            'appsecret'=>site_config("addons.uniapp_miniapp_secret")
        ];
        $mini = new \WeMini\Crypt($config);
        $access_token=$mini->getAccessToken();
        $url="https://api.weixin.qq.com/wxa/generatescheme?access_token=".$access_token;
        $response=Http::post($url,json_encode([
            'jump_wxa'=>[
                'path'=>'/pages/index/kefu',
                'query'=>'',
                'env_version'=>'release'
            ]
        ]));
        if($response->isSuccess()){
            echo "<div style='text-align: center;padding: 130px 0;'><a style='font-size: 135px;' href='".$response->content['openlink']."'>点击这里跳转</a></div>";
            exit;
        }
    }
}
