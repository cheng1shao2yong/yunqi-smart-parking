<?php
declare(strict_types=1);
namespace app\common\service;

use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;

abstract class BoardService{

    const MESSAGE_ENTRY=[
        0=>'无',
        1=>'欢迎光临',
        20=>'请入场停车',
        39=>'欢迎回家',
        40=>'请通行'
    ];

    const MESSAGE_EXIT=[
        0=>'无',
        2=>'一路平安',
        40=>'请通行',
        95=>'一路顺风',
        99=>'出入平安'
    ];

    const SCREEN_ACTIONS=[
        '入场显示',
        '请缴费显示',
        '免费离场显示',
        '开闸异常显示',
        '人工确认显示',
        '余额不足显示',
        '设置广告',
        '无入场记录放行显示',
        '内场放行显示',
        '显示出场付款码',
        '显示无牌车入场二维码',
        '显示无牌车出场二维码',
        '无牌车显示',
        '支付成功显示',
    ];

    const VOICE_ACTIONS=[
        '入场语音',
        '请缴费语音',
        '免费离场语音',
        '余额不足语音',
        '禁止通行语音',
        '人工确认语音',
        '设置音量',
        '无入场记录放行语音',
        '无牌车语音',
        '支付成功语音',
    ];

    //入场语音
    abstract public static function entryVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType);
    //入场显示
    abstract public static function entryDisplay(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType);
    //请缴费语音
    abstract public static function payVoice(ParkingPlate $plate,ParkingRecordsPay $recordsPay);
    //免费离场语音
    abstract public static function freeLeaveVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType);
    //请缴费显示
    abstract public static function paidLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,ParkingRecordsPay $recordsPay,string $rulesType);
    //免费离场显示
    abstract public static function freeLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,string $rulesType);
    //开闸异常显示
    abstract public static function openGateExceptionDisplay(ParkingBarrier $barrier,string $message);
    //余额不足语音
    abstract public static function insufficientBalanceVoice();
    //余额不足显示
    abstract public static function insufficientBalanceScreen(ParkingBarrier $barrier,string $plate_number);
    //支付成功语音
    abstract public static function paySuccessVoice();
    //支付成功显示
    abstract public static function paySuccessScreen(ParkingBarrier $barrier,ParkingRecords $records);
    //禁止通行语音
    abstract public static function noEntryVoice();
    //人工确认语音
    abstract public static function confirmVoice(string $plate_number);
    //人工确认显示
    abstract public static function confirmDisplay(ParkingBarrier $barrier,string $plate_number);
    //设置广告
    abstract public static function setAdvertisement(int $line,string $text);
    //设置音量
    abstract public static function setVolume(int $step,int $voice);
    //无入场记录放行显示
    abstract public static function noEntryRecordDisplay(ParkingBarrier $barrier,string $plate_number);
    //无入场记录放行语音
    abstract public static function noEntryRecordVoice();
    //内场放行显示
    abstract public static function insidePassDisplay(ParkingBarrier $barrier,string $plate_number);
    //显示出场付款码
    abstract public static function showPayQRCode(ParkingBarrier $barrier);
    //显示无牌车入场二维码
    abstract public static function showEntryQRCode(ParkingBarrier $barrier);
    //显示无牌车出场二维码
    abstract public static function showExitQRCode(ParkingBarrier $barrier);
    //无牌车语音
    abstract public static function noPlateVoice(ParkingBarrier $barrier);
    //无牌车显示
    abstract public static function noPlateDisplay(ParkingBarrier $barrier);

    public static function isScreenAction(string $name)
    {
        return in_array($name,self::SCREEN_ACTIONS);
    }

    public static function isVoiceAction(string $name)
    {
        return in_array($name,self::VOICE_ACTIONS);
    }

    public static function getQrcodeView(string $top,string $footer,string $qrcodeUrl='',string $qrcodeText='')
    {
        $view=<<<EOF
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script type="text/javascript" src="qrcode.min.js"></script>
    <style>
        .container{
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }
        .top{
            text-align: center;
            font-size: 4.5em;
            font-weight: bold;
        }
        .qrcode{
            width: 80%;
            margin: 40px auto;
        }
        .qrcode img{
            width: 85%;
            margin: 40px auto;
        }
        .footer{
            text-align: center;
            font-size: 3em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top">{$top}</div>
        <div class="qrcode" id="qrcode"></div>
        <div class="footer">{$footer}</div>
    </div>
    <script>
        var qrcodeUrl='{$qrcodeUrl}';
        var qrcodeText='{$qrcodeText}';
        if(qrcodeUrl){
            document.getElementById('qrcode').innerHTML='<img src="'+qrcodeUrl+'" style="width: 100%;height: 100%;margin: 0;"/>';
        }
        if(qrcodeText){
            //获取屏幕的宽度
            var width=window.screen.width;
            new QRCode("qrcode", {
                text: qrcodeText,
                width: width,
                height: width,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }
    </script>
</body>
</html>
EOF;
        return $view;
    }
}