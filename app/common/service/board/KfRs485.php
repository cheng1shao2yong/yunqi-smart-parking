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
use app\common\service\barrier\Zhenshi;
use app\common\service\BoardService;

/**
 * 科发-RS485主板实现（基于 显示屏通信协议 V2.4）
 *
 * 说明：
 * - 语音 CMD 0x30 使用 buildPacket() 返回二进制（与之前一致）
 * - 文本 CMD 0x62 按 C# LED_DisText 实现，生成完整帧并返回二进制（每行一帧）
 * - 所有文本编码为 GBK
 * - 显示屏地址 DA 使用 0x00（如需改为 0x01 请修改常量）
 */
class KfRs485 extends BoardService
{
    private const DA = 0x00;   // 显示屏地址
    private const VR = 0x64;   // 版本
    private const PNH = 0xFF;
    private const PNL = 0xFF;

    //入场语音
    public static function entryVoice(ParkingBarrier $barrier, ParkingPlate $plate, string $rulesType)
    {
        $plate_number = $plate->plate_number;
        if ($rulesType == ParkingRules::RULESTYPE('月租车') || $rulesType == ParkingRules::RULESTYPE('VIP车')) {
            $text =self::monthlyVoice($barrier,$plate,'voice',$plate_number);
        } else {
            $text =$plate_number.'，'.self::provisionalVoice($barrier);
        }
        return self::buildPlayVoicePacket(0x01, $text);
    }

