<?php
/**
 * ----------------------------------------------------------------------------
 * 行到水穷处，坐看云起时
 * 开发软件，找贵阳云起信息科技，官网地址:https://www.56q7.com/
 * ----------------------------------------------------------------------------
 * Author: 老成
 * email：85556713@qq.com
 */
declare(strict_types=1);

namespace app\common\service\contactless;

use app\common\model\parking\ParkingContactless;
use app\common\model\parking\ParkingRecords;
use app\common\model\PayUnion;
use app\common\service\ContactlessService;
use Swoole\Coroutine;
use think\facade\Env;
use think\facade\Log;

class Hzbrain extends ContactlessService
{
    const URL="http://220.191.209.248:8990";

    const PLATE_COLOR=[
        'blue'=>1,
        'yellow'=>2,
        'yellow-green'=>5,
        'white'=>3,
        'black'=>4,
        'green'=>5,
    ];

    //申请支付
    public function applyPayment(ParkingContactless $contactless,ParkingRecords $records,PayUnion $union)
    {
        $post=[
            'parkingCode'=>$contactless->parking_code,
            'uid'=>$contactless->parking_code.$records->entry_barrier.date('YmdHis',$records->entry_time),
            'plateNo'=>$records->plate_number,
            'plateColor'=>self::PLATE_COLOR[$records->plate_type],
            'arriveTime'=>date('Y-m-d H:i:s',$records->entry_time),
            'endTime'=>date('Y-m-d H:i:s',$records->exit_time),
            'billID'=>$union->out_trade_no,
            'billTime'=>$union->createtime.":".substr($union->out_trade_no,12,2),
            'shouldPay'=>intval($union->pay_price*100),
            'payStatus'=>0,
            'carStock'=>1
        ];
        $privatekey=Env::get('TRAFFIC_PRIVATE_KEY');
        $accessid=Env::get('TRAFFIC_ACCESSID');
        $package=$this->pack($post,$privatekey);
        $url=self::URL."/api/v2/cp/applyPayment";
        $this->sendRequest($url,[
            'accessID'=>$accessid,
            'sign'=>self::sign($package,$privatekey),
            'cipher'=>$package
        ]);
    }

    //处理支付结果
    public function payResult(array $result)
    {
        Log::record(json_encode($result,JSON_UNESCAPED_UNICODE));
        if($result['payStatus']!=1){
            return;
        }
        $out_trade_no=$result['billID'];
        $union=PayUnion::where('out_trade_no',$out_trade_no)->find();
        if(!$union){
            return;
        }
        if($union->pay_status==1){
            return;
        }
        if($result['actualPay']!=intval($union->pay_price*100)){
            return;
        }
        try{
            $union->paySuccess($result['orderCode']);
        }catch (\Exception $e){
            Log::record('write_log','交易异常:'.$e->getMessage());
        }
    }

    private function sendRequest($url, $params = [])
    {
        $protocol = substr($url, 0, 5);
        $query_string = is_array($params) ? http_build_query($params) : $params;
        $ch = curl_init();
        $defaults = [];
        $defaults[CURLOPT_URL] = $url;
        $defaults[CURLOPT_CUSTOMREQUEST] = 'POST';
        $defaults[CURLOPT_POSTFIELDS] = $query_string;
        $defaults[CURLOPT_HEADER] = false;
        $defaults[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.98 Safari/537.36";
        $defaults[CURLOPT_FOLLOWLOCATION] = true;
        $defaults[CURLOPT_RETURNTRANSFER] = true;
        $defaults[CURLOPT_CONNECTTIMEOUT] = 3;
        $defaults[CURLOPT_TIMEOUT] = 10;
        $options['Expect']='';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
        if ('https' == $protocol) {
            $defaults[CURLOPT_SSL_VERIFYPEER] = false;
            $defaults[CURLOPT_SSL_VERIFYHOST] = false;
        }
        curl_setopt_array($ch,$defaults);
        $ret = curl_exec($ch);
        $err = curl_error($ch);
        $error='';
        $result='';
        if (false === $ret || !empty($err)) {
            $error="HTTP请求失败: " . $err;
        }else{
            $statusCode=curl_getinfo($ch,CURLINFO_HTTP_CODE);
            if($statusCode==200) {
                $result=json_decode($ret,true);
            }else{
                $error="HTTP请求失败: " . $ret;
            }
        }
        curl_close($ch);
        if($error){
            throw new \Exception($error);
        }
        return $result;
    }

    public static function verifySign(string $content, string $signature,string $publicKey): bool
    {
        $public_key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($publicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        $publicKeyId = openssl_pkey_get_public($public_key);
        $decodedSignature = base64_decode($signature, true);
        $result = openssl_verify($content, $decodedSignature, $publicKeyId, 'md5WithRSAEncryption');
        return $result === 1;
    }

    public static function decrypt(string $encryptedData,string $publicKey): array
    {
        $encryptedData = base64_decode($encryptedData);
        $public_key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($publicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        $publicKeyId = openssl_pkey_get_public($public_key);
        $decryptedResult = "";
        foreach (str_split($encryptedData, 128) as $chunk) {
            $decrypted = '';
            $result = openssl_public_decrypt($chunk, $decrypted, $publicKeyId);
            if ($result) {
                $decryptedResult .= $decrypted;
            } else {
                throw new \Exception("解密失败: " . openssl_error_string());
            }
        }
        $data = json_decode($decryptedResult, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON解码失败: " . json_last_error_msg());
        }
        return $data;
    }

    public static function pack($data,string $privatekey)
    {
        return self::encrypt(['data'=>$data],$privatekey);
    }

    public static function sign(string $content,string $privatekey): string
    {
        $private_key = "-----BEGIN PRIVATE KEY-----\n" . wordwrap($privatekey, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
        $privateKeyId = openssl_pkey_get_private($private_key);
        $signature = '';
        if (!openssl_sign($content, $signature, $privateKeyId, 'md5WithRSAEncryption')) {
            throw new \Exception("Signing failed");
        }
        // 返回 Base64 编码的签名
        return base64_encode($signature);
    }

    public static function encrypt(array $params,string $privatekey)
    {
        $data=json_encode($params);
        $private_key = "-----BEGIN PRIVATE KEY-----\n" . wordwrap($privatekey, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
        $privateKeyId = openssl_pkey_get_private($private_key);
        $encryptResult = "";
        foreach (str_split($data, 117) as $chunk) {
            $encrypted='';
            $r=openssl_private_encrypt($chunk,$encrypted,$privateKeyId);
            if($r){
                $encryptResult.=$encrypted;
            }else{
                throw new \Exception(openssl_error_string());
            }
        }
        $result=base64_encode($encryptResult);
        return $result;
    }

}