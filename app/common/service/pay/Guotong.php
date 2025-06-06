<?php
declare(strict_types=1);
namespace app\common\service\pay;

use app\common\library\Http;
use app\common\model\manage\Parking;
use app\common\model\PayUnion;
use app\common\model\Third;
use app\common\service\PayService;
/**
 * 国通支付
 */
class Guotong extends PayService {
    //测试url
    //const URL='http://jghwucyshtgjcbrnxgwe.gtxygroup.com/testterpay/';
    //正式url
    const URL='https://yyfsvxm.postar.cn/';

    private $config;

    protected function init()
    {
        $this->pay_type_handle='国通';
        $this->config=[
            //虚拟机构编号
            'agetId'=>'',
            //虚拟机构公钥
            'publicKey'=>'',
            //微信子商户，通过getReportInfo方法获取，取wxQdh那列的thirdMercid
            'wxSubMercid'=>'',
            //移动支付appid，问国通工作人员要
            'mAppid'=>'',
            //支付宝appid，问国通工作人员要
            'zfbappid'=>'',
            //微信合作伙伴编号，国通邮件申请获取
            'wxQdh'=>''
        ];
    }

    //关联支付的全流程
    public function test()
    {
        $parking=Parking::find(1);
        $service=PayService::newInstance([
            'pay_type_handle'=>$parking->pay_type_handle,
            'parking_id'=>$parking->id,
            'sub_merch_no'=>$parking->sub_merch_no
        ]);
        try{
            //虚拟机构号关联商户
            $service->connectCust();
            //商户服务化配置信息配置
            //$service->config();
            //appid配置信息配置，其中参数从getReportInfo获取
            $service->appidConfig();
            //$r=$service->getReportInfo();
            //print_r($r);
        }catch (\Exception $e)
        {
            print_r($e->getMessage());
        }
    }

    public function wechatMiniappPay()
    {
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $union=PayUnion::wechatminiapp(PayUnion::PAY_TYPE_HANDLE('国通'),$user,$this->pay_price,$this->getHandlingFees(),$this->order_type,$this->attach,$this->order_body);
        $third=Third::where(['platform'=>'miniapp','user_id'=>$this->user_id])->find();
        $data = array(
            'agetId' => $this->config['agetId'],
            'custId' =>  $this->sub_merch_no,
            'orderNo' => $union->out_trade_no,
            'txamt' =>  (string)($this->pay_price*100),
            'openid' =>  $third->openid,
            'payWay' =>  '1',
            'title' => $this->order_body,
            'ip' => request()->ip(),
            'wxAppid'=> site_config("addons.uniapp_miniapp_id"),
            'traType'=> '8',
            'limitPay'=> '0',
            'timeStamp'=> date('YmdHis'),
            'version'=> '1.0.0'
        );
        $data['sign']=$this->getSign($data);
        $response=Http::post(self::URL.'yyfsevr/order/pay',json_encode($data,JSON_UNESCAPED_UNICODE),[],["Content-Type: application/json; charset=utf-8"]);
        if($response->isSuccess()){
            if($response->content['code']=='000000'){
                $r['orderId']=$union->out_trade_no;
                $r['payInfo']=[
                    'appId'=>$response->content['data']['jsapiAppid'],
                    'timeStamp'=>$response->content['data']['jsapiTimestamp'],
                    'nonceStr'=>$response->content['data']['jsapiNoncestr'],
                    'package'=>$response->content['data']['jsapiPackage'],
                    'signType'=>$response->content['data']['jsapiSignType'],
                    'paySign'=>$response->content['data']['jsapiPaySign'],
                ];
                return $r;
            }else{
                throw new \Exception($response->content['msg']);
            }
        }
        throw new \Exception('支付失败');
    }

    public function wechatPcPay()
    {
        throw new \Exception('暂不支持');
    }

    public function alipayPcPay()
    {
        throw new \Exception('暂不支持');
    }

