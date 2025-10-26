<?php
declare(strict_types=1);
namespace app\common\service\pay;

use app\common\library\Http;
use app\common\model\PayUnion;
use app\common\service\PayService;
use think\facade\Env;

/**
 * 收钱吧支付
 */
class Shouqianba extends PayService {

    const URL="https://vsi-api.shouqianba.com/";

    private $config;

    protected function init()
    {
        $this->pay_type_handle='收钱吧支付';
        $this->config=[
            'mpapp_app_id' =>  Env::get('SHOUQIANBA_MPAPP_ID'),
            'miniapp_app_id' => Env::get('SHOUQIANBA_MINIAPP_ID'),
            'vendor_sn' => Env::get('SHOUQIANBA_VENDOR_SN'),
            'vendor_key' => Env::get('SHOUQIANBA_VENDOR_KEY'),
            'public_key' => Env::get('SHOUQIANBA_PUBLIC_KEY')
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
        $union=PayUnion::wechatminiapp(PayUnion::PAY_TYPE_HANDLE('收钱吧支付'),$user,$this->pay_price,$handling_fees,$this->order_type,$this->attach,$this->order_body);
        $data=[
            'terminal_sn'=>$this->sub_merch_no,
            'client_sn'=>$union->out_trade_no,
            'total_amount'=>intval($union->pay_price*100),
            'subject'=>$this->order_body,
            'return_url' => '/pages/index/orderdetail?out_trade_no='.$union->out_trade_no,
            'notify_url' => request()->domain().'/index/notify/shouqianba'
        ];
        $sign=$this->paySign($data);
        $data['sign']=$sign;
        $result=[];
        $result['data']=$data;
        $result['payType']='shouqianba';
        $result['orderId']=$union->out_trade_no;
        return $result;
    }

    public function activateMpappAppid(string $code)
    {
        $data=[
            'app_id'=>$this->config['mpapp_app_id'],
            'code'=>$code,
            'device_id'=>'公众号-停车支付',
        ];
        $j_params = json_encode($data);
        $sign = md5($j_params .$this->config['vendor_key']);
        $response=Http::post(self::URL.'terminal/activate',$j_params,'',array(
            "Format:json",
            "Content-Type: application/json",
            "Authorization:".$this->config['vendor_sn']. ' '. $sign
        ));
        if($response->isSuccess()){
            print_r(json_encode($response->content,JSON_UNESCAPED_UNICODE));
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function activateMiniappAppid(string $code)
    {
        $data=[
            'app_id'=>$this->config['miniapp_app_id'],
            'code'=>$code,
            'device_id'=>'公众号-停车支付',
        ];
        $j_params = json_encode($data);
        $sign = md5($j_params .$this->config['vendor_key']);
        $response=Http::post(self::URL.'terminal/activate',$j_params,'',array(
            "Format:json",
            "Content-Type: application/json",
            "Authorization:".$this->config['vendor_sn']. ' '. $sign
        ));
        if($response->isSuccess()){
            print_r(json_encode($response->content,JSON_UNESCAPED_UNICODE));
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public function refund()
    {
        $data = [
            'terminal_sn'=>$this->sub_merch_no,
            'client_sn' => $this->pay_union->out_trade_no,
            'refund_request_no' => 're'.create_out_trade_no(),
            'refund_amount'=>(string)intval($this->refund_price*100),
            'operator'=>request()->ip()
        ];
        $j_params = json_encode($data);
        $sign = md5($j_params .$this->sub_merch_key);
        $response=Http::post(self::URL.'upay/v2/refund',$j_params,'',array(
            "Format:json",
            "Content-Type: application/json",
            "Authorization:".$this->sub_merch_no. ' '. $sign
        ));
        if($response->isSuccess()){
            if(
                $response->content['result_code']=='200'
                && $response->content['biz_response']['result_code']=='REFUND_SUCCESS'
            ){
                return true;
            }
            if(
                $response->content['result_code']=='200'
                && $response->content['biz_response']['result_code']=='FAIL'
            ){
                throw new \Exception($response->content['biz_response']['error_message']);
            }
        }else{
            throw new \Exception($response->errorMsg);
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
        $header=request()->header('Authorization');
        $postdata=file_get_contents('php://input');
        if($this->verifySign($header,$postdata)){
            event('write_log',$postdata);
            $respdata=json_decode($postdata,true);
            if($respdata['status']=='SUCCESS' && $respdata['order_status']=='PAID'){
                $out_trade_no=$respdata['client_sn'];
                $amount=$respdata['total_amount'];
                $transaction_id=$respdata['sn'];
                $union=PayUnion::where('out_trade_no',$out_trade_no)->find();
                if(!$union){
                    return '';
                }
                if($union->pay_status==1){
                    return 'success';
                }
                if($amount!=intval($union->pay_price*100)){
                    return '';
                }
                try{
                    $union->paySuccess($transaction_id);
                    return 'success';
                }catch (\Exception $e){
                    //print_r($e->getFile().'|'.$e->getLine().'|'.$e->getMessage());
                    event('write_log','交易异常:'.$e->getMessage());
                    return '';
                }
            }
        }
        return '';
    }

    public function paySign($params)
    {
        ksort($params);
        $param_str = "";
        foreach ($params as $k => $v) {
            $param_str .= $k .'='.$v.'&';
        }
        $sign = strtoupper(md5($param_str.'key='.$this->sub_merch_key));
        return $sign;
    }

    private function verifySign(string $sign, string $body): bool
    {
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($this->config['public_key'], 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        $decodedSign = base64_decode($sign);
        $publicKeyResource = openssl_get_publickey($publicKey);
        if (!$publicKeyResource) {
            return false;
        }
        $result = openssl_verify($body, $decodedSign, $publicKeyResource, OPENSSL_ALGO_SHA256);
        return $result === 1;
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