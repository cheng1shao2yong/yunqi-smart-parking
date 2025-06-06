<?php
declare(strict_types=1);
namespace app\common\service\pay;

use app\common\library\Http;
use app\common\model\PayUnion;
use app\common\model\Third;
use app\common\service\PayService;
/**
 * 易宝支付
 */
class Yibao extends PayService {
    private $config;

    const MBPAYORDER='https://platform.mhxxkj.com/paygateway/mbpay/order/v1';
    const ORDERREFUND='https://platform.mhxxkj.com/paygateway/mbrefund/orderRefund/v1';

    protected function init()
    {
        $this->pay_type_handle='易宝支付';
        $this->config=[
            //服务商标识
            'mer_account'=>'',
            //服务商编号
            'mer_no'=>'',
            //私钥
            'private_key'=>'',
            //公钥
            'public_key'=>'',
            //最低费率
            'min_rate'=>0.28,
        ];
    }

    public function wechatMiniappPay()
    {
        $time=time();
        [$splitBillDetail,$handling_fees]=$this->getPayDetail();
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $union=PayUnion::wechatminiapp(PayUnion::PAY_TYPE_HANDLE('易宝支付'),$user,$this->pay_price,$handling_fees,$this->order_type,$this->attach,$this->order_body);
        $third=Third::where(['platform'=>'miniapp','user_id'=>$this->user_id])->find();
        $data = array(
            'merAccount' => $this->config['mer_account'],
            'merNo' =>  $this->config['mer_no'],
            'time' => (string)$time,
            'orderId' => $union->out_trade_no,//订单号
            'amount' => (string)($this->pay_price*100),//交易金额(分)
            'product' => PayUnion::ORDER_TYPE[$this->order_type],//商品
            'productDesc' => $this->order_body,//商品描述
            'payWay' => 'WEIXIN',
            'payType' => 'MINIAPP_WEIXIN',
            'openId' => $third->openid,
            'splitBillDetail'=>json_encode($splitBillDetail),
            'userIp' => request()->ip(),
            'notifyUrl' => request()->domain().'/index/notify/yibao'
        );
        if($this->attach){
            $data['attach']=$this->attach;
        }
        $data['sign'] = $this->getSign($data,$this->config['private_key']);
        $encrypt=$this->encryptData($data);
        $response=Http::post(self::MBPAYORDER,[
            'merAccount'=>$this->config['mer_account'],
            'data'=>$encrypt
        ]);
        if($response->isSuccess()){
            $content=json_decode($response->content,true);
            if($content['code']=='000000'){
                $content['data']['payInfo']=json_decode($content['data']['payInfo'],true);
                return $content['data'];
            }else{
                throw new \Exception($content['msg']);
            }
        }
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
        $time=time();
        [$splitBillDetail,$handling_fees]=$this->getPayDetail();
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $union=PayUnion::wechatminiapp(PayUnion::PAY_TYPE_HANDLE('易宝支付'),$user,$this->pay_price,$handling_fees,$this->order_type,$this->attach,$this->order_body);
        $third=Third::where(['platform'=>'alipay-mini','user_id'=>$this->user_id])->find();
        $data = array(
            'merAccount' => $this->config['mer_account'],
            'merNo' =>  $this->config['mer_no'],
            'time' => (string)$time,
            'orderId' => $union->out_trade_no,//订单号
            'amount' => (string)($this->pay_price*100),//交易金额(分)
            'product' => PayUnion::ORDER_TYPE[$this->order_type],//商品
            'productDesc' => $this->order_body,//商品描述
            'payWay' => 'ALIPAY',
            'payType' => 'MINIAPP_ALIPAY',
            'openId' => $third->openid,
            'splitBillDetail'=>json_encode($splitBillDetail),
            'userIp' => request()->ip(),
            'notifyUrl' => request()->domain().'/index/notify/yibao'
        );
        if($this->attach){
            $data['attach']=$this->attach;
        }
        $data['sign'] = $this->getSign($data,$this->config['private_key']);
        $encrypt=$this->encryptData($data);
        $response=Http::post(self::MBPAYORDER,[
            'merAccount'=>$this->config['mer_account'],
            'data'=>$encrypt
        ]);
        if($response->isSuccess()){
            $content=json_decode($response->content,true);
            if($content['code']=='000000'){
                $r=[];
                $r['orderId']=$union->out_trade_no;
                $r['payInfo']=[
                    'tradeNO'=>json_decode($content['data']['payInfo'],true)['tradeNo'],
                ];
                return $r;
            }else{
                throw new \Exception($content['msg']);
            }
        }
    }

