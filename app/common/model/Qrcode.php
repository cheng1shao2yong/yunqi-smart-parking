<?php

namespace app\common\model;

use app\common\model\base\ConstTraits;
use think\Model;

/**
 * 二维码展示模型
 */
class Qrcode extends Model
{
    use ConstTraits;

    const TYPE=[
        'backend-login'=>'管理员扫码登录',
        'parking-login'=>'停车场扫码登录',
        'merchant-login'=>'商户PC扫码登录',
        'bind-third-user'=>'绑定第三方账号',
        'merchant-static-qrcode'=>'商家固定优惠券',
        'merchant-dynamic-qrcode'=>'商家动态优惠券',
        'parking-entry-qrcode'=>'停车入场二维码',
        'parking-mpapp-index'=>'关注公众号二维码',
        'parking-entry-apply'=>'停车场预约车申请码',
        'merchant-entry-apply'=>'商户预约车申请码',
    ];

    const TYPERECREATE=[
        'backend-login'=>false,
        'parking-login'=>false,
        'merchant-login'=>false,
        'bind-third-user'=>false,
        'merchant-static-qrcode'=>false,
        'parking-entry-qrcode'=>false,
        'parking-mpapp-index'=>false,
        'merchant-dynamic-qrcode'=>true,
        'parking-entry-apply'=>false,
        'merchant-entry-apply'=>false
    ];

    public static function createQrcode(string $type,mixed $foreign_key,int $expiretime,$set_mpapp_scan=1)
    {
        $expiretime=time()+$expiretime;
        if(is_array($foreign_key)){
            $foreign_key=json_encode($foreign_key,JSON_UNESCAPED_UNICODE);
        }
        $recreate=self::TYPERECREATE[$type];
        $key=md5($type.$foreign_key);
        if(!$recreate){
            $qrcode=self::where('key',$key)->order('id desc')->find();
            if($qrcode && $qrcode->expiretime>time()){
                return $qrcode;
            }
        }
        $qrcode=new self();
        $qrcode['type']=$type;
        $qrcode->expiretime=$expiretime;
        $qrcode->foreign_key=$foreign_key;
        $qrcode->set_mpapp_scan=$set_mpapp_scan;
        $qrcode['key']=$key;
        $qrcode->save();
        return $qrcode;
    }
}
