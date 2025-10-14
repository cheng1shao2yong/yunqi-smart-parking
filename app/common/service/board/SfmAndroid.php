<?php
declare(strict_types=1);

namespace app\common\service\board;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRules;
use app\common\model\Qrcode;
use app\common\service\BoardService;

/**
 * 赛菲姆-安卓通道机
 */
class SfmAndroid extends BoardService
{
    //入场语音
    public static function entryVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType){
        throw new \Exception('暂不支持此功能');
    }

    //入场显示
    public static function entryDisplay(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType){
        throw new \Exception('暂不支持此功能');
    }

    //请缴费语音
    public static function payVoice(ParkingPlate $plate,ParkingRecordsPay $recordsPay){
        throw new \Exception('暂不支持此功能');
    }

    //免费离场语音
    public static function freeLeaveVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType){
        throw new \Exception('暂不支持此功能');
    }

    //免费离场显示
    public static function freeLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,string $rulesType){
        throw new \Exception('暂不支持此功能');
    }

    //请缴费显示
    public static function paidLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,ParkingRecordsPay $recordsPay,string $rulesType){
        throw new \Exception('暂不支持此功能');
    }

    //开闸异常显示
    public static function openGateExceptionDisplay(ParkingBarrier $barrier,string $message){
        throw new \Exception('暂不支持此功能');
    }

    //余额不足语音
    public static function insufficientBalanceVoice(){
        throw new \Exception('暂不支持此功能');
    }

    //支付成功语音
    public static function paySuccessVoice(){
        throw new \Exception('暂不支持此功能');
    }

    //支付成功显示
    public static function paySuccessScreen(ParkingBarrier $barrier,ParkingRecords $records){
        throw new \Exception('暂不支持此功能');
    }

    //禁止通行语音
    public static function noEntryVoice(){
        throw new \Exception('暂不支持此功能');
    }

    //人工确认语音
    public static function confirmVoice(string $plate_number){
        throw new \Exception('暂不支持此功能');
    }

    //人工确认显示
    public static function confirmDisplay(ParkingBarrier $barrier,string $plate_number){
        throw new \Exception('暂不支持此功能');
    }

    //设置广告
    public static function setAdvertisement(int $line,string $text){
        throw new \Exception('暂不支持此功能');
    }

    //设置音量
    public static function setVolume(int $step,int $voice){
        throw new \Exception('暂不支持此功能');
    }

    //无入场记录放行显示
    public static function noEntryRecordDisplay(ParkingBarrier $barrier){
        throw new \Exception('暂不支持此功能');
    }

    //无入场记录放行语音
    public static function noEntryRecordVoice(){
        throw new \Exception('暂不支持此功能');
    }

    //内场放行显示
    public static function insidePassDisplay(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    //显示出场付款码
    public static function showPayQRCode(ParkingBarrier $barrier)
    {
        $url=get_domain('api');
        $qrcode=$url.'/qrcode/exit?serialno='.$barrier->serialno;
        $data=[
            'voiceText'=>'',
            'paymentQrcode'=>$qrcode,
            'qrcodeType'=>0,
            'topText'=>'支付请扫码',
            'displayPageTimeout'=>$barrier->limit_pay_time,
        ];
        return $data;
    }

    //显示无牌车入场二维码
    public static function showEntryQRCode(ParkingBarrier $barrier)
    {
        $config=[
            'appid'=>site_config("addons.uniapp_mpapp_id"),
            'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
        ];
        $qrcode= Qrcode::createQrcode('parking-entry-qrcode',$barrier->serialno,60*15);
        $wechat=new \WeChat\Qrcode($config);
        $ticket = $wechat->create($qrcode->id,60*15)['ticket'];
        $url=$wechat->url($ticket);
        $data=[
            'voiceText'=>'',
            'paymentQrcode'=>$url,
            'qrcodeType'=>1,
            'topText'=>'无牌车入场请扫码',
            'displayPageTimeout'=>$barrier->limit_pay_time,
        ];
        return $data;
    }

    //显示无牌车出场二维码
    public static function showExitQRCode(ParkingBarrier $barrier)
    {
        $url=get_domain('api');
        $qrcode=$url.'/qrcode/exit?serialno='.$barrier->serialno;
        $data=[
            'voiceText'=>'',
            'paymentQrcode'=>$qrcode,
            'qrcodeType'=>0,
            'topText'=>'无牌车出场请扫码',
            'displayPageTimeout'=>$barrier->limit_pay_time,
        ];
        return $data;
    }

    //无牌车语音
    public static function noPlateVoice()
    {
        throw new \Exception('暂不支持此功能');
    }

    //无牌车显示
    public static function noPlateDisplay(ParkingBarrier $barrier,string $type)
    {
        throw new \Exception('暂不支持此功能');
    }
}