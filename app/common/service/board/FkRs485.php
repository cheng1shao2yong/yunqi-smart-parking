<?php
declare(strict_types=1);

namespace app\common\service\board;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRules;
use app\common\service\BoardService;

/**
 * 方控-RS485主板
 */
class FkRs485 extends BoardService
{
    //入场语音
    public static function entryVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType){
        $plate_number=$plate->plate_number;
        $istemp=false;
        if(str_starts_with($plate->plate_number,'临')){
            $plate_number=mb_substr($plate_number,1);
            $istemp=true;
        }
        if($istemp){
            $plate_number=[0x7f,0x19,...self::stringToGbkHexArray($plate_number)];
        }else{
            $plate_number=self::stringToGbkHexArray($plate_number);
        }
        if($rulesType==ParkingRules::RULESTYPE('月租车') || $rulesType==ParkingRules::RULESTYPE('VIP车')){
            $message=self::monthlyVoiceAndScreen($barrier,$plate,'voice',$plate_number);
        }else{
            $message=self::provisionalVoiceAndScreen($barrier,$plate_number,'voice');
        }
        $data=self::convertArrayToHex($message,0x22);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //入场显示
    public static function entryDisplay(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType){
        $l1=self::convertScreenline($barrier->screen_time,1,$plate->plate_number);
        $l2=self::convertScreenline($barrier->screen_time,2,ParkingRules::RULESTYPE[$rulesType]);
        $l3=self::provisionalVoiceAndScreen($barrier,'','screen');
        $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
        if($rulesType==ParkingRules::RULESTYPE('月租车') || $rulesType==ParkingRules::RULESTYPE('VIP车')){
            $l3=self::monthlyVoiceAndScreen($barrier,$plate,'screen',$l3);
        }else if($rulesType==ParkingRules::RULESTYPE('储值车')){
            $l3=self::convertScreenline($barrier->screen_time,1,'余额'.formatNumber($plate->cars->balance).'元');
        }
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //请缴费语音
    public static function payVoice(ParkingPlate $plate,ParkingRecordsPay $recordsPay){
        $plate_number=self::stringToGbkHexArray($plate->plate_number);
        $fee=formatNumber($recordsPay->pay_price);
        $string=self::convertNumberToChinese($fee).'元';
        $string=self::stringToGbkHexArray($string);
        $message=array_merge($plate_number,[0x0B],$string);
        $data=self::convertArrayToHex($message,0x22);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //免费离场语音
    public static function freeLeaveVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType){
        $plate_number=self::stringToGbkHexArray($plate->plate_number);
        if($rulesType==ParkingRules::RULESTYPE('月租车') || $rulesType==ParkingRules::RULESTYPE('VIP车')){
            $message=self::monthlyVoiceAndScreen($barrier,$plate,'voice',$plate_number);
        }else{
            $message=array_merge($plate_number,[0x02]);
        }
        $data=self::convertArrayToHex($message,0x22);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //请缴费显示
    public static function paidLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,ParkingRecordsPay $recordsPay,string $rulesType){
        $l1=self::convertScreenline($barrier->screen_time,1,$plate->plate_number);
        $l2=self::convertScreenline($barrier->screen_time,2,ParkingRules::RULESTYPE[$rulesType]);
        $fee=formatNumber($recordsPay->pay_price);
        $string=$fee.'元';
        $l3=self::convertScreenline($barrier->screen_time,1,$string);
        $time=self::convertTimeToString(time()-$records->entry_time);
        $l4=self::convertScreenline($barrier->screen_time,2,$time);
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //免费离场显示
    public static function freeLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,string $rulesType){
        $time=self::convertTimeToString(time()-$records->entry_time);
        $l1=self::convertScreenline($barrier->screen_time,1,$plate->plate_number);
        $l2=self::convertScreenline($barrier->screen_time,2,ParkingRules::RULESTYPE[$rulesType]);
        $l3=self::convertScreenline($barrier->screen_time,1,'免费');
        $l4=self::convertScreenline($barrier->screen_time,2,$time);
        if($rulesType==ParkingRules::RULESTYPE('月租车') || $rulesType==ParkingRules::RULESTYPE('VIP车')){
            $l3=self::monthlyVoiceAndScreen($barrier,$plate,'screen',$l3);
        }else if($rulesType==ParkingRules::RULESTYPE('储值车')){
            $l3=self::convertScreenline($barrier->screen_time,2,'本次停车免费，余额'.formatNumber($plate->cars->balance).'元');
        }
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //开闸异常显示
    public static function openGateExceptionDisplay(ParkingBarrier $barrier,string $message){
        $l1=self::convertScreenline($barrier->screen_time,1,'禁止通行');
        $l2=self::convertScreenline($barrier->screen_time,2,$message);
        $l3=self::convertScreenline($barrier->screen_time,1,'一车一杆');
        $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //余额不足语音
    public static function insufficientBalanceVoice(){
        $data=self::convertArrayToHex([0x23,0x04],0x22);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //余额不足显示
    public static function insufficientBalanceScreen(ParkingBarrier $barrier,string $plate_number)
    {
        $l1=self::convertScreenline($barrier->screen_time,1,$plate_number);
        $l2=self::convertScreenline($barrier->screen_time,2,'储值车');
        $l3=self::convertScreenline($barrier->screen_time,1,'余额不足');
        $l4=self::convertScreenline($barrier->screen_time,2,'请充值');
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //支付成功语音
    public static function paySuccessVoice(){
        $data=self::convertArrayToHex([0x7e,0x18,0x28,0x02],0x22);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //支付成功显示
    public static function paySuccessScreen(ParkingBarrier $barrier,ParkingRecords $records){
        $time=self::convertTimeToString(time()-$records->entry_time);
        $l1=self::convertScreenline($barrier->screen_time,1,$records->plate_number);
        $l2=self::convertScreenline($barrier->screen_time,2,ParkingRules::RULESTYPE[$records->rules_type]);
        $l3=self::convertScreenline($barrier->screen_time,1,'已付款');
        $l4=self::convertScreenline($barrier->screen_time,2,$time);
        if($records->rules_type==ParkingRules::RULESTYPE('储值车')){
            $fee=formatNumber($records->pay_fee);
            $balance=0;
            $cars=ParkingCars::find($records->cars_id);
            if($cars){
                $balance=$cars->balance;
            }
            $l3=self::convertScreenline($barrier->screen_time,2,'已付款'.$fee.'元，余额'.formatNumber($balance).'元');
        }
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //禁止通行语音
    public static function noEntryVoice(){
        $data=self::convertArrayToHex([0x2B],0x22);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //人工确认语音
    public static function confirmVoice(string $plate_number){
        $plate_number=self::stringToGbkHexArray($plate_number);
        $message=array_merge($plate_number,[0x1F]);
        $data=self::convertArrayToHex($message,0x22);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //人工确认显示
    public static function confirmDisplay(ParkingBarrier $barrier,string $plate_number){
        $l1=self::convertScreenline($barrier->screen_time,1,$plate_number);
        $l2=self::convertScreenline($barrier->screen_time,2,'人工确认');
        $l3=self::convertScreenline($barrier->screen_time,1,'一车一杆');
        $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //设置广告
    public static function setAdvertisement(int $line,string $text){
        $setting=[
            $line+1,
            ($line%2)===0?1:2,
            ''
        ];
        $arr=self::stringToGbkHexArray($text);
        $message=array_merge($setting,$arr);
        $data=self::convertArrayToHex($message,0x25);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //设置音量
    public static function setVolume(int $step,int $voice){
        if($step==1){
            $data=self::convertArrayToHex([$voice],0xF0);
            $dataStream = pack('C*', ...$data);
            return $dataStream;
        }
        if($step==2){
            $data=self::convertArrayToHex([0x01],0x22);
            $dataStream = pack('C*', ...$data);
            return $dataStream;
        }
    }
    //无入场记录放行显示
    public static function noEntryRecordDisplay(ParkingBarrier $barrier,string $plate_number){
        $l1=self::convertScreenline($barrier->screen_time,1,$plate_number);
        $l2=self::convertScreenline($barrier->screen_time,2,'无入场记录，直接放行');
        $l3=self::convertScreenline($barrier->screen_time,1,'一车一杆');
        $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //无入场记录放行语音
    public static function noEntryRecordVoice(){
        $str=self::stringToGbkHexArray('无');
        $data=self::convertArrayToHex([...$str,0x5C,0x16,0x68],0x22);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }

    //内场放行显示
    public static function insidePassDisplay(ParkingBarrier $barrier,string $plate_number)
    {
        $l1=self::convertScreenline($barrier->screen_time,1,$plate_number);
        $l2=self::convertScreenline($barrier->screen_time,2,'内场通道，直接放行');
        $l3=self::convertScreenline($barrier->screen_time,1,'一车一杆');
        $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }

    //显示出场付款码
    public static function showPayQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('不支持');
    }
    //显示无牌车入场二维码
    public static function showEntryQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('不支持');
    }
    //显示无牌车出场二维码
    public static function showExitQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('不支持');
    }
    //无牌车语音
    public static function noPlateVoice(ParkingBarrier $barrier)
    {
        $str=self::stringToGbkHexArray('请');
        $data=self::convertArrayToHex([0x7F,0x19,...$str,0x7F,0x1B],0x22);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }
    //无牌车显示
    public static function noPlateDisplay(ParkingBarrier $barrier)
    {
        if($barrier->barrier_type=='entry'){
            $text='入场';
        }
        if($barrier->barrier_type=='exit'){
            $text='出场';
        }
        $l1=self::convertScreenline($barrier->screen_time,1,'无牌车');
        $l2=self::convertScreenline($barrier->screen_time,2,'请扫码'.$text);
        $l3=self::convertScreenline($barrier->screen_time,1,'一车一杆');
        $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
        $message=array_merge($l1,$l2,$l3,$l4);
        $data=self::convertArrayToHex($message,0x29);
        $dataStream = pack('C*', ...$data);
        return $dataStream;
    }

    private static function convertTimeToString(int $time)
    {
        $day=intval($time/3600/24);
        $str='';
        if($day>0){
            $str.=$day.'天';
        }
        $hour=intval(($time%(3600*24))/3600);
        if($hour>0){
            $str.=$hour.'小时';
        }
        $minute=intval(($time%3600)/60);
        if($minute>0){
            $str.=$minute.'分钟';
        }
        return $str;
    }

    private static function convertScreenline(int $time,int $color,string $string)
    {
        $arr=self::stringToGbkHexArray($string);
        $length=count($arr);
        return array_merge([$time,$color,$length],$arr);
    }

    private static function provisionalVoiceAndScreen(ParkingBarrier $barrier,mixed $plate_number,string $type)
    {
        $message_entry=self::MESSAGE_ENTRY;
        $message_exit=self::MESSAGE_EXIT;
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $setting=$parking->setting;
        if($type=='screen'){
            $blessing_screen=[
                'entry'=>self::convertScreenline($barrier->screen_time,1,$message_entry[$setting->provisional_entry_tips]),
                'exit'=>self::convertScreenline($barrier->screen_time,1,$message_exit[$setting->provisional_exit_tips])
            ];
            return $blessing_screen[$barrier->barrier_type];
        }
        if($type=='voice'){
            $blessing_voice=[
                'entry'=>array_merge($plate_number,[$setting->provisional_entry_tips]),
                'exit'=>array_merge($plate_number,[$setting->provisional_exit_tips])
            ];
            return $blessing_voice[$barrier->barrier_type];
        }
    }

    private static function monthlyVoiceAndScreen(ParkingBarrier $barrier,ParkingPlate $plate,string $type,mixed $default)
    {
        $message_entry=self::MESSAGE_ENTRY;
        $message_exit=self::MESSAGE_EXIT;
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $setting=$parking->setting;
        $blessing_screen=[
            'entry'=>self::convertScreenline($barrier->screen_time,1,$message_entry[$setting->monthly_entry_tips]),
            'exit'=>self::convertScreenline($barrier->screen_time,1,$message_exit[$setting->monthly_exit_tips])
        ];
        $blessing_voice=[
            'entry'=>array_merge($default,[$setting->monthly_entry_tips]),
            'exit'=>array_merge($default,[$setting->monthly_exit_tips])
        ];
        $overdate_screen=self::convertScreenline($barrier->screen_time,1,'已过期');
        $overdate_voice=array_merge($default,[0x0F]);
        //显示祝福语
        if($type=='screen' && $barrier->monthly_screen=='blessing'){
            $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
            $setting=$parking->setting;
            if($setting->monthly_voice){
                return $blessing_screen[$barrier->barrier_type];
            }
        }
        //显示剩余天数
        if($type=='screen' && $barrier->monthly_screen=='day'){
            $time=$plate->cars->endtime-time();
            if($time<=0){
                return $overdate_screen;
            }else{
                $day=self::convertTimeToString($time);
                return self::convertScreenline($barrier->screen_time,1,'有效期'.$day);
            }
        }
        //显示祝福语，剩余N天时显示剩余天数
        if($type=='screen' && $barrier->monthly_screen=='last'){
            $time=$plate->cars->endtime-time();
            $lday=ceil($time/86400);
            if($lday<=$barrier->monthly_screen_day){
                if($time<=0){
                    return $overdate_screen;
                }else{
                    $day=self::convertTimeToString($time);
                    return self::convertScreenline($barrier->screen_time,1,'有效期'.$day);
                }
            }else{
                return $blessing_screen[$barrier->barrier_type];
            }
        }
        //播放祝福语
        if($type=='voice' && $barrier->monthly_voice=='blessing'){
            $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
            $setting=$parking->setting;
            if($setting->monthly_voice){
                return $blessing_voice[$barrier->barrier_type];
            }
        }
        //播放剩余天数
        if($type=='voice' && $barrier->monthly_voice=='day'){
            $time=$plate->cars->endtime-time();
            if($time<=0){
                return $overdate_voice;
            }else{
                $day=intval($time/24/3600);
                $day=self::convertNumberToChinese($day);
                $string=self::stringToGbkHexArray($day.'天');
                return array_merge($default,[0x12],$string);
            }
        }
        //显示祝福语，剩余N天时显示剩余天数
        if($type=='voice' && $barrier->monthly_voice=='last'){
            $time=$plate->cars->endtime-time();
            $lday=ceil($time/86400);
            if($lday<=$barrier->monthly_voice_day){
                if($time<=0){
                    return $overdate_voice;
                }else{
                    $day=intval($time/24/3600);
                    $day=self::convertNumberToChinese($day);
                    $string=self::stringToGbkHexArray($day.'天');
                    return array_merge($default,[0x12],$string);
                }
            }else{
                return $blessing_voice[$barrier->barrier_type];
            }
        }
        return $default;
    }

    public static function convertNumberToChinese(mixed $num)
    {
        if($num<=0){
            return '0';
        }
        if(is_int($num)){
            $units = ['', '十', '百', '千'];
            $yi=intval($num/100000000);
            if($yi>=1){
                $last=$num%100000000;
                $ling='';
                if($last<10000000){
                    $ling='0';
                }
                $result=self::convertNumberToChinese($yi).'亿'.$ling.self::convertNumberToChinese($last);
                $result = preg_replace('/0+/', '0', $result);
                $result = preg_replace('/0+$/', '', $result);
                return $result;
            }
            $wan=intval($num/10000);
            if($wan>=1){
                $last=$num%10000;
                $ling='';
                if($last<1000){
                    $ling='0';
                }
                $result=self::convertNumberToChinese($wan).'万'.$ling.self::convertNumberToChinese($last);
                $result = preg_replace('/0+/', '0', $result);
                $result = preg_replace('/0+$/', '', $result);
                return $result;
            }
            $arr=[];
            $numstr=(string)$num;
            for ($i=strlen($numstr)-1;$i>=0;$i--) {
                $arr[] = $numstr[strlen($numstr)-$i-1].$units[$i];
            }
            $result='';
            foreach ($arr as $value){
                if(str_starts_with($value,'0')){
                    $result.='0';
                }else{
                    $result.=$value;
                }
            }
            $result = preg_replace('/0+/', '0', $result);
            $result = preg_replace('/0+$/', '', $result);
            return $result;
        }
        if(is_float($num)){
            $start=intval($num);
            $end=intval(round($num-$start,2)*100);
            if($end<10){
                $end='0'.$end;
            }
            return self::convertNumberToChinese($start).'点'.$end;
        }
    }

    private static function stringToGbkHexArray(string $str)
    {
        $gbkStr = mb_convert_encoding($str, 'GBK', 'UTF-8');
        $length = strlen($gbkStr);
        $hexArray = [];
        // 遍历GBK编码字符串的每个字节，并将其转换为16进制
        for ($i = 0; $i < $length; $i++) {
            $hexArray[] = unpack('C', $gbkStr[$i])[1];
        }
        return $hexArray;
    }

    private static function convertArrayToHex(array $message,int $action)
    {
        $length=count($message);
        $start=[0xAA,0x55];
        $rand=rand(1,100);
        $data=[$rand,0x64,0x00,$action,0x00,$length,...$message,0x00,0x00];
        $data = array_map(function($hex) {
            // 去除0x前缀并转换为整数
            $int = intval($hex, 16);
            // 将整数转换为十六进制字符串
            $hexString = dechex($int);
            while (strlen($hexString) < 2) {
                $hexString = '0' . $hexString;
            }
            // 将十六进制字符串转换为大写
            return strtoupper($hexString);
        }, $data);
        $hexString = implode("",$data);
        $s = pack('H*',$hexString);
        $t = self::crc166($s);
        $t = unpack("H*", $t);
        $jx1 = intval('0x'.substr($t[1], 0, 2),16);
        $jx2 = intval('0x'.substr($t[1], 2, 2),16);
        return [...$start,$rand,0x64,0x00,$action,0x00,$length,...$message,$jx1,$jx2,0xAF];
    }

    private static function crc166($string, $length = 0)
    {
        $auchCRCHi = array(0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
            0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0,
            0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01,
            0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81,
            0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0,
            0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01,
            0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
            0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0,
            0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01,
            0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
            0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0,
            0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01,
            0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
            0x40);
        $auchCRCLo = array(0x00, 0xC0, 0xC1, 0x01, 0xC3, 0x03, 0x02, 0xC2, 0xC6, 0x06, 0x07, 0xC7, 0x05, 0xC5, 0xC4,
            0x04, 0xCC, 0x0C, 0x0D, 0xCD, 0x0F, 0xCF, 0xCE, 0x0E, 0x0A, 0xCA, 0xCB, 0x0B, 0xC9, 0x09,
            0x08, 0xC8, 0xD8, 0x18, 0x19, 0xD9, 0x1B, 0xDB, 0xDA, 0x1A, 0x1E, 0xDE, 0xDF, 0x1F, 0xDD,
            0x1D, 0x1C, 0xDC, 0x14, 0xD4, 0xD5, 0x15, 0xD7, 0x17, 0x16, 0xD6, 0xD2, 0x12, 0x13, 0xD3,
            0x11, 0xD1, 0xD0, 0x10, 0xF0, 0x30, 0x31, 0xF1, 0x33, 0xF3, 0xF2, 0x32, 0x36, 0xF6, 0xF7,
            0x37, 0xF5, 0x35, 0x34, 0xF4, 0x3C, 0xFC, 0xFD, 0x3D, 0xFF, 0x3F, 0x3E, 0xFE, 0xFA, 0x3A,
            0x3B, 0xFB, 0x39, 0xF9, 0xF8, 0x38, 0x28, 0xE8, 0xE9, 0x29, 0xEB, 0x2B, 0x2A, 0xEA, 0xEE,
            0x2E, 0x2F, 0xEF, 0x2D, 0xED, 0xEC, 0x2C, 0xE4, 0x24, 0x25, 0xE5, 0x27, 0xE7, 0xE6, 0x26,
            0x22, 0xE2, 0xE3, 0x23, 0xE1, 0x21, 0x20, 0xE0, 0xA0, 0x60, 0x61, 0xA1, 0x63, 0xA3, 0xA2,
            0x62, 0x66, 0xA6, 0xA7, 0x67, 0xA5, 0x65, 0x64, 0xA4, 0x6C, 0xAC, 0xAD, 0x6D, 0xAF, 0x6F,
            0x6E, 0xAE, 0xAA, 0x6A, 0x6B, 0xAB, 0x69, 0xA9, 0xA8, 0x68, 0x78, 0xB8, 0xB9, 0x79, 0xBB,
            0x7B, 0x7A, 0xBA, 0xBE, 0x7E, 0x7F, 0xBF, 0x7D, 0xBD, 0xBC, 0x7C, 0xB4, 0x74, 0x75, 0xB5,
            0x77, 0xB7, 0xB6, 0x76, 0x72, 0xB2, 0xB3, 0x73, 0xB1, 0x71, 0x70, 0xB0, 0x50, 0x90, 0x91,
            0x51, 0x93, 0x53, 0x52, 0x92, 0x96, 0x56, 0x57, 0x97, 0x55, 0x95, 0x94, 0x54, 0x9C, 0x5C,
            0x5D, 0x9D, 0x5F, 0x9F, 0x9E, 0x5E, 0x5A, 0x9A, 0x9B, 0x5B, 0x99, 0x59, 0x58, 0x98, 0x88,
            0x48, 0x49, 0x89, 0x4B, 0x8B, 0x8A, 0x4A, 0x4E, 0x8E, 0x8F, 0x4F, 0x8D, 0x4D, 0x4C, 0x8C,
            0x44, 0x84, 0x85, 0x45, 0x87, 0x47, 0x46, 0x86, 0x82, 0x42, 0x43, 0x83, 0x41, 0x81, 0x80,
            0x40);
        $length = ($length <= 0 ? strlen($string) : $length);
        $uchCRCHi = 0xFF;
        $uchCRCLo = 0xFF;
        for ($i = 0; $i < $length; $i++) {
            $uIndex = $uchCRCLo ^ ord(substr($string, $i, 1));
            $uchCRCLo = $uchCRCHi ^ $auchCRCHi[$uIndex];
            $uchCRCHi = $auchCRCLo[$uIndex];
        }
        return chr($uchCRCHi) . chr($uchCRCLo);
    }
}