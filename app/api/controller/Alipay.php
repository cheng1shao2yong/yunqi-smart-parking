<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Http;
use think\facade\Env;
use think\annotation\route\Group;
use think\annotation\route\Post;
use app\common\model\Third;

#[Group("alipay")]
class Alipay extends Api
{
    protected $noNeedLogin = ['login'];

    #[Post('login')]
    public function login()
    {
        $code=$this->request->post('code');
        $url="https://openapi.alipay.com/gateway.do";
        $data=[
            'grant_type'=>'authorization_code',
            'code'=>$code
        ];
        $response=Http::post($this->parseUrl('alipay.system.oauth.token',$url,$data),$data);
        if($response->isSuccess()){
            $result=json_decode($response->content,true)['alipay_system_oauth_token_response'];
            $param=[
                'nickname'=>'支付宝用户',
                'openid'=>$result['user_id'],
                'access_token'=>$result['access_token'],
                'refresh_token'=>$result['refresh_token'],
                'expires_in'=>$result['expires_in'],
            ];
            //判断是否启用账号绑定
            $third=Third::connect(Third::PLATFORM('支付宝小程序'), $param);
            $this->auth->loginByThirdPlatform(Third::PLATFORM('支付宝小程序'),$third);
            $token=$this->auth->getToken();
            $userinfo=$this->auth->userinfo();
            $this->success('登录成功',compact('token','userinfo'));
        }else{
            $this->error('登录失败');
        }
    }

    private function parseUrl($method,$url,$data)
    {
        $arr=[
            'charset'=>'UTF-8',
            'method'=>$method,
            'format'=>'json',
            'app_id'=>site_config('addons.uniapp_alimini_id'),
            'version'=>'1.0',
            'sign_type'=>'RSA2',
            'timestamp'=>date("Y-m-d H:i:s"),
        ];
        $sign=$this->sign(array_merge($data,$arr));
        $arr['sign']=$sign;
        return $url.'?'.http_build_query($arr);
    }

    private function sign($data)
    {
        ksort($data);
        $str = "";
        $i = 0;
        foreach ($data as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                if ($i == 0) {
                    $str .= "$k" . "=" . "$v";
                } else {
                    $str .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        $privateString=Env::get('ALIPAY_PRIVATE_KEY');
        $private_key = <<<EOT
-----BEGIN RSA PRIVATE KEY-----
{$privateString}
-----END RSA PRIVATE KEY-----
EOT;
        $signature = '';
        if (!openssl_sign($str, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            throw new \Exception('Signature failed');
        }
        $r=base64_encode($signature);
        return $r;
    }

    private function checkEmpty($value) {
        if (!isset($value)){
            return true;
        }
        if ($value === null){
            return true;
        }
        if (trim($value) === ""){
            return true;
        }
        return false;
    }
}