    private function getQrcodePayType()
    {
        if(strlen($this->mediumNo)==18 && in_array(substr($this->mediumNo,0,2),['10','11','12','13','14','15'])){
            $paytype=[
                'payWay'=>'WEIXIN',
                'payType'=>'BARCODE_WEIXIN',
            ];
            return $paytype;
        }
        if(in_array(substr($this->mediumNo,0,2),['25','26','27','28','29','30'])){
            $paytype=[
                'payWay'=>'ALIPAY',
                'payType'=>'BARCODE_ALIPAY',
            ];
            return $paytype;
        }
        throw new \Exception('不支持的二维码类型');
    }

    public function qrcodePay()
    {
        $time=time();
        $paytype=$this->getQrcodePayType();
        [$splitBillDetail,$handling_fees]=$this->getPayDetail();
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $union=PayUnion::qrcodePay(PayUnion::PAY_TYPE_HANDLE('易宝支付'),$user,$this->pay_price,$handling_fees,$this->order_type,$this->attach,$this->order_body);
        $domain=get_domain('api');
        $data = array(
            'merAccount' => $this->config['mer_account'],
            'merNo' =>  $this->config['mer_no'],
            'time' => (string)$time,
            'orderId' => $union->out_trade_no,//订单号
            'amount' => (string)($this->pay_price*100),//交易金额(分)
            'product' => PayUnion::ORDER_TYPE[$this->order_type],//商品
            'productDesc' => $this->order_body,//商品描述
            'mediumNo'=>$this->mediumNo,
            'terminalId'=>$this->terminalId,
            'splitBillDetail'=>json_encode($splitBillDetail),
            'userIp' => request()->ip(),
            'payWay' => $paytype['payWay'],
            'payType' => $paytype['payType'],
            'notifyUrl' => $domain.'/index/notify/yibao'
        );
        if($this->attach){
            $data['attach']=$this->attach;
        }
        $data['sign'] = $this->getSign($data,$this->config['private_key']);
        $encrypt=$this->encryptData($data);
        $response=Http::post(self::MBPAYORDER,[
            'merAccount'=>$this->config['mer_account'],
            'data'=>$encrypt
        ]);
        if($response->isSuccess()){
            $content=json_decode($response->content,true);
            if($content['code']=='000000'){
                return true;
            }else{
                throw new \Exception($content['msg']);
            }
        }
    }

    public function wechatMpappPay()
    {
        throw new \Exception('暂不支持');
    }

    private function getPayDetail()
    {
        $min_persent=bcdiv((string)$this->config['min_rate'],'1000',4);
        $persent=bcdiv($this->persent,'1000',4);
        $payprice=(string)($this->pay_price*100);
        //易宝手续费
        $handling_fee_0=bcmul($payprice,$min_persent,1);
        $handling_fee_0=round(floatval($handling_fee_0));
        //总手续费
        $handling_fees=bcmul($payprice,$persent,1);
        $handling_fees=round(floatval($handling_fees));
        //实际到账金额
        $sub_price=bcsub($payprice,(string)$handling_fees);
        //分账金额
        $split_price=bcsub((string)$handling_fees,(string)$handling_fee_0);
        $r=array(
            [
                'subUserNo'=>$this->getSubMerchNo(),
                'splitBillType'=>2,
                'splitBillValue'=>(int)$sub_price
            ],
            [
                'subUserNo'=>$this->getSplitMerchNo(),
                'splitBillType'=>2,
                'splitBillValue'=>(int)$split_price
            ]
        );
        if($r[0]['subUserNo']==$r[1]['subUserNo']){
            throw new \Exception('分账商户号重复');
        }
        return [$r,$handling_fees];
    }

