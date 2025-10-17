<?php
declare(strict_types=1);

namespace app\common\service\board;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRules;
use app\common\service\BoardService;

/**
 * 赛菲姆-RS485主板
 */
class SfmRs485 extends BoardService
{
    /**
     * 协议固定头部（十六进制）
     */
    private const FIXED_HEADER = 'A661';
    /**
     * 单包最大数据长度（字节）- 文档规定超过200字节分包
     */
    private const MAX_SINGLE_PACK_DATA_LEN = 200;

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
        $data=[
            'text'=>$plate->plate_number.'\n'.ParkingRules::RULESTYPE[$rulesType].'\n免费离场',
            'cmd'=>'customQRCode',
            'voice'=>'请通行',
            'same'=>'0',
            'time'=>'50'
        ];
        $parkId=rand(0,255);
        $dataStream = self::pack485Data($data, $parkId);
        return $dataStream;
    }

    //请缴费显示
    public static function paidLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,ParkingRecordsPay $recordsPay,string $rulesType){
        $url=get_domain('api');
        $qrcode=$url.'/qrcode/exit?serialno='.$barrier->serialno;
        $fee=formatNumber($recordsPay->pay_price);
        $string=$fee.'元';
        $data=[
            'text'=>$plate->plate_number.'，请支付：'.$string,
            'cmd'=>'customQRCode',
            'voice'=>'请扫码付款',
            'same'=>'0',
            'time'=>'50',
            'qrcode'=>$qrcode
        ];
        $parkId=rand(0,255);
        $dataStream = self::pack485Data($data, $parkId);
        return $dataStream;
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
        $str="CAAC8B000000027B22616374696F6E4E616D65223A226469726563745465787473446973706C6179222C22637068223A22E7B2A442413132333435222C22707572655465787473446973706C6179223A22E6ACA2E8BF8EE58589E4B8B4222C22766F69636554657874223A22E6ACA2E8BF8EE58589E4B8B4222C22646973706C61795061676554696D656F7574223A33307DF8";
        $data=self::hexstr2hex($str);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
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
    public static function noEntryRecordDisplay(ParkingBarrier $barrier,string $plate_number){
        throw new \Exception('暂不支持此功能');
    }

    //无入场记录放行语音
    public static function noEntryRecordVoice(){
        throw new \Exception('暂不支持此功能');
    }

    //内场放行显示
    public static function insidePassDisplay(ParkingBarrier $barrier,string $plate_number)
    {
        throw new \Exception('暂不支持此功能');
    }

    //显示出场付款码
    public static function showPayQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    //显示无牌车入场二维码
    public static function showEntryQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    //显示无牌车出场二维码
    public static function showExitQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    //无牌车语音
    public static function noPlateVoice(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    //无牌车显示
    public static function noPlateDisplay(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    private static function hexstr2hex($hexString)
    {
        $decimalArray = [];
        for ($i = 0; $i < strlen($hexString); $i += 2) {
            $hexPair = substr($hexString, $i, 2);
            $decimalArray[] = hexdec($hexPair);
        }
        return $decimalArray;
    }
}