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
use app\common\model\Qrcode;
use app\common\service\barrier\Zhenshi;
use app\common\service\BoardService;
use think\facade\Cache;

/**
 * 赛菲姆-RS485主板
 */
class SfmRs485 extends BoardService
{
    private static int $queue=1;
    //入场语音
    public static function entryVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType)
    {
        $plate_number = $plate->plate_number;
        if ($rulesType == ParkingRules::RULESTYPE('月租车') || $rulesType == ParkingRules::RULESTYPE('VIP车')) {
            $text =self::monthlyVoice($barrier,$plate,'voice',$plate_number);
        } else {
            $text =$plate_number . '|欢迎光临';
        }
        $json=[
            'actionName'=>'directTextsDisplay',
            'voiceText'=>$text
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //入场显示
    public static function entryDisplay(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType)
    {
        $frames = [];
        $frames[] = $plate->plate_number;
        $frames[] = '一车一杆';
        $frames[] = ParkingRules::RULESTYPE[$rulesType];
        $frames[] = '减速慢行';
        if ($rulesType == ParkingRules::RULESTYPE('月租车') || $rulesType == ParkingRules::RULESTYPE('VIP车')) {
            $frames[1]=self::monthlyScreen($barrier,$plate,'screen');
        } else if ($rulesType == ParkingRules::RULESTYPE('储值车')) {
            $frames[1]='余额'.formatNumber($plate->cars->balance).'元';
        }
        $json=[
            'actionName'=>'directTextsDisplay',
            'pureTextsDisplay'=>implode('\n',$frames),
            'displayPageTimeout'=>120,
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //请缴费语音
    public static function payVoice(ParkingPlate $plate,ParkingRecordsPay $recordsPay)
    {
        throw new \Exception('暂不支持此功能');
    }

    //免费离场语音
    public static function freeLeaveVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType)
    {
        if($rulesType==ParkingRules::RULESTYPE('月租车') || $rulesType==ParkingRules::RULESTYPE('VIP车')){
            $message=self::monthlyVoice($barrier,$plate,'voice',$plate->plate_number);
        }else{
            $message=$plate->plate_number.'|一路平安';
        }
        $json=[
            'actionName'=>'directTextsDisplay',
            'voiceText'=>$message
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //免费离场显示
    public static function freeLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,string $rulesType)
    {
        $time=self::convertTimeToString(time()-$records->entry_time);
        $frames = [];
        $frames[] = $plate->plate_number;
        $frames[] = '免费离场';
        $frames[] = ParkingRules::RULESTYPE[$rulesType];
        $frames[] = '停车时长-'.$time;
        if($rulesType==ParkingRules::RULESTYPE('月租车') || $rulesType==ParkingRules::RULESTYPE('VIP车')){
            $frames[1]=self::monthlyScreen($barrier,$plate,'screen');
        }else if($rulesType==ParkingRules::RULESTYPE('储值车')){
            $frames[1]='本次停车免费，余额'.formatNumber($plate->cars->balance).'元';
        }
        $json=[
            'actionName'=>'directTextsDisplay',
            'pureTextsDisplay'=>implode('\n',$frames),
            'displayPageTimeout'=>120,
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //请缴费显示
    public static function paidLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,ParkingRecordsPay $recordsPay,string $rulesType)
    {
        $time=self::convertTimeToString(time()-$records->entry_time);
        $fee=formatNumber($recordsPay->pay_price).'元';
        $ruletitle=ParkingRules::RULESTYPE[$rulesType];
        $top=<<<EOF
        <div>{$plate->plate_number}，{$ruletitle}</div>
        <div>停车时长 - {$time}</div>
        <div style="color: red;">请支付 - {$fee}</div>
EOF;
        $voicetext = $plate->plate_number . '|请缴费|' . $fee;
        $qrcode=get_domain('api').'/qrcode/exit?serialno='.$barrier->serialno;
        $filecontent=self::getQrcodeView($top,'请扫码付费','',$qrcode);
        file_put_contents(root_path().'public/h5/barrier/'.$barrier->serialno.'.html',$filecontent);
        $json=[
            'actionName'=>'setWebAddressRender',
            'voiceText'=>$voicetext,
            'displayPageTimeout'=>120,
            'webAddressUrl'=>get_domain('api').'/h5/barrier/'.$barrier->serialno.'.html'
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //开闸异常显示
    public static function openGateExceptionDisplay(ParkingBarrier $barrier,string $message)
    {
        if(mb_strpos($message,'欠费')!==false && mb_strpos($message,'入场码')!==false){
            $config=[
                'appid'=>site_config("addons.uniapp_mpapp_id"),
                'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
            ];
            $qrcode= Qrcode::createQrcode('parking-entry-qrcode',$barrier->serialno,60*15);
            $wechat=new \WeChat\Qrcode($config);
            $ticket = $wechat->create($qrcode->id,60*15)['ticket'];
            $url=$wechat->url($ticket);
            $filecontent=self::getQrcodeView($message,'请扫码付费',$url);
            file_put_contents(root_path().'public/h5/barrier/'.$barrier->serialno.'.html',$filecontent);
            $json=[
                'actionName'=>'setWebAddressRender',
                'voiceText'=>'禁止通行',
                'displayPageTimeout'=>120,
                'webAddressUrl'=>get_domain('api').'/h5/barrier/'.$barrier->serialno.'.html'
            ];
            $dataStream=self::packJsonToBinary($json);
            return $dataStream;
        }
        if(mb_strpos($message,'欠费')!==false && mb_strpos($message,'出场码')!==false){
            $qrcode=get_domain('api').'/qrcode/exit?serialno='.$barrier->serialno;
            $filecontent=self::getQrcodeView($message,'请扫码付费','',$qrcode);
            file_put_contents(root_path().'public/h5/barrier/'.$barrier->serialno.'.html',$filecontent);
            $json=[
                'actionName'=>'setWebAddressRender',
                'voiceText'=>'禁止通行',
                'displayPageTimeout'=>120,
                'webAddressUrl'=>get_domain('api').'/h5/barrier/'.$barrier->serialno.'.html'
            ];
            $dataStream=self::packJsonToBinary($json);
            return $dataStream;
        }
        $json=[
            'actionName'=>'directTextsDisplay',
            'pureTextsDisplay'=>$message,
            'displayPageTimeout'=>120,
            'voiceText'=>'禁止通行'
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //余额不足语音
    public static function insufficientBalanceVoice()
    {
        throw new \Exception('暂不支持此功能');
    }

    //余额不足显示
    public static function insufficientBalanceScreen(ParkingBarrier $barrier,string $plate_number)
    {
        $json=[
            'actionName'=>'directTextsDisplay',
            'pureTextsDisplay'=>$plate_number.'\n储值车\n余额不足\n请充值',
            'voiceText'=>'余额不足|请充值',
            'displayPageTimeout'=>120,
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //支付成功语音
    public static function paySuccessVoice()
    {
        throw new \Exception('暂不支持此功能');
    }

    //支付成功显示
    public static function paySuccessScreen(ParkingBarrier $barrier,ParkingRecords $records)
    {
        $time=self::convertTimeToString(time()-$records->entry_time);
        $frames = [];
        $frames[] = $records->plate_number;
        $frames[] = '已付款';
        $frames[] = ParkingRules::RULESTYPE[$records->rules_type];
        $frames[] = '停车时长-'.$time;
        if($records->rules_type==ParkingRules::RULESTYPE('储值车')){
            $fee=formatNumber($records->pay_fee);
            $balance=0;
            $cars=ParkingCars::find($records->cars_id);
            if($cars){
                $balance=$cars->balance;
            }
            $frames[1] = '已付款'.$fee.'元，余额'.formatNumber($balance).'元';
        }
        $json=[
            'actionName'=>'directTextsDisplay',
            'pureTextsDisplay'=>implode('\n',$frames),
            'displayPageTimeout'=>120,
            'voiceText'=>'支付成功|请通行'
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //禁止通行语音
    public static function noEntryVoice()
    {
        throw new \Exception('暂不支持此功能');
    }

    //人工确认语音
    public static function confirmVoice(string $plate_number)
    {
        throw new \Exception('暂不支持此功能');
    }

    //人工确认显示
    public static function confirmDisplay(ParkingBarrier $barrier,string $plate_number)
    {
        $json=[
            'actionName'=>'directTextsDisplay',
            'pureTextsDisplay'=>'请人工确认',
            'displayPageTimeout'=>120,
            'voiceText'=>$plate_number.'|请人工确认'
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //设置广告
    public static function setAdvertisement(int $line,string $text)
    {
        throw new \Exception('暂不支持此功能');
    }

    //设置音量
    public static function setVolume(int $step,int $voice)
    {
        throw new \Exception('暂不支持此功能');
    }

    //无入场记录放行显示
    public static function noEntryRecordDisplay(ParkingBarrier $barrier,string $plate_number)
    {
        $json=[
            'actionName'=>'directTextsDisplay',
            'pureTextsDisplay'=>$plate_number.'\n无入场记录\n减速慢行\n一车一杆',
            'displayPageTimeout'=>120,
            'voiceText'=>'无入场记录|直接放行'
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
    }

    //无入场记录放行语音
    public static function noEntryRecordVoice()
    {
        throw new \Exception('暂不支持此功能');
    }

    //内场放行显示
    public static function insidePassDisplay(ParkingBarrier $barrier,string $plate_number)
    {
        $json=[
            'actionName'=>'directTextsDisplay',
            'displayPageTimeout'=>120,
            'pureTextsDisplay'=>$plate_number.'\n内场通道\n减速慢行\n一车一杆',
        ];
        $dataStream=self::packJsonToBinary($json);
        return $dataStream;
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
        if($barrier->barrier_type=='entry'){
            $config=[
                'appid'=>site_config("addons.uniapp_mpapp_id"),
                'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
            ];
            $qrcode= Qrcode::createQrcode('parking-entry-qrcode',$barrier->serialno,60*15);
            $wechat=new \WeChat\Qrcode($config);
            $ticket = $wechat->create($qrcode->id,60*15)['ticket'];
            $url=$wechat->url($ticket);
            $filecontent=self::getQrcodeView('无牌车','请扫码入场',$url);
            file_put_contents(root_path().'public/h5/barrier/'.$barrier->serialno.'.html',$filecontent);
            $json=[
                'actionName'=>'setWebAddressRender',
                'voiceText'=>'无牌车请扫码',
                'displayPageTimeout'=>120,
                'webAddressUrl'=>get_domain('api').'/h5/barrier/'.$barrier->serialno.'.html'
            ];
            $dataStream=self::packJsonToBinary($json);
            return $dataStream;
        }
        if($barrier->barrier_type=='exit'){
            $qrcode=get_domain('api').'/qrcode/exit?serialno='.$barrier->serialno;
            $filecontent=self::getQrcodeView('无牌车','请扫码出场','',$qrcode);
            file_put_contents(root_path().'public/h5/barrier/'.$barrier->serialno.'.html',$filecontent);
            $json=[
                'actionName'=>'setWebAddressRender',
                'voiceText'=>'无牌车请扫码',
                'displayPageTimeout'=>120,
                'webAddressUrl'=>get_domain('api').'/h5/barrier/'.$barrier->serialno.'.html'
            ];
            $dataStream=self::packJsonToBinary($json);
            return $dataStream;
        }
    }

    private static function hexstr2hex($hexString)
    {
        $decimalArray = [];
        for ($i = 0; $i < strlen($hexString); $i += 2) {
            $hexPair = substr($hexString, $i, 2);
            $decimalArray[] = hexdec($hexPair);
        }
        $dataStream = pack('C*', ...$decimalArray);
        return $dataStream;
    }

    /**
     * 按RS485协议打包JSON数据为二进制
     * @param string $jsonData 待打包的JSON字符串（对应协议中data字段内容）
     * @param int $seq 发送数据序号（0-255，方便统计数据）
     * @return string 打包后的二进制字符串
     * @throws Exception 数据长度超出限制时抛出异常
     */
    private static function packJsonToBinary(array $jsonData):string
    {
        $seq=self::getSeq();
        $jsonData=json_encode($jsonData,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonData = str_replace('\\n', "\n", $jsonData);
        // 1. 协议头：固定 0xCA（字节0）、0xAC（字节1）
        $header = pack('CC', 0xCA, 0xAC);
        // 2. 处理 JSON 内容：UTF-8 编码，计算字节长度
        $jsonBytes = mb_convert_encoding($jsonData, 'UTF-8');
        $jsonLen = strlen($jsonBytes); // 获取 JSON 实际字节长度
        // 3. 关键：lenPack 实现（32位无符号大端序，自动补0至4字节）
        $lenPack = pack('V', $jsonLen); // 自动按大端序转换为 4字节，无需手动补位
        // 4. 序号：1字节（限制 0-255，超出则取低8位）
        $seqPack = pack('C', $seq & 0xFF);
        // 5. 异或校验：仅对 JSON 字节数组进行异或运算（符合文档规则）
        $checksum = 0;
        foreach (str_split($jsonBytes) as $byte) {
            $checksum ^= ord($byte);
        }
        $checksumPack = pack('C', $checksum);
        $bin=$header . $lenPack . $seqPack . $jsonBytes . $checksumPack;
        $hexString=bin2hex($bin);
        $decimalArray = [];
        for ($i = 0; $i < strlen($hexString); $i += 2) {
            $hexPair = substr($hexString, $i, 2);
            $decimalArray[] = hexdec($hexPair);
        }
        $dataStream = pack('C*', ...$decimalArray);
        return $dataStream;
    }

    private static function getSeq()
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            self::$queue++;
            return (time()+self::$queue)%9+1;
        }
        self::$queue++;
        return self::$queue%9+1;
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
                return $plate_number.'，有效期，'.$day.'天';
            }
        }
        //播放祝福语，剩余N天时显示剩余天数
        if($type=='voice' && $barrier->monthly_voice=='last'){
            $time=$plate->cars->endtime-time();
            $lday=ceil($time/86400);
            if($lday<=$barrier->monthly_voice_day){
                if($time<=0){
                    return $overdate_voice;
                }else{
                    $day=intval($time/24/3600);
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
}