    /**
     * 入场显示
     */
    public static function entryDisplay(ParkingBarrier $barrier, ParkingPlate $plate, string $rulesType)
    {
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, $plate->plate_number, $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, self::provisionalScreen($barrier), $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(2, 0x15, ParkingRules::RULESTYPE[$rulesType], $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(3, 0x15, '减速慢行', $barrier->screen_time);
        if ($rulesType == ParkingRules::RULESTYPE('月租车') || $rulesType == ParkingRules::RULESTYPE('VIP车')) {
            $message=self::monthlyScreen($barrier,$plate,'screen');
            $frames[1]=self::makeDisplayPacket(1, 0x15, $message, $barrier->screen_time);
        } else if ($rulesType == ParkingRules::RULESTYPE('储值车')) {
            $frames[1]=self::makeDisplayPacket(1, 0x15, '余额'.formatNumber($plate->cars->balance).'元', $barrier->screen_time);
        }
        return implode('', $frames);
    }

    //请缴费语音
    public static function payVoice(ParkingPlate $plate, ParkingRecordsPay $recordsPay)
    {
        $fee=formatNumber($recordsPay->pay_price);
        $feeChinese=self::convertNumberToChinese($fee).'元';
        $text = $plate->plate_number . '，请缴费，' . $feeChinese;
        return self::buildPlayVoicePacket(0x01, $text);
    }

    //免费离场语音
    public static function freeLeaveVoice(ParkingBarrier $barrier, ParkingPlate $plate, string $rulesType)
    {
        if($rulesType==ParkingRules::RULESTYPE('月租车') || $rulesType==ParkingRules::RULESTYPE('VIP车')){
            $message=self::monthlyVoice($barrier,$plate,'voice',$plate->plate_number);
        }else{
            $message=$plate->plate_number.'，一路平安';
        }
        return self::buildPlayVoicePacket(0x01, $message);
    }

    //请缴费显示
    public static function paidLeaveDisplay(ParkingBarrier $barrier, ParkingPlate $plate, ParkingRecords $records, ParkingRecordsPay $recordsPay, string $rulesType)
    {
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, $plate->plate_number, $barrier->screen_time);
        $fee=formatNumber($recordsPay->pay_price).'元';
        $time=self::convertTimeToString(time()-$records->entry_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, $fee, $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(2, 0x15, ParkingRules::RULESTYPE[$rulesType], $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(3, 0x15, '停车'.$time, $barrier->screen_time);
        return implode('', $frames);
    }

    //免费离场显示
    public static function freeLeaveDisplay(ParkingBarrier $barrier, ParkingPlate $plate, ParkingRecords $records, string $rulesType)
    {
        $time=self::convertTimeToString(time()-$records->entry_time);
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, $plate->plate_number, $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, '免费离场', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(2, 0x15, ParkingRules::RULESTYPE[$rulesType], $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(3, 0x15, '停车'.$time, $barrier->screen_time);
        if($rulesType==ParkingRules::RULESTYPE('月租车') || $rulesType==ParkingRules::RULESTYPE('VIP车')){
            $message=self::monthlyScreen($barrier,$plate,'screen');
            $frames[1]=self::makeDisplayPacket(1, 0x15, $message, $barrier->screen_time);
        }else if($rulesType==ParkingRules::RULESTYPE('储值车')){
            $message='本次停车免费，余额'.formatNumber($plate->cars->balance).'元';
            $frames[1]=self::makeDisplayPacket(1, 0x15, $message, $barrier->screen_time);
        }
        return implode('', $frames);
    }

    //开闸异常显示
    public static function openGateExceptionDisplay(ParkingBarrier $barrier, string $message)
    {
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, '开闸异常', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, $message, $barrier->screen_time);
        return implode('', $frames);
    }

    //余额不足语音
    public static function insufficientBalanceVoice()
    {
        return self::buildPlayVoicePacket(0x01, '余额不足，请充值');
    }

    //余额不足显示
    public static function insufficientBalanceScreen(ParkingBarrier $barrier,string $plate_number)
    {
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, $plate_number, $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, '储值车', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(2, 0x15, '余额不足', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(3, 0x15, '请充值', $barrier->screen_time);
        return implode('', $frames);
    }

    //支付成功语音
    public static function paySuccessVoice()
    {
        return self::buildPlayVoicePacket(0x01, '支付，成功，请通行');
    }

    //支付成功显示
    public static function paySuccessScreen(ParkingBarrier $barrier, ParkingRecords $records)
    {
        $time=self::convertTimeToString(time()-$records->entry_time);
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, $records->plate_number, $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, '已付款', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(2, 0x15, ParkingRules::RULESTYPE[$records->rules_type], $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(3, 0x15, '停车'.$time, $barrier->screen_time);
        if($records->rules_type==ParkingRules::RULESTYPE('储值车')){
            $fee=formatNumber($records->pay_fee);
            $balance=0;
            $cars=ParkingCars::find($records->cars_id);
            if($cars){
                $balance=$cars->balance;
            }
            $frames[1] = self::makeDisplayPacket(1, 0x15, '已付款'.$fee.'元，余额'.formatNumber($balance).'元', $barrier->screen_time);
        }
        return implode('', $frames);
    }

    //禁止通行语音
    public static function noEntryVoice()
    {
        return self::buildPlayVoicePacket(0x01, '禁止通行');
    }

    //人工确认语音
    public static function confirmVoice(string $plate_number)
    {
        return self::buildPlayVoicePacket(0x01, $plate_number . '，请，人工确认');
    }

    //人工确认显示
    public static function confirmDisplay(ParkingBarrier $barrier, string $plate_number)
    {
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, $plate_number, $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, '请人工确认', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(2, 0x15, '一车一杆', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(3, 0x15, '减速慢行', $barrier->screen_time);
        return implode('', $frames);
    }

    //设置广告
    public static function setAdvertisement(int $line, string $text)
    {
        $frames = [];
        $frames[] = self::makeDisplayPacket($line, 0x15, $text, 0);
        return implode('', $frames);
    }

    //设置音量
    public static function setVolume(int $step, int $voice)
    {
        throw new \Exception('暂不支持');
    }

    //无入场记录放行显示
    public static function noEntryRecordDisplay(ParkingBarrier $barrier,string $plate_number)
    {
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, $plate_number, $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, '无入场记录，直接放行', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(2, 0x15, '一车一杆', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(3, 0x15, '减速慢行', $barrier->screen_time);
        return implode('', $frames);
    }

    //无入场记录放行语音
    public static function noEntryRecordVoice()
    {
        return self::buildPlayVoicePacket(0x01,'请通行');
    }

    //内场放行显示
    public static function insidePassDisplay(ParkingBarrier $barrier,string $plate_number)
    {
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, $plate_number, $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, '内场通道，直接放行', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, '一车一杆', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, '减速慢行', $barrier->screen_time);
        return implode('', $frames);
    }

    public static function showPayQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持');
    }

    public static function showEntryQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持');
    }

    public static function showExitQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持');
    }

    //无牌车语音
    public static function noPlateVoice(ParkingBarrier $barrier)
    {
        $text='';
        if($barrier->barrier_type=='entry'){
            $text='入场';
        }
        if($barrier->barrier_type=='exit'){
            $text='出场';
        }
        return self::buildPlayVoicePacket(0x01,'无牌车，请扫码，'.$text);
    }

    //无牌车显示
    public static function noPlateDisplay(ParkingBarrier $barrier)
    {
        $text='';
        if($barrier->barrier_type=='entry'){
            $text='入场';
        }
        if($barrier->barrier_type=='exit'){
            $text='出场';
        }
        $frames = [];
        $frames[] = self::makeDisplayPacket(0, 0x15, '无牌车', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(1, 0x15, '请扫码'.$text, $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(2, 0x15, '一车一杆', $barrier->screen_time);
        $frames[] = self::makeDisplayPacket(3, 0x15, '减速慢行', $barrier->screen_time);
        return implode('', $frames);
    }

    private static function provisionalScreen(ParkingBarrier $barrier)
    {
        $message_entry=self::MESSAGE_ENTRY;
        $message_exit=self::MESSAGE_EXIT;
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $setting=$parking->setting;
        $blessing_screen=[
            'entry'=>$message_entry[$setting->provisional_entry_tips],
            'exit'=>$message_exit[$setting->provisional_exit_tips]
        ];
        return $blessing_screen[$barrier->barrier_type];
    }

    private static function provisionalVoice(ParkingBarrier $barrier)
    {
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $setting=$parking->setting;
        $blessing_voice=[
            'entry'=>Zhenshi::MESSAGE_ENTRY[$setting->provisional_entry_tips],
            'exit'=>Zhenshi::MESSAGE_EXIT[$setting->provisional_exit_tips]
        ];
        return $blessing_voice[$barrier->barrier_type];
    }

    private static function monthlyVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $type,string $plate_number)
    {
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $setting=$parking->setting;
        $blessing_voice=[
            'entry'=>Zhenshi::MESSAGE_ENTRY[$setting->monthly_entry_tips],
            'exit'=>Zhenshi::MESSAGE_EXIT[$setting->monthly_exit_tips]
        ];
        $overdate_voice=$plate_number.'，已过期';
        //播放祝福语
        if($type=='voice' && $barrier->monthly_voice=='blessing'){
            return $plate_number.'，'.$blessing_voice[$barrier->barrier_type];
        }
        //播放剩余天数
        if($type=='voice' && $barrier->monthly_voice=='day'){
            $time=$plate->cars->endtime-time();
            if($time<=0){
                return $overdate_voice;
            }else{
                $day=intval($time/24/3600);
                $day=self::convertNumberToChinese($day);
                return $plate_number.'，有效期，'.$day.'天';
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
                    return $plate_number.'，有效期，'.$day.'天';
                }
            }else{
                return $plate_number.'，'.$blessing_voice[$barrier->barrier_type];
            }
        }
        return $plate_number;
    }

    private static function monthlyScreen(ParkingBarrier $barrier,ParkingPlate $plate,string $type)
    {
        $message_entry=self::MESSAGE_ENTRY;
        $message_exit=self::MESSAGE_EXIT;
        $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
        $setting=$parking->setting;
        $blessing_screen=[
            'entry'=>$message_entry[$setting->monthly_entry_tips],
            'exit'=>$message_exit[$setting->monthly_exit_tips]
        ];
        $overdate_screen='已过期';
        //显示祝福语
        if($type=='screen' && $barrier->monthly_screen=='blessing'){
            return $blessing_screen[$barrier->barrier_type];
        }
        //显示剩余天数
        if($type=='screen' && $barrier->monthly_screen=='day'){
            $time=$plate->cars->endtime-time();
            if($time<=0){
                return $overdate_screen;
            }else{
                $day=self::convertTimeToString($time);
                return '有效期'.$day;
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
                    return '有效期'.$day;
                }
            }else{
                return $blessing_screen[$barrier->barrier_type];
            }
        }
    }


    /**
     * 构建播放语音（CMD = 0x30）包，返回二进制字符串（pack）
     * @param int $opt
     * @param string $text UTF-8
     * @return string
     */
    private static function buildPlayVoicePacket(int $opt, string $text): string
    {
        $bytes = [];
        $bytes[] = $opt;
        $textBytes = self::stringToGbkBytes($text);
        foreach ($textBytes as $b) $bytes[] = $b;
        return self::buildPacket(0x30, $bytes);
    }

    /**
     * 通用 buildPacket（用于语音等以主机端组帧的命令）
     * 使用单字节 DL（和 C# 实现一致）
     * 返回二进制字符串（pack）
     */
    private static function buildPacket(int $cmd, array $dataBytes): string
    {
        $da = 0x00;
        $vr = 0x64; // 协议版本 100 (0x64)
        $pnH = 0xFF;
        $pnL = 0xFF;

        $dl = count($dataBytes);
        // 构建控制域 + 数据域（用于 CRC 计算，从 DA 到 DATA 最后一个字节）
        $frame = [];
        $frame[] = $da;
        $frame[] = $vr;
        $frame[] = $pnH;
        $frame[] = $pnL;
        $frame[] = $cmd & 0xFF;
        // 当 VR = 100，DL 为 1 字节
        $frame[] = $dl & 0xFF;
        foreach ($dataBytes as $b) $frame[] = $b & 0xFF;

        // 计算 CRC16（小端：低字节在前）
        $crc = self::mbCrc16($frame);
        $crcLo = $crc & 0xFF;
        $crcHi = ($crc >> 8) & 0xFF;

        // 最终包：frame + crcLo + crcHi
        $packet = array_merge($frame, [$crcLo, $crcHi]);
        // pack 成二进制字符串
        return pack('C*', ...$packet);
    }

    /**
     * 按 C# LED_DisText 构造 0x62 完整帧（返回二进制字符串）
     *
     * Data length = 19 + textLen （与 C# 保持一致）
     *
     * 参数：
     *  - $line: 行号 0..3
     *  - $disMode: 显示模式（0 立即等）
     *  - $text: 文本 (UTF-8)
     *  - $delay: 停留时间(秒)
     */
    private static function makeDisplayPacket(int $line, int $disMode, string $text, int $delay): string
    {
        $EnterSpeed = 1;    // 显示速度（C# 示例使用）
        $ExitSpeed  = 1;
        $FontIndex  = 0x03;
        $DisTimes   = 0;    // 0 = 循环
        $TextColor  = 0xFF000000; // 红色，小端存储
        $bgR = $bgG = $bgB = $bgRes = 0x00;

        $textBytes = self::stringToGbkBytes($text);
        $textLen = count($textBytes);

        // 构造头 + 数据（不含 CRC）
        $buf = [];
        $buf[] = self::DA;
        $buf[] = self::VR;
        $buf[] = self::PNH;
        $buf[] = self::PNL;
        $buf[] = 0x62;                 // CMD
        $buf[] = (19 + $textLen) & 0xFF; // DL (单字节，与 C# 一致)

        // data 区（与 C# LED_DisText 顺序一致）
        $buf[] = $line & 0xFF;    // Line
        $buf[] = $disMode & 0xFF; // DisMode
        $buf[] = $EnterSpeed & 0xFF; // EnterSpeed
        $buf[] = $delay;            // 停留模式（C# 填 0）
        $buf[] = 2 & 0xFF;   // DelayTime (秒)
        $buf[] = $disMode & 0xFF; // 退出模式
        $buf[] = $ExitSpeed & 0xFF; // 退出速度
        $buf[] = $FontIndex & 0xFF; // 字体类型
        $buf[] = $DisTimes & 0xFF;  // 显示次数

        // 前景色 (32-bit little endian: R G B RES)
        $buf[] = $TextColor & 0xFF;
        $buf[] = ($TextColor >> 8) & 0xFF;
        $buf[] = ($TextColor >> 16) & 0xFF;
        $buf[] = ($TextColor >> 24) & 0xFF;

        // 背景色 4 字节
        $buf[] = $bgR; $buf[] = $bgG; $buf[] = $bgB; $buf[] = $bgRes;

        // 文本长度 16-bit (低字节先)
        $buf[] = $textLen & 0xFF;
        $buf[] = 0x00;

        // 文本内容（GBK字节）
        foreach ($textBytes as $b) $buf[] = $b & 0xFF;

        // CRC (计算从 DA 到 DATA 最后一字节)
        $crc = self::mbCrc16($buf);
        $buf[] = $crc & 0xFF;         // CRC low
        $buf[] = ($crc >> 8) & 0xFF;  // CRC high

        return pack('C*', ...$buf);
    }

    /**
     * 将 UTF-8 字符串转换为 GBK 字节数组（每个元素 0..255）
     */
    private static function stringToGbkBytes(string $str): array
    {
        // 转为 GBK（如果包含中文）
        $gbk = @mb_convert_encoding($str, 'GBK', 'UTF-8');
        if ($gbk === false) $gbk = $str;
        $bytes = [];
        $len = strlen($gbk);
        for ($i = 0; $i < $len; $i++) {
            $bytes[] = ord($gbk[$i]);
        }
        return $bytes;
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

    /**
     * MB CRC16（与 C# / PDF 中的一致）
     * 输入：bytes array（从 DA 开始到 DATA 最后一个字节）
     * 返回：16-bit 整数（高字节在高位）
     */
    private static function mbCrc16(array $data): int
    {
        $auchCRCHi = [
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40,
            0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
            0x00, 0xC1, 0x81, 0x40
        ];
        $auchCRCLo = [
            0x00, 0xC0, 0xC1, 0x01, 0xC3, 0x03, 0x02, 0xC2, 0xC6, 0x06, 0x07, 0xC7,
            0x05, 0xC5, 0xC4, 0x04, 0xCC, 0x0C, 0x0D, 0xCD, 0x0F, 0xCF, 0xCE, 0x0E,
            0x0A, 0xCA, 0xCB, 0x0B, 0xC9, 0x09, 0x08, 0xC8, 0xD8, 0x18, 0x19, 0xD9,
            0x1B, 0xDB, 0xDA, 0x1A, 0x1E, 0xDE, 0xDF, 0x1F, 0xDD, 0x1D, 0x1C, 0xDC,
            0x14, 0xD4, 0xD5, 0x15, 0xD7, 0x17, 0x16, 0xD6, 0xD2, 0x12, 0x13, 0xD3,
            0x11, 0xD1, 0xD0, 0x10, 0xF0, 0x30, 0x31, 0xF1, 0x33, 0xF3, 0xF2, 0x32,
            0x36, 0xF6, 0xF7, 0x37, 0xF5, 0x35, 0x34, 0xF4, 0x3C, 0xFC, 0xFD, 0x3D,
            0xFF, 0x3F, 0x3E, 0xFE, 0xFA, 0x3A, 0x3B, 0xFB, 0x39, 0xF9, 0xF8, 0x38,
            0x28, 0xE8, 0xE9, 0x29, 0xEB, 0x2B, 0x2A, 0xEA, 0xEE, 0x2E, 0x2F, 0xEF,
            0x2D, 0xED, 0xEC, 0x2C, 0xE4, 0x24, 0x25, 0xE5, 0x27, 0xE7, 0xE6, 0x26,
            0x22, 0xE2, 0xE3, 0x23, 0xE1, 0x21, 0x20, 0xE0, 0xA0, 0x60, 0x61, 0xA1,
            0x63, 0xA3, 0xA2, 0x62, 0x66, 0xA6, 0xA7, 0x67, 0xA5, 0x65, 0x64, 0xA4,
            0x6C, 0xAC, 0xAD, 0x6D, 0xAF, 0x6F, 0x6E, 0xAE, 0xAA, 0x6A, 0x6B, 0xAB,
            0x69, 0xA9, 0xA8, 0x68, 0x78, 0xB8, 0xB9, 0x79, 0xBB, 0x7B, 0x7A, 0xBA,
            0xBE, 0x7E, 0x7F, 0xBF, 0x7D, 0xBD, 0xBC, 0x7C, 0xB4, 0x74, 0x75, 0xB5,
            0x77, 0xB7, 0xB6, 0x76, 0x72, 0xB2, 0xB3, 0x73, 0xB1, 0x71, 0x70, 0xB0,
            0x50, 0x90, 0x91, 0x51, 0x93, 0x53, 0x52, 0x92, 0x96, 0x56, 0x57, 0x97,
            0x55, 0x95, 0x94, 0x54, 0x9C, 0x5C, 0x5D, 0x9D, 0x5F, 0x9F, 0x9E, 0x5E,
            0x5A, 0x9A, 0x9B, 0x5B, 0x99, 0x59, 0x58, 0x98, 0x88, 0x48, 0x49, 0x89,
            0x4B, 0x8B, 0x8A, 0x4A, 0x4E, 0x8E, 0x8F, 0x4F, 0x8D, 0x4D, 0x4C, 0x8C,
            0x44, 0x84, 0x85, 0x45, 0x87, 0x47, 0x46, 0x86, 0x82, 0x42, 0x43, 0x83,
            0x41, 0x81, 0x80, 0x40
        ];

        $uchCRCHi = 0xFF;
        $uchCRCLo = 0xFF;
        foreach ($data as $b) {
            $uIndex = $uchCRCLo ^ ($b & 0xFF);
            $uchCRCLo = $uchCRCHi ^ $auchCRCHi[$uIndex];
            $uchCRCHi = $auchCRCLo[$uIndex];
        }
        return ($uchCRCHi << 8) | $uchCRCLo;
    }

    /**
     * 数字转换成大写
     */
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
}