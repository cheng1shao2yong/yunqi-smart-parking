<?php
declare(strict_types=1);
namespace app\common\service\pay;

use app\common\model\PayUnion;
use app\common\model\Third;
use app\common\service\PayService;
use think\facade\Env;
use Yansongda\Pay\Pay;

/**
 * 微信支付宝商户支付
 */
class Subwechat extends PayService {

    private $config;

    protected function init()
    {
        $this->pay_type_handle='微信服务商支付';
        $this->config = [
            'wechat' => [
                'default' => [
                    // 「必填」商户号，服务商模式下为服务商商户号
                    // 可在 https://pay.weixin.qq.com/ 账户中心->商户信息 查看
                    'mch_id' => Env::get('WECHAT_MERCH_NO'),
                    // 「选填」v2商户私钥
                    'mch_secret_key_v2' => '',
                    // 「必填」v3 商户秘钥
                    // 即 API v3 密钥(32字节，形如md5值)，可在 账户中心->API安全 中设置
                    'mch_secret_key' => Env::get('WECHAT_MERCH_KEY'),
                    // 「必填」商户私钥 字符串或路径
                    // 即 API证书 PRIVATE KEY，可在 账户中心->API安全->申请API证书 里获得
                    // 文件名形如：apiclient_key.pem
                    'mch_secret_cert' => root_path().'cert/apiclient_key.pem',
                    // 「必填」商户公钥证书路径
                    // 即 API证书 CERTIFICATE，可在 账户中心->API安全->申请API证书 里获得
                    // 文件名形如：apiclient_cert.pem
                    'mch_public_cert_path' => root_path().'cert/apiclient_cert.pem',
                    // 「必填」微信回调url
                    // 不能有参数，如?号，空格等，否则会无法正确回调
                    'notify_url' => request()->domain().'/index/notify/custom',
                    // 「选填」公众号 的 app_id
                    // 可在 mp.weixin.qq.com 设置与开发->基本配置->开发者ID(AppID) 查看
                    'mp_app_id' => site_config("addons.uniapp_mpapp_id"),
                    // 「选填」小程序 的 app_id
                    'mini_app_id' => site_config("addons.uniapp_miniapp_id"),
                    // 「选填」app 的 app_id
                    'app_id' => '',
                    // 「选填」服务商模式下，子公众号 的 app_id
                    'sub_mp_app_id' => '',
                    // 「选填」服务商模式下，子 app 的 app_id
                    'sub_app_id' => '',
                    // 「选填」服务商模式下，子小程序 的 app_id
                    //'sub_mini_app_id' => site_config("addons.uniapp_miniapp_id"),
                    'sub_mini_app_id' => '',
                    // 「选填」服务商模式下，子商户id
                    'sub_mch_id' => '',
                    // 「选填」（适用于 2024-11 及之前开通微信支付的老商户）微信支付平台证书序列号及证书路径，强烈建议 php-fpm 模式下配置此参数
                    // 「必填」微信支付公钥ID及证书路径，key 填写形如 PUB_KEY_ID_0000000000000024101100397200000006 的公钥id，见 https://pay.weixin.qq.com/doc/v3/merchant/4013053249
                    'wechat_public_cert_path' => [
                        'PUB_KEY_ID_0116733863302025121000381848002602' => root_path().'cert/pub_key.pem',
                    ],
                    // 「选填」默认为正常模式。可选为： MODE_NORMAL, MODE_SERVICE
                    'mode' => Pay::MODE_SERVICE,
                ]
            ]
        ];
    }

    public function wechatMiniappPay()
    {
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $handling_fees=$this->handlingFee();
        $union=PayUnion::wechatminiapp(PayUnion::PAY_TYPE_HANDLE('微信服务商支付'),$user,$this->pay_price,$handling_fees,$this->order_type,$this->attach,$this->order_body);
        $third=Third::where(['platform'=>'miniapp','user_id'=>$this->user_id])->find();
        $order = [
            'out_trade_no' => $union->out_trade_no,
            'description' => $this->order_body,
            'amount' => [
                'total' => intval($union->pay_price*100),
                'currency' => 'CNY',
            ],
            'payer' => [
                'sp_openid' => $third->openid
            ]
        ];
        $this->config['wechat']['default']['sub_mch_id']=$this->sub_merch_no;
        Pay::config($this->config);
        $pay =  Pay::wechat()->mini($order);
        $result=[];
        $result['payInfo']=$pay;
        $result['orderId']=$union->out_trade_no;
        $result['payType']='custom';
        return $result;
    }

    public function refund()
    {
        $order = [
            'out_trade_no' => $this->pay_union->out_trade_no,
            'out_refund_no' => 're'.create_out_trade_no(),
            'amount' => [
                'refund' => intval($this->refund_price*100),
                'total' => intval($this->pay_union->pay_price*100),
                'currency' => 'CNY',
            ]
        ];
        $result = Pay::wechat($this->config)->refund($order);
        if($result){
            return true;
        }
        throw new \Exception('退款失败');
    }

    public function alipayPcPay()
    {
        // TODO: Implement alipayPcPay() method.
    }

    public function mpAlipay()
    {

    }

    public function wechatPcPay()
    {
        // TODO: Implement wechatPcPay() method.
    }

    public function wechatMpappPay()
    {
        // TODO: Implement wechatMpappPay() method.
    }

    public function qrcodePay()
    {

    }


    public function notify()
    {
        $result = Pay::wechat($this->config)->callback();
        if($result){
            $data=$result->all();
            event('write_log',json_encode($data,JSON_UNESCAPED_UNICODE));
            $out_trade_no=$data['resource']['ciphertext']['out_trade_no'];
            $amount=$data['resource']['ciphertext']['amount']['total'];
            $transaction_id=$data['resource']['ciphertext']['transaction_id'];
            $union=PayUnion::where('out_trade_no',$out_trade_no)->find();
            if(!$union){
                return '';
            }
            if($union->pay_status==1){
                return 'SUCCESS';
            }
            if($amount!=intval($union->pay_price*100)){
                return '';
            }
            try{
                $union->paySuccess($transaction_id);
                return 'SUCCESS';
            }catch (\Exception $e){
                //print_r($e->getFile().'|'.$e->getLine().'|'.$e->getMessage());
                event('write_log','交易异常:'.$e->getMessage());
                return '';
            }
        }
    }

    private function handlingFee()
    {
        $persent=bcdiv($this->persent,'1000',4);
        $payprice=(string)($this->pay_price*100);
        $handling_fees=bcmul($payprice,$persent,1);
        $handling_fees=round(floatval($handling_fees));
        return $handling_fees;
    }
}