    public function refund()
    {
        $data = array(
            'merAccount' => $this->config['mer_account'],
            'merchantRefundNo' =>  're'.create_out_trade_no(),
            'orderId' =>  $this->pay_union->out_trade_no,
            'time'=>(string)time(),
            'refundCause'=>$this->refund_cause,
            'refundAmt' => (string)($this->refund_price*100),
        );
        $data['sign'] = $this->getSign($data,$this->config['private_key']);
        $encrypt=$this->encryptData($data);
        $response=Http::post(self::ORDERREFUND,[
            'merAccount'=>$this->config['mer_account'],
            'data'=>$encrypt
        ]);
        if($response->isSuccess()){
            $content=json_decode($response->content,true);
            if($content['code']=='000000'){
                return true;
            }else{
                throw new \Exception($content['msg']);
            }
        }
        throw new \Exception('退款失败');
    }

    public function notify()
    {
        $data=request()->get('data');
        $decrypt=$this->decryptData($data);
        $orderdata=json_decode($decrypt,true);
        if($this->checkSign($orderdata,$this->config['public_key']) && $orderdata['orderStatus']=='SUCCESS'){
            $union=PayUnion::where('out_trade_no',$orderdata['orderId'])->find();
            if(!$union){
                return '';
            }
            if($union->pay_status==1){
                return 'SUCCESS';
            }
            if((float)$orderdata['amount']!=(float)$union->pay_price){
                return '';
            }
            try{
                $union->paySuccess($orderdata['bankOrderNo']);
                return 'SUCCESS';
            }catch (\Exception $e){
                //print_r($e->getFile().'|'.$e->getLine().'|'.$e->getMessage());
                return '';
            }
        }
        return '';
    }

    private function getSign($params,$signKey)
    {
        ksort($params);
        $data = "";
        foreach ($params as $key => $value) {
            $data .= trim($value);
        }
        $sign = strtoupper(md5($data.$signKey));
        return $sign;
    }

    private function checkSign($params,$signKey)
    {
        ksort($params);
        $psign = "";
        $data = "";
        foreach ($params as $key => $value) {
            if($key == "sign") {
                $psign = $value;
            } else {
                $data .= trim($value);
            }
        }
        $sign = strtoupper(md5($data.$signKey));
        if($psign == $sign) {
            return true;
        } else {
            return false;
        }
    }


    //rsa加密
    private function encryptData(array $params)
    {
        $data=json_encode($params);
        $private_key="-----BEGIN PRIVATE KEY-----\r\n".$this->config['private_key']."\r\n-----END PRIVATE KEY-----";
        openssl_pkey_get_private($private_key);
        $encryptResult = "";
        foreach (str_split($data, 117) as $chunk) {
            $encrypted='';
            $r=openssl_private_encrypt($chunk,$encrypted,$private_key);
            if($r){
                $encryptResult.=$encrypted;
            }else{
                throw new \Exception(openssl_error_string());
            }
        }
        return base64_encode($encryptResult);
    }

    private function decryptData($data)
    {
        $data=base64_decode($data);
        $public_key="-----BEGIN PUBLIC KEY-----\r\n".$this->config['public_key']."\r\n-----END PUBLIC KEY-----";
        openssl_pkey_get_public($public_key);
        $decryptedResult = "";
        foreach (str_split($data, 128) as $chunk) {
            $decrypted='';
            $r=openssl_public_decrypt($chunk,$decrypted,$public_key);
            if($r){
                $decryptedResult.=$decrypted;
            }else{
                throw new \Exception(openssl_error_string());
            }
        }
        return $decryptedResult;
    }



}