    public function mpAlipay()
    {
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $union=PayUnion::wechatminiapp(PayUnion::PAY_TYPE_HANDLE('国通'),$user,$this->pay_price,$this->getHandlingFees(),$this->order_type,$this->attach,$this->order_body);
        $third=Third::where(['platform'=>'alipay-mini','user_id'=>$this->user_id])->find();
        $data = array(
            'agetId' => $this->config['agetId'],
            'custId' =>  $this->sub_merch_no,
            'orderNo' => $union->out_trade_no,
            'txamt' =>  (string)($this->pay_price*100),
            'openid' =>  $third->openid,
            'payWay' =>  '2',
            'title' => $this->order_body,
            'ip' => request()->ip(),
            'zfbappid'=> site_config("addons.uniapp_alimini_id"),
            'timeStamp'=> date('YmdHis'),
            'version'=> '1.0.0'
        );
        $data['sign']=$this->getSign($data);
        $response=Http::post(self::URL.'yyfsevr/order/pay',json_encode($data,JSON_UNESCAPED_UNICODE),[],["Content-Type: application/json; charset=utf-8"]);
        if($response->isSuccess()){
            if($response->content['code']=='000000'){
                $r=[];
                $r['orderId']=$union->out_trade_no;
                $r['payInfo']=[
                    'tradeNO'=>$response->content['data']['prepayid'],
                ];
                return $r;
            }else{
                throw new \Exception($response->content['msg']);
            }
        }
        throw new \Exception('支付失败');
    }

    //被扫
    public function qrcodePay()
    {
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $union=PayUnion::qrcodePay(PayUnion::PAY_TYPE_HANDLE('国通'),$user,$this->pay_price,$this->getHandlingFees(),$this->order_type,$this->attach,$this->order_body);
        $data = array(
            'agetId' => $this->config['agetId'],
            'custId' =>  $this->sub_merch_no,
            'orderNo' => $union->out_trade_no,
            'txamt' =>  (string)($this->pay_price*100),
            'code' =>  $this->mediumNo,
            'tradingIp' => '127.0.0.1',
            'type' => 'P',
            'timeStamp'=> date('YmdHis'),
            'version'=> '1.0.0'
        );
        $data['sign']=$this->getSign($data);
        $response=Http::post(self::URL.'/yyfsevr/order/scanByMerchant',json_encode($data,JSON_UNESCAPED_UNICODE),[],["Content-Type: application/json; charset=utf-8"]);
        if($response->isSuccess()){
            if($response->content['code']=='000000'){
                return true;
            }else{
                throw new \Exception($response->content['msg']);
            }
        }
        throw new \Exception('支付失败');
    }
    public function wechatMpappPay()
    {
        throw new \Exception('暂不支持');
    }

