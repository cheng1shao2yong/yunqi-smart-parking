<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Http;
use think\annotation\route\Group;
use think\annotation\route\Post;
use app\common\model\Third;

#[Group("alipay")]
class Alipay extends Api
{
    protected $noNeedLogin = ['login'];

    const PRIVATE_KEY="MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDDtlwvO2Xh2MkwfiKZ3sMLKV5diff7+Bjum5xGEzMy0NhHmKuDluKcK2ot1P7dF3AACCuk72nBcpTzA7peAGw03tRTXCnEP1i+eGnyoauQrpZoU2Fybz2BrmCcG1PYcMoom28JwHAZfJpfrYJR2j5qCJdmcnXDARlcnsoFFLjZx3wpP1ReU42O4I45HZOjHJjAoPEc8wk9S73h6lrL8qHXGAPjCts3PG8PBYq+3ZcLbN67AQN+gVCGryQk1g41oDziHvHlruH1lJlzcsAnA92/NJizoZKDu21Ay6/VKXnLGeaJ00BSEbLLXYFGYEAz8aKg4e9HAycJf9n+MoLBxqDnAgMBAAECggEAB4aDlO1bxYtocQzol7IRHeTBVCdx+aZYjxQ8thUW6uVM67PbJHwwyoCA3LJL+oRkMhweUYFDN8UIJTAHgoXRo1bOI0Zv9LPa3bgTmtjMmAuejPRn0takLtBdtqL2XmQ34cfYRS+5H8HCzdsGH8+chsc9yxqrnIv2RbF1Yyygzv7jVdhttrLEhcDv2UfqJPA1yGMgkzbAWlw2E5tw9A/yO3CgVmus+H65kXml7O9s1lJzu1m3mw6kni2OtYNRaBL7tSmrvFWOc8wQb+YuYmEx1cpvrJRcRvDjmUlX7XwoU4AslzIlmGs5FPKVA19/MnAr5a8nkzfn/U98jK5EYVG4QQKBgQD9v//ozwJeN232+SJI8HzcDWyxwgYsbaz87UwbX8m5cUBlIuE1iCQ0Wo0xLVfzX/eQFJTMJzwbf7hcdwjoBPjI8f8s36BvQ9BUYgvsF/JmTsP+eqBiK09xvH0odcDMHeeieU2fBcWms7KKguRFaCfmG5SR17SaEb5ahyum4AimAwKBgQDFcp4k8ZrQM/Jtk7atWp0FyD3m8Ie5wIckH4l3jJOLZ/1u5fOqIu9igcGNuZ0HDD3VyACjwelnvMmVytpDGkPrY8soYCJ7fx+ssNS3jrRkCZseyuFoFhGD1EMHFdndqRT/3KM2kusvBWqPwjajpAerrABv4sNA211Y2EO0GgLmTQKBgBhE0Lj3c4aHTqEcWscXGRoCvq6Rm/2Lz8uw9mJ32tc9macSmu9/wdawAmj9qTfBqe/ByCluZzVKFdviFpv6PcEaRAmKqdF6buZjKji+YZSfW+du2wAInGSIXoAMIxBim9DSQTZAWxMChMTyl9p7h7VeVetM8hz6LXaDDw26x5j5AoGATh79Z0yYnkwPXT+WhZxWiFUj+N2uNdZxId/AeiaKshug8GHXOLe901HXjQWllYZIaO9LIX+1o6/UaonqNaaMvPOtC/qNaiNwGtmUMFQsF3CdiV9oM9CXeXtgUctZehROFLXtdchHeUFBWkPTboeV6VySh7JG4sBofKCEmRu5jfkCgYEAvXTzZkPfvt2nbc3M54uUkv5pP4x7UZhV+k7DL6Lj3tzsNxCL2DNWdKma0mVRhRYBNbH8YWsLmAyV4caU0opND8KIctYAbuYMby5UwBuPsdxAZH948quTl0qfsMwu495G2bciNQfrNKaaaFN79z9aGPC7ZBThESZJQVI5Y7YJu90=";

    const APPID='2021004184666260';

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
            'app_id'=>self::APPID,
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
        $privateString=self::PRIVATE_KEY;
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
