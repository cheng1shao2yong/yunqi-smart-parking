<?php

declare(strict_types=1);
namespace app\common\service\charge;

use app\common\model\Accesskey;
use app\common\model\parking\ParkingCharge;
use app\common\service\BaseService;
use app\api\service\ApiAuthService;

class Telaidian extends BaseService {

    //const URL="http://hlht.teld.cc:7777/evcs/v20191230/";
    const URL="https://hlht.teld.cn/evcs/v20161110/";

    const OPERATOR_ID='';
    const OPERATOR_SECRET='';
    const SIGN_SECRET='';
    const AES_SECRET='';
    const AES_IV='';

    protected function init()
    {

    }

    public static function getAccessToken()
    {
        $token=md5(time().rand(1000,9999));
        $data=[
            'OperatorID'=>self::OPERATOR_ID,
            'SuccStat'=>0,
            'AccessToken'=>$token,
            'TokenAvailableTime'=>24*3600,
            'FailReason'=>0,
        ];
        $encrypt=self::encryptString($data);
        return $encrypt;
    }

    public static function run($str)
    {
        $result=self::decryptString($str);
        $ParkID=$result['ParkID'];
        $PlateNum=$result['PlateNum'];
        $Power=$result['Power'];
        $TotalMoney=$result['TotalMoney'];
        $time=strtotime($result['EndTime'])-strtotime($result['StartTime']);
        /* @var ParkingCharge $charge*/
        $charge=ParkingCharge::where(['code'=>$ParkID,'channel'=>'telaidian'])->find();
        if(!$charge){
            throw new \Exception('未配置收费规则');
        }
        $charge->send($PlateNum,$TotalMoney,$Power,$time);
        $data=[
            'StartChargeSeq'=>$result['StartChargeSeq'],
            'ConfirmResult'=>0,
            'PlateAutResult'=>1,
            'PlateAutFailReason'=>0,
        ];
        $encrypt=self::encryptString($data);
        $result=[
            'Ret'=>0,
            'Msg'=>'',
            'Data'=>$encrypt,
            'Sig'=>md5(date('YmdHis'))
        ];
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    public static function sign($postdata)
    {
        $sig=self::OPERATOR_ID.$postdata['Data'].$postdata['TimeStamp'].$postdata['Seq'];
        $rs=self::hmac_md5($sig);
        return $rs;
    }

    private static function hmac_md5($s)
    {
        $ctx = hash_init('md5', HASH_HMAC,self::SIGN_SECRET);
        hash_update($ctx, $s);
        $rs  = hash_final($ctx);
        $rs  = strtoupper($rs);
        return $rs;
    }

    private static function encryptString($data)
    {
        $plaintext = json_encode($data,JSON_UNESCAPED_UNICODE);
        $ciphertext = openssl_encrypt($plaintext, "aes-128-cbc", self::AES_SECRET, OPENSSL_PKCS1_PADDING, self::AES_IV);
        return base64_encode($ciphertext);
    }

    private static function decryptString($encryptStr)
    {
        $encryptStr = base64_decode($encryptStr);
        $decryptStr = openssl_decrypt($encryptStr, "aes-128-cbc", self::AES_SECRET, OPENSSL_PKCS1_PADDING, self::AES_IV);
        return json_decode($decryptStr,true);
    }
}