    public function connectCust()
    {
        $data = array(
            'agetId' => $this->config['agetId'],
            'custId' =>  $this->sub_merch_no,
            'opType'=> '00',
            'timeStamp'=> date('YmdHis'),
            'version'=> '1.0.0'
        );
        $data['sign']=$this->getSign($data);
        $response=Http::post(self::URL.'yyfsevr/order/connectCust',json_encode($data,JSON_UNESCAPED_UNICODE),[],["Content-Type: application/json; charset=utf-8"]);
        if($response->isSuccess()){
            if($response->content['code']=='000000'){
                return $response->content;
            }else{
                throw new \Exception($response->content['msg']);
            }
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function config()
    {
        $data = array(
            'agetId' => $this->config['agetId'],
            'custId' =>  $this->sub_merch_no,
            'wxAppid'=> site_config("addons.uniapp_mpapp_id"),
            'wxSecret'=> site_config("addons.uniapp_mpapp_secret"),
            'smallAppid'=> site_config("addons.uniapp_miniapp_id"),
            'smallSecret'=> site_config("addons.uniapp_miniapp_secret"),
            'wxQdh'=>  $this->config['wxQdh'],
            'mAppid'=> $this->config['mAppid'],
            'timeStamp'=> date('YmdHis'),
            'version'=> '1.0.0'
        );
        $data['sign']=$this->getSign($data);
        $response=Http::post(self::URL.'yyfsevr/custConfig/config',json_encode($data,JSON_UNESCAPED_UNICODE),[],["Content-Type: application/json; charset=utf-8"]);
        if($response->isSuccess()){
            if($response->content['code']=='000000'){
                return $response->content;
            }else{
                throw new \Exception($response->content['msg']);
            }
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function appidConfig()
    {
        $data = array(
            'agetId' => $this->config['agetId'],
            'custId' =>  $this->sub_merch_no,
            'appid' =>  site_config("addons.uniapp_miniapp_id"),
            'appsecret' =>  site_config("addons.uniapp_miniapp_secret"),
            'payType' =>  '02',
            'payWay' =>  '01',
            'wxSubMercid' =>  $this->config['wxSubMercid'],
            'mAppid'=> $this->config['mAppid'],
            'timeStamp'=> date('YmdHis'),
            'version'=> '1.0.0'
        );
        $data['sign']=$this->getSign($data);
        print_r(json_encode($data));
        $response=Http::post(self::URL.'yyfsevr/addCust/appidConfig',json_encode($data,JSON_UNESCAPED_UNICODE),[],["Content-Type: application/json; charset=utf-8"]);
        if($response->isSuccess()){
            if($response->content['code']=='000000'){
                return $response->content;
            }else{
                throw new \Exception($response->content['msg']);
            }
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function getReportInfo()
    {
        $data = array(
            'agetId' => $this->config['agetId'],
            'custId' =>  $this->sub_merch_no,
            'payWay' =>  '00',
            'timeStamp'=> date('YmdHis'),
            'version'=> '1.0.0'
        );
        $data['sign']=$this->getSign($data);
        $response=Http::post(self::URL.'yyfsevr/addCust/getReportInfo',json_encode($data,JSON_UNESCAPED_UNICODE),[],["Content-Type: application/json; charset=utf-8"]);
        if($response->isSuccess()){
            if($response->content['code']=='000000'){
                return $response->content;
            }else{
                throw new \Exception($response->content['msg']);
            }
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function refund()
    {
        $tag=[
            'wechat-miniapp'=>'2',
            'alipay'=>'1'
        ];
        $data = array(
            'orderNo' =>  're'.create_out_trade_no(),
            'agetId' =>  $this->config['agetId'],
            'custId' =>  $this->sub_merch_no,
            'tOrderNo' =>  $this->pay_union->transaction_id,
            'refundAmount' =>  (string)($this->refund_price*100),
            'tag' =>  $tag[$this->pay_union->pay_type],
            'timeStamp' =>  date('YmdHis',time()),
            'version' =>  '1.0.0',
        );
        $data['sign'] = $this->getSign($data);
        //print_r(json_encode($data,JSON_UNESCAPED_UNICODE));
        $response=Http::post(self::URL.'yyfsevr/order/refund',json_encode($data,JSON_UNESCAPED_UNICODE),[],["Content-Type: application/json; charset=utf-8"]);
        if($response->isSuccess()){
            if($response->content['code']=='000000'){
                return true;
            }else{
                throw new \Exception('退款失败，'.$response->content['msg']);
            }
        }
        throw new \Exception('退款失败');
    }

    public function notify()
    {
        //获取post传来的文本消息
        $json = file_get_contents("php://input");
        $orderdata=json_decode($json,true);
        if($this->checkSign($orderdata) && $orderdata['ORDER_STATUS']=='1'){
            $union=PayUnion::where('out_trade_no',$orderdata['THREE_ORDER_NO'])->find();
            if(!$union){
                return '';
            }
            if($union->pay_status==1){
                return 'SUCCESS';
            }
            if((int)$orderdata['TXAMT']!=intval($union->pay_price*100)){
                return '';
            }
            try{
                $union->paySuccess($orderdata['T_ORDER_NO']);
                return 'SUCCESS';
            }catch (\Exception $e){
                //print_r($e->getFile().'|'.$e->getLine().'|'.$e->getMessage());
                return '';
            }
        }
        return '';
    }

    private function pay(string $platform)
    {
        $payWay=[
            'mpapp'=>'1',
            'miniapp'=>'1',
            'alipay'=>'2',
        ];
        $traType=[
            'mpapp'=>'5',
            'miniapp'=>'8',
            'alipay'=>'',
        ];
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $union=PayUnion::wechatminiapp(PayUnion::PAY_TYPE_HANDLE('国通'),$user,$this->pay_price,$this->getHandlingFees(),$this->order_type,$this->attach,$this->order_body);
        $third=Third::where(['platform'=>$platform,'user_id'=>$this->user_id])->find();
        $data = array(
            'agetId' => $this->config['agetId'],
            'custId' =>  $this->sub_merch_no,
            'orderNo' => $union->out_trade_no,
            'txamt' =>  (string)($this->pay_price*100),
            'openid' =>  $third->openid,
            'payWay' =>  $payWay[$platform],
            'title' => $this->order_body,
            'ip' => request()->ip(),
            'wxAppid'=> site_config("addons.uniapp_miniapp_id"),
            'zfbappid'=> $this->config['zfbappid'],
            'traType'=> $traType[$platform],
            'limitPay'=> '0',
            'timeStamp'=> date('YmdHis'),
            'version'=> '1.0.0'
        );
        $data['sign']=$this->getSign($data);
        $response=Http::post(self::URL.'yyfsevr/order/pay',json_encode($data,JSON_UNESCAPED_UNICODE),[],["Content-Type: application/json; charset=utf-8"]);
        if($response->isSuccess()){
            if($response->content['code']=='000000'){
                $r['orderId']=$union->out_trade_no;
                $r['payInfo']=[
                    'appId'=>$response->content['data']['jsapiAppid'],
                    'timeStamp'=>$response->content['data']['jsapiTimestamp'],
                    'nonceStr'=>$response->content['data']['jsapiNoncestr'],
                    'package'=>$response->content['data']['jsapiPackage'],
                    'signType'=>$response->content['data']['jsapiSignType'],
                    'paySign'=>$response->content['data']['jsapiPaySign'],
                ];
                return $r;
            }else{
                throw new \Exception($response->content['msg']);
            }
        }
        throw new \Exception('支付失败');
    }

    private function getHandlingFees()
    {
        $persent=bcdiv($this->persent,'1000',4);
        $payprice=(string)($this->pay_price*100);
        $handling_fees=bcmul($payprice,$persent,1);
        $handling_fees=round(floatval($handling_fees));
        return $handling_fees;
    }

    private function checkSign($params)
    {
        ksort($params);
        $signstr = "";
        $str = "";
        foreach ($params as $key => $value) {
            if($key=='sign'){
                $signstr=$this->decode($value);
                continue;
            }
            $str .= $key.'='.trim($value).'&';
        }
        $str = substr($str, 0, -1);
        $sha256 = hash("sha256", $str);
        if($signstr==$sha256){
            return true;
        }
        return false;
    }

    private function decode($sign)
    {
        $sign = base64_decode($sign);
        $data = str_split($sign, 256);
        $key_pem = <<<EOT
-----BEGIN PUBLIC KEY-----
{$this->config['publicKey']}
-----END PUBLIC KEY-----
EOT;
        $pubKey = openssl_pkey_get_public($key_pem);
        $result = "";
        foreach ($data as $block) {
            openssl_public_decrypt($block, $dataDecrypt, $pubKey);
            $result .= $dataDecrypt;
        }
        return $result;
    }

    private function getSign($params)
    {
        ksort($params);
        $str = "";
        foreach ($params as $key => $value) {
            $str .= $key.'='.trim($value).'&';
        }
        $str = substr($str, 0, -1);
        $sha256 = hash("sha256", $str);
        $key_pem = <<<EOT
-----BEGIN PUBLIC KEY-----
{$this->config['publicKey']}
-----END PUBLIC KEY-----
EOT;
        $privateKey = openssl_pkey_get_public($key_pem); //检测是否公钥
        openssl_public_encrypt($sha256, $sign, $privateKey); //公钥加密
        $sign = base64_encode($sign);
        return $sign;
    }
}