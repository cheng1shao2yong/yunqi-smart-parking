<?php
declare(strict_types=1);
namespace app\common\service\pay;

use app\common\library\Http;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingDailyCashFlow;
use app\common\model\parking\ParkingDailySettle;
use app\common\model\PayUnion;
use app\common\model\Third;
use app\common\service\PayService;
use think\facade\Env;

/**
 * 斗拱支付
 */
class Dougong extends PayService {

    private $config;

    const URL='https://api.huifu.com/v3/trade/payment/jspay';

    const QRCODEURL='https://api.huifu.com/v3/trade/payment/micropay';

    const BALANCEPAY='https://api.huifu.com/v2/trade/acctpayment/pay';

    const ORDERREFUND='https://api.huifu.com/v3/trade/payment/scanpay/refund';

    protected function init()
    {
        $this->pay_type_handle='斗拱';
        $this->config=[
            'private_key'=>Env::get('DOUGONG_PRIVATE_KEY'),
            'public_key'=>Env::get('DOUGONG_PUBLIC_KEY'),
            'sys_id'=>Env::get('DOUGONG_SYS_ID')
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
        $union=PayUnion::wechatminiapp(PayUnion::PAY_TYPE_HANDLE('斗拱支付'),$user,$this->pay_price,$handling_fees,$this->order_type,$this->attach,$this->order_body);
        $third=Third::where(['platform'=>'miniapp','user_id'=>$this->user_id])->find();
        $postdata=[
            'req_date'=>date('Ymd'),
            'req_seq_id'=>'DG'.$union->out_trade_no,
            'huifu_id'=>$this->config['sys_id'],
            'goods_desc'=>$this->order_body,
            'trade_type'=>'T_MINIAPP',
            'trans_amt'=> number_format((float)$union->pay_price,2, '.',''),
            'notify_url'=>request()->domain().'/index/notify/dougong',
            'wx_data'=>json_encode([
                'sub_appid'=>site_config("addons.uniapp_miniapp_id"),
                'sub_openid'=>$third->openid,
                'body'=>$this->order_body,
            ],JSON_UNESCAPED_UNICODE)
        ];
        $postdata=$this->parseData($postdata);
        $response=Http::post(self::URL,$postdata,'',['Content-Type: application/json','Content-Length: '.strlen($postdata)]);
        if($response->isSuccess()){
            $content=$response->content;
            if($content['data']['trans_stat']=='P'){
                $result=[];
                $result['payInfo']=json_decode($content['data']['pay_info'],true);
                $result['orderId']=$union->out_trade_no;
                $result['payType']='dougong';
                return $result;
            }else{
                throw new \Exception($content['data']['resp_desc']);
            }
        }
    }

    public function refund()
    {
        $postdata = array(
            'req_date'=>date('Ymd'),
            'req_seq_id'=>'re'.create_out_trade_no(),
            'huifu_id'=>$this->config['sys_id'],
            'ord_amt'=>number_format($this->refund_price,2, '.',''),
            'org_req_date'=>date('Ymd',strtotime($this->pay_union->createtime)),
            'org_party_order_id'=>$this->pay_union->transaction_id
        );
        $postdata=$this->parseData($postdata);
        $response=Http::post(self::ORDERREFUND,$postdata,'',['Content-Type: application/json','Content-Length: '.strlen($postdata)]);
        if($response->isSuccess()){
            $content=$response->content;
            if($content['data']['trans_stat']=='P'){
                return true;
            }else{
                throw new \Exception($content['data']['resp_desc']);
            }
        }
        throw new \Exception('退款失败');
    }

    public function alipayPcPay()
    {
        // TODO: Implement alipayPcPay() method.
    }

    public function mpAlipay()
    {
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $handling_fees=$this->handlingFee();
        $union=PayUnion::wechatminiapp(PayUnion::PAY_TYPE_HANDLE('斗拱支付'),$user,$this->pay_price,$handling_fees,$this->order_type,$this->attach,$this->order_body);
        $third=Third::where(['platform'=>'alipay-mini','user_id'=>$this->user_id])->find();
        $postdata=[
            'req_date'=>date('Ymd'),
            'req_seq_id'=>'DG'.$union->out_trade_no,
            'huifu_id'=>$this->config['sys_id'],
            'trade_type'=>'A_JSAPI',
            'trans_amt'=> number_format((float)$union->pay_price,2, '.',''),
            'goods_desc'=>$this->order_body,
            'notify_url'=>request()->domain().'/index/notify/dougong',
            'alipay_data'=>json_encode([
                'op_app_id'=>site_config("addons.uniapp_alimini_id"),
                'buyer_id'=>$third->openid,
            ],JSON_UNESCAPED_UNICODE)
        ];
        $postdata=$this->parseData($postdata);
        $response=Http::post(self::URL,$postdata,'',['Content-Type: application/json','Content-Length: '.strlen($postdata)]);
        if($response->isSuccess()){
            $content=$response->content;
            if($content['data']['trans_stat']=='P'){
                $result=[];
                $result['payInfo']=json_decode($content['data']['pay_info'],true);
                $result['orderId']=$union->out_trade_no;
                $result['payType']='dougong';
                return $result;
            }else{
                throw new \Exception($content['data']['resp_desc']);
            }
        }
    }

    public function wechatPcPay()
    {
        // TODO: Implement wechatPcPay() method.
    }

    public function wechatMpappPay()
    {
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $handling_fees=$this->handlingFee();
        $union=PayUnion::wechatmpapp(PayUnion::PAY_TYPE_HANDLE('斗拱支付'),$user,$this->pay_price,$handling_fees,$this->order_type,$this->attach,$this->order_body);
        $third=Third::where(['platform'=>'mpapp','user_id'=>$this->user_id])->find();
        $postdata=[
            'req_date'=>date('Ymd'),
            'req_seq_id'=>'DG'.$union->out_trade_no,
            'huifu_id'=>$this->config['sys_id'],
            'goods_desc'=>$this->order_body,
            'trade_type'=>'T_JSAPI',
            'trans_amt'=> number_format((float)$union->pay_price,2, '.',''),
            'notify_url'=>request()->domain().'/index/notify/dougong',
            'wx_data'=>json_encode([
                'sub_appid'=>site_config("addons.uniapp_mpapp_id"),
                'sub_openid'=>$third->openid,
                'body'=>$this->order_body,
            ],JSON_UNESCAPED_UNICODE)
        ];
        $postdata=$this->parseData($postdata);
        $response=Http::post(self::URL,$postdata,'',['Content-Type: application/json','Content-Length: '.strlen($postdata)]);
        if($response->isSuccess()){
            $content=$response->content;
            if($content['data']['trans_stat']=='P'){
                $result=[];
                $result['payInfo']=json_decode($content['data']['pay_info'],true);
                $result['orderId']=$union->out_trade_no;
                $result['payType']='dougong';
                return $result;
            }else{
                throw new \Exception($content['data']['resp_desc']);
            }
        }
    }

    public function qrcodePay()
    {
        $user=[
            'user_id'=>$this->user_id,
            'parking_id'=>$this->parking_id,
            'property_id'=>$this->property_id,
        ];
        $handling_fees=$this->handlingFee();
        $union=PayUnion::qrcodePay(PayUnion::PAY_TYPE_HANDLE('斗拱支付'),$user,$this->pay_price,$handling_fees,$this->order_type,$this->attach,$this->order_body);
        $domain=get_domain('api');
        [$type,$trade_type,$payinfo]=$this->getQrcodePayType();
        $postdata=[
            'req_date'=>date('Ymd'),
            'req_seq_id'=>'DG'.$union->out_trade_no,
            'huifu_id'=>$this->config['sys_id'],
            'trans_amt'=> number_format((float)$union->pay_price,2, '.',''),
            'goods_desc'=>$this->order_body,
            'auth_code'=>$this->mediumNo,
            'trade_type'=>$trade_type,
            'notify_url'=>$domain.'/index/notify/dougong',
            'risk_check_data'=>json_encode([
                'ip_addr'=>'192.168.1.1',
            ],JSON_UNESCAPED_UNICODE)
        ];
        $postdata[$type]=$payinfo;
        $postdata=$this->parseData($postdata);
        $response=Http::post(self::QRCODEURL,$postdata,'',['Content-Type: application/json','Content-Length: '.strlen($postdata)]);
        if($response->isSuccess()){
            $content=$response->content;
            if($content['data']['trans_stat']=='S'){
                return true;
            }else{
                throw new \Exception($content['data']['resp_desc']);
            }
        }
    }

    public function settle(Parking $parking,string $date)
    {
        $settle=ParkingDailySettle::where(['parking_id'=>$parking->id,'date'=>$date,'pay_depart'=>'dougong','status'=>1])->find();
        if($settle){
            return;
        }
        $flow=ParkingDailyCashFlow::where(['parking_id'=>$parking->id,'date'=>$date])->find();
        if($flow && $flow->net_income>0){
            $out_trade_no=create_out_trade_no();
            $savedata=[
                'parking_id'=>$parking->id,
                'out_trade_no'=>$out_trade_no,
                'date'=>$date,
                'price'=>$flow->net_income,
                'pay_depart'=>'dougong',
                'createtime'=>time()
            ];
            $postdata=[
                'req_seq_id'=>$out_trade_no,
                'req_date'=>date('Ymd'),
                'out_huifu_id'=>$this->config['sys_id'],
                'ord_amt'=>number_format(floatval($flow->net_income),2, '.',''),
                'risk_check_data'=>json_encode([
                    'sub_product'=>'1',
                    'transfer_type'=>'05'
                ],JSON_UNESCAPED_UNICODE),
                'acct_split_bunch'=>json_encode([
                    'acct_infos'=>array([
                        'div_amt'=>number_format(floatval($flow->net_income),2, '.',''),
                        'huifu_id'=>$parking->sub_merch_no,
                    ])
                ],JSON_UNESCAPED_UNICODE)
            ];
            $postdata=$this->parseData($postdata);
            $response=Http::post(self::BALANCEPAY,$postdata,'',['Content-Type: application/json','Content-Length: '.strlen($postdata)]);
            if($response->isSuccess()){
                $content=$response->content;
                if($content['data']['trans_stat']=='S'){
                    $savedata['status']=1;
                    (new ParkingDailySettle())->save($savedata);
                }else{
                    $savedata['status']=2;
                    $savedata['error']=$content['data']['resp_desc'];
                    (new ParkingDailySettle())->save($savedata);
                }
            }else{
                $savedata['status']=2;
                $savedata['error']=$response->errorMsg;
                (new ParkingDailySettle())->save($savedata);
            }
        }
    }

    public function payOne(Parking $parking,float $price)
    {
        //数据加锁
        $out_trade_no=create_out_trade_no();
        $postdata=[
            'req_seq_id'=>$out_trade_no,
            'req_date'=>date('Ymd'),
            'out_huifu_id'=>$this->config['sys_id'],
            'ord_amt'=>number_format($price,2, '.',''),
            'risk_check_data'=>json_encode([
                'sub_product'=>'1',
                'transfer_type'=>'05'
            ],JSON_UNESCAPED_UNICODE),
            'acct_split_bunch'=>json_encode([
                'acct_infos'=>array([
                    'div_amt'=>number_format($price,2, '.',''),
                    'huifu_id'=>$parking->sub_merch_no,
                ])
            ],JSON_UNESCAPED_UNICODE)
        ];
        $postdata=$this->parseData($postdata);
        $response=Http::post(self::BALANCEPAY,$postdata,'',['Content-Type: application/json','Content-Length: '.strlen($postdata)]);
        if($response->isSuccess()){
            $content=$response->content;
            if($content['data']['trans_stat']=='S'){
                echo 'SUCCESS';
                return;
            }
        }
        echo 'FAIL';
    }

    public function payMerch(string $sub_merch_no,float $price)
    {
        //数据加锁
        $out_trade_no=create_out_trade_no();
        $postdata=[
            'req_seq_id'=>$out_trade_no,
            'req_date'=>date('Ymd'),
            'out_huifu_id'=>$this->config['sys_id'],
            'ord_amt'=>number_format($price,2, '.',''),
            'risk_check_data'=>json_encode([
                'sub_product'=>'1',
                'transfer_type'=>'05'
            ],JSON_UNESCAPED_UNICODE),
            'acct_split_bunch'=>json_encode([
                'acct_infos'=>array([
                    'div_amt'=>number_format($price,2, '.',''),
                    'huifu_id'=>$sub_merch_no,
                ])
            ],JSON_UNESCAPED_UNICODE)
        ];
        $postdata=$this->parseData($postdata);
        $response=Http::post(self::BALANCEPAY,$postdata,'',['Content-Type: application/json','Content-Length: '.strlen($postdata)]);
        if($response->isSuccess()){
            $content=$response->content;
            if($content['data']['trans_stat']=='S'){
                return;
            }
        }
    }

    public function notify()
    {
        $res=request()->post();
        $respdata=htmlspecialchars_decode($res['resp_data']);
        if($this->checkSign($respdata,$res['sign'])){
            event('write_log',$respdata);
            $respdata=json_decode($respdata,true);
            if($respdata['trans_stat']=='S' && intval($respdata['notify_type'])==1){
                $out_trade_no=substr($respdata['mer_ord_id'],2);
                $union=PayUnion::where('out_trade_no',$out_trade_no)->find();
                if(!$union){
                    return '';
                }
                if($union->pay_status==1){
                    return 'SUCCESS';
                }
                if((float)$respdata['trans_amt']!=(float)$union->pay_price){
                    return '';
                }
                try{
                    $union->paySuccess($respdata['party_order_id']);
                    return 'SUCCESS';
                }catch (\Exception $e){
                    //print_r($e->getFile().'|'.$e->getLine().'|'.$e->getMessage());
                    event('write_log','交易异常:'.$e->getMessage());
                    return '';
                }
            }
        }
    }

    private function getQrcodePayType()
    {
        if(strlen($this->mediumNo)==18 && in_array(substr($this->mediumNo,0,2),['10','11','12','13','14','15'])){
            return [
                'wx_data',
                'T_MICROPAY',
                json_encode([
                    'sub_appid'=>site_config("addons.uniapp_miniapp_id"),
                    'device_info'=>$this->terminalId,
                ],JSON_UNESCAPED_UNICODE),
            ];
        }
        if(in_array(substr($this->mediumNo,0,2),['25','26','27','28','29','30'])){
            return [
                'alipay_data',
                'A_MICROPAY',
                json_encode([
                    'op_app_id'=>site_config("addons.uniapp_alimini_id"),
                    'operator_id'=>$this->terminalId,
                ],JSON_UNESCAPED_UNICODE)
            ];
        }
        throw new \Exception('不支持的二维码类型');
    }

    private function handlingFee()
    {
        $persent=bcdiv($this->persent,'1000',4);
        $payprice=(string)($this->pay_price*100);
        $handling_fees=bcmul($payprice,$persent,1);
        $handling_fees=round(floatval($handling_fees));
        return $handling_fees;
    }

    private function parseData(array $postdata)
    {
        $result=[
            'sys_id'=>$this->config['sys_id'],
            'product_id'=>'XLSISV',
            'data'=>$postdata
        ];
        $result['sign']=$this->sign($postdata);
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    private function sign(array $params):string
    {
        ksort($params);
        $str=json_encode($params, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $privateString=$this->config['private_key'];
        $private_key = <<<EOT
-----BEGIN RSA PRIVATE KEY-----
{$privateString}
-----END RSA PRIVATE KEY-----
EOT;
        $signature= '';
        openssl_sign($str, $signature, $private_key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    private function checkSign(string $data,string $sign)
    {
        $publicString=$this->config['public_key'];
        $public_key = <<<EOT
-----BEGIN PUBLIC KEY-----
{$publicString}
-----END PUBLIC KEY-----
EOT;
        return openssl_verify($data, base64_decode($sign), $public_key, OPENSSL_ALGO_SHA256);
    }
}