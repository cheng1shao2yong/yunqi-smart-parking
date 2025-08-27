<?php
declare(strict_types=1);

namespace app\common\service\barrier;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingBarrierTjtc;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingScreen;
use app\common\model\parking\ParkingTrigger;
use app\common\service\BarrierService;
use app\common\service\InsideService;
use app\common\service\msg\WechatMsg;
use app\common\service\ParkingService;
use app\common\library\AliyunOss;
use think\facade\Cache;

defined('DS') or define('DS',DIRECTORY_SEPARATOR);
class Zhenshi extends BarrierService {

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

    const PLATE_TYPE = [
        0 => 'unknown',
        1 => 'blue',
        2 => 'yellow',
        3 => 'white',
        4 => 'black',
        5 => 'green',
        6 => 'yellow-green'
    ];

    const TRIGGER_TYPE = [
        1  => '自动触发类型',
        2  => '外部输入触发（IO输入）',
        4  => '软件触发（SDK）',
        8  => '虚拟线圈触发',
        64 => '车滞留事件',
        65 => '车滞留恢复事件',
        66 => '车折返事件',
    ];

    const SUBJECT=[
        '/device/message/up/ivs_result'=>0,
        '/device/message/down/gpio_out/reply'=>0,
        '/device/message/up/lanectrl_result'=>0,
        '/device/message/up/snapshot'=>0
    ];

    const ALIVE=[
        '/device/message/up/keep_alive'=>0
    ];

    const ACTION=[
        '开闸'=>'gpio_out',
        '关闸'=>'gpio_out',
        '入场语音'=>'serial_data',
        '入场显示'=>'serial_data',
        '请缴费语音'=>'serial_data',
        '免费离场语音'=>'serial_data',
        '已付款语音'=>'serial_data',
        '缴费离场显示'=>'serial_data',
        '免费离场显示'=>'serial_data',
        '已付款显示'=>'serial_data',
        '开闸异常显示'=>'serial_data',
        '余额不足语音'=>'serial_data',
        '支付成功语音'=>'serial_data',
        '禁止通行语音'=>'serial_data',
        '人工确认语音'=>'serial_data',
        '人工确认显示'=>'serial_data',
        '主动拍照'=>'snapshot',
        '主动识别'=>'ivs_trigger',
        '通道记录'=>'screen_record',
        '设置广告'=>'serial_data',
        '设置音量'=>'serial_data',
        '设置时间'=>'set_time',
        '内场放行显示'=>'serial_data',
        '无入场记录放行显示'=>'serial_data',
        '无入场记录放行语音'=>'serial_data',
        '离线白名单'=>'white_list_operator',
    ];
    
    public static function get_subject(string $serialno)
    {
        $arr=[];
        foreach (self::SUBJECT as $key=>$num){
            $arr[$serialno.$key]=$num;
        }
        return $arr;
    }
    
    public static function get_keep_alive(string $serialno)
    {
        $arr=[];
        foreach (self::ALIVE as $key=>$num){
            $arr[$serialno.$key]=$num;
        }
        return $arr;
    }

    public static function getUniqidName(ParkingBarrier $barrier)
    {
        return 'id';
    }

    public static function isOnline(ParkingBarrier $barrier):bool
    {
        $now=time();
        $updatetime=Cache::get('barrier-online-'.$barrier->serialno);
        if($updatetime && $updatetime>=$now-60){
            return true;
        }
        return false;
    }

    public static function getTopic(ParkingBarrier $barrier,string $name)
    {
        $action=self::ACTION[$name];
        switch ($action){
            case 'serial_data':
            case 'gpio_out':
            case 'snapshot':
            case 'set_time':
            case 'ivs_trigger':
            case 'white_list_operator':
                return $barrier->serialno.'/device/message/down/'.$action;
            case 'screen_record':
                return $barrier->serialno.'/barrier/screen/record/message/'.$barrier->parking_id;
        }
    }

    public static function getMessage(ParkingBarrier $barrier,string $name,array $param=[])
    {
        $action=self::ACTION[$name];
        $id=uniqid();
        $body=[];
        switch ($name){
            case '设置广告':
                $setting=[
                    $param['line']+1,
                    ($param['line']%2)===0?1:2,
                    ''
                ];
                $arr=self::stringToGbkHexArray($param['text']);
                $message=array_merge($setting,$arr);
                $data=self::convertArrayToHex($message,0x25);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '设置时间':
                $body=$param['time'];
                break;
            case '设置音量':
                if($param['step']==1){
                    $data=self::convertArrayToHex([$param['voice']],0xF0);
                    $dataStream = pack('C*', ...$data);
                    $body=[
                        'serialData'=>[
                            [
                                'serialChannel'=>0,
                                'data'=>base64_encode($dataStream),
                                'dataLen'=>strlen($dataStream),
                            ]
                        ]
                    ];
                }
                if($param['step']==2){
                    $data=self::convertArrayToHex([0x01],0x22);
                    $dataStream = pack('C*', ...$data);
                    $body=[
                        'serialData'=>[
                            [
                                'serialChannel'=>0,
                                'data'=>base64_encode($dataStream),
                                'dataLen'=>strlen($dataStream),
                            ]
                        ]
                    ];
                }
                break;
            case '通道记录':
                $body=$param;
                break;
            case '离线白名单':
                if($param['action']=='update_or_add'){
                    $arr=[];
                    foreach ($param['cars'] as $cars){
                        foreach ($cars->plates as $plate){
                            $arr[]=[
                                "plate"=>$plate->plate_number,
                                "create_time"=>date('Y-m-d H:i:s',time()),
                                "enable"=>1,
                                "enable_time"=>date('Y-m-d H:i:s',$cars->starttime),
                                "overdue_time"=>date('Y-m-d H:i:s',$cars->endtime),
                                "time_seg_enable"=>0,
                                "need_alarm"=>0
                            ];
                        }
                    }
                    $body=[
                        "operator_type"=>$param['action'],
                        "dldb_rec"=>$arr
                    ];
                }
                if($param['action']=='delete'){
                    $arr=[];
                    foreach ($param['cars'] as $cars){
                        foreach ($cars->plates as $plate){
                            $arr[]=$plate->plate_number;
                        }
                    }
                    $body=[
                        "operator_type"=>$param['action'],
                        "plate"=>$arr
                    ];
                }
                break;
            case '开闸':
                $body=[
                    'delay'=>500,
                    'io'=>0,
                    'value'=>2
                ];
                break;
            case '关闸':
                $body=[
                    'delay'=>500,
                    'io'=>1,
                    'value'=>2
                ];
                break;
            case '开闸异常显示':
                $l1=self::convertScreenline($barrier->screen_time,1,'禁止通行');
                $l2=self::convertScreenline($barrier->screen_time,2,$param['message']);
                $l3=self::convertScreenline($barrier->screen_time,1,'一车一杆');
                $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
                $message=array_merge($l1,$l2,$l3,$l4);
                $data=self::convertArrayToHex($message,0x29);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '内场放行显示':
            case '无入场记录放行显示':
                $l1=self::convertScreenline($barrier->screen_time,1,'免费放行');
                $l2=self::convertScreenline($barrier->screen_time,2,$param['message']);
                $l3=self::convertScreenline($barrier->screen_time,1,'一车一杆');
                $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
                $message=array_merge($l1,$l2,$l3,$l4);
                $data=self::convertArrayToHex($message,0x29);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '主动拍照':
            case '主动识别':
                $body=[];
                break;
            case '支付成功语音':
                $data=self::convertArrayToHex([0x7e,0x18,0x28,0x02],0x22);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '入场语音':
                $plate_number=$param['plate']->plate_number;
                $istemp=false;
                if(str_starts_with($param['plate']->plate_number,'临')){
                    $plate_number=mb_substr($plate_number,1);
                    $istemp=true;
                }
                if($istemp){
                    $plate_number=[0x7f,0x19,...self::stringToGbkHexArray($plate_number)];
                }else{
                    $plate_number=self::stringToGbkHexArray($plate_number);
                }
                if($param['rulesType']==ParkingRules::RULESTYPE('月租车') || $param['rulesType']==ParkingRules::RULESTYPE('VIP车')){
                    $message=self::monthlyVoiceAndScreen($barrier,$param['plate'],'voice',$plate_number);
                }else{
                    $message=array_merge($plate_number,[0x01]);
                }
                $data=self::convertArrayToHex($message,0x22);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '入场显示':
                $l1=self::convertScreenline($barrier->screen_time,1,$param['plate']->plate_number);
                $l2=self::convertScreenline($barrier->screen_time,2,ParkingRules::RULESTYPE[$param['rulesType']]);
                $l3=self::convertScreenline($barrier->screen_time,1,'一车一杆');
                $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
                if($param['rulesType']==ParkingRules::RULESTYPE('月租车') || $param['rulesType']==ParkingRules::RULESTYPE('VIP车')){
                    $l3=self::monthlyVoiceAndScreen($barrier,$param['plate'],'screen',$l3);
                }else if($param['rulesType']==ParkingRules::RULESTYPE('储值车')){
                    $l3=self::convertScreenline($barrier->screen_time,1,'余额'.formatNumber($param['plate']->cars->balance).'元');
                }
                $message=array_merge($l1,$l2,$l3,$l4);
                $data=self::convertArrayToHex($message,0x29);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '免费离场语音':
            case '已付款语音':
                $plate_number=self::stringToGbkHexArray($param['plate']->plate_number);
                if($param['rulesType']==ParkingRules::RULESTYPE('月租车') || $param['rulesType']==ParkingRules::RULESTYPE('VIP车')){
                    $message=self::monthlyVoiceAndScreen($barrier,$param['plate'],'voice',$plate_number);
                }else{
                    $message=array_merge($plate_number,[0x02]);
                }
                $data=self::convertArrayToHex($message,0x22);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '请缴费语音':
                $plate_number=self::stringToGbkHexArray($param['plate']->plate_number);
                $fee=formatNumber($param['recordsPay']->pay_price);
                $string=self::convertNumberToChinese($fee).'元';
                $string=self::stringToGbkHexArray($string);
                $message=array_merge($plate_number,[0x0B],$string);
                $data=self::convertArrayToHex($message,0x22);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '余额不足语音':
                $data=self::convertArrayToHex([0x23,0x04],0x22);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '禁止通行语音':
                $data=self::convertArrayToHex([0x2B],0x22);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '无入场记录放行语音':
                $str=self::stringToGbkHexArray('无');
                $data=self::convertArrayToHex([...$str,0x5C,0x16,0x68],0x22);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '人工确认语音':
                $plate_number=self::stringToGbkHexArray($param['plate_number']);
                $message=array_merge($plate_number,[0x1F]);
                $data=self::convertArrayToHex($message,0x22);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '人工确认显示':
                $l1=self::convertScreenline($barrier->screen_time,1,$param['plate_number']);
                $l2=self::convertScreenline($barrier->screen_time,2,'人工确认');
                $l3=self::convertScreenline($barrier->screen_time,1,'一车一杆');
                $l4=self::convertScreenline($barrier->screen_time,2,'减速慢行');
                $message=array_merge($l1,$l2,$l3,$l4);
                $data=self::convertArrayToHex($message,0x29);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '缴费离场显示':
                $l1=self::convertScreenline($barrier->screen_time,1,$param['plate']->plate_number);
                $l2=self::convertScreenline($barrier->screen_time,2,ParkingRules::RULESTYPE[$param['rulesType']]);
                $fee=formatNumber($param['recordsPay']->pay_price);
                $string=$fee.'元';
                $l3=self::convertScreenline($barrier->screen_time,1,$string);
                $time=self::convertTimeToString(time()-$param['records']->entry_time);
                $l4=self::convertScreenline($barrier->screen_time,2,$time);
                $message=array_merge($l1,$l2,$l3,$l4);
                $data=self::convertArrayToHex($message,0x29);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '已付款显示':
                $time=self::convertTimeToString(time()-$param['records']->entry_time);
                $l1=self::convertScreenline($barrier->screen_time,1,$param['plate']->plate_number);
                $l2=self::convertScreenline($barrier->screen_time,2,ParkingRules::RULESTYPE[$param['rulesType']]);
                $l3=self::convertScreenline($barrier->screen_time,1,'已付款');
                $l4=self::convertScreenline($barrier->screen_time,2,$time);
                if($param['rulesType']==ParkingRules::RULESTYPE('储值车')){
                    $fee=formatNumber($param['records']->pay_fee);
                    $l3=self::convertScreenline($barrier->screen_time,2,'已付款'.$fee.'元，余额'.formatNumber($param['plate']->cars->balance).'元');
                }
                $message=array_merge($l1,$l2,$l3,$l4);
                $data=self::convertArrayToHex($message,0x29);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
            case '免费离场显示':
                $time=self::convertTimeToString(time()-$param['records']->entry_time);
                $l1=self::convertScreenline($barrier->screen_time,1,$param['plate']->plate_number);
                $l2=self::convertScreenline($barrier->screen_time,2,ParkingRules::RULESTYPE[$param['rulesType']]);
                $l3=self::convertScreenline($barrier->screen_time,1,'免费');
                $l4=self::convertScreenline($barrier->screen_time,2,$time);
                if($param['rulesType']==ParkingRules::RULESTYPE('月租车') || $param['rulesType']==ParkingRules::RULESTYPE('VIP车')){
                    $l3=self::monthlyVoiceAndScreen($barrier,$param['plate'],'screen',$l3);
                }else if($param['rulesType']==ParkingRules::RULESTYPE('储值车')){
                    $l3=self::convertScreenline($barrier->screen_time,2,'本次停车免费，余额'.formatNumber($param['plate']->cars->balance).'元');
                }
                $message=array_merge($l1,$l2,$l3,$l4);
                $data=self::convertArrayToHex($message,0x29);
                $dataStream = pack('C*', ...$data);
                $body=[
                    'serialData'=>[
                        [
                            'serialChannel'=>0,
                            'data'=>base64_encode($dataStream),
                            'dataLen'=>strlen($dataStream),
                        ]
                    ]
                ];
                break;
        }
        $r=[
            'id'=>$id,
            'sn'=>$barrier->serialno,
            'name'=>$action,
            'version'=>'1.0',
            'timestamp'=>time(),
            'payload'=>[
                'type'=>$action,
                'body'=>$body
            ]
        ];
        return $r;
    }

    public function open():bool
    {
        if($this->recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $this->recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            Utils::send($this->barrier,'开闸');
            return true;
        }
        return true;
    }

    public function payOpen()
    {
        Utils::send($this->barrier,'支付成功语音');
        Utils::send($this->barrier,'开闸',[],function($res){
            //没开闸的情况下再开一次
            if(!$res){
                Utils::send($this->barrier,'开闸');
            }
        },2);
    }

    public function inFieldOpen():bool
    {
        if($this->recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $this->recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            Utils::send($this->barrier,'开闸');
            Utils::send($this->barrier,'内场放行显示',['message'=>'内场开闸放行']);
            if($this->barrier->barrier_type=='entry'){
                Utils::send($this->barrier,'入场语音',['plate'=>$this->plate,'rulesType'=>$this->rulesType]);
            }
            if($this->barrier->barrier_type=='exit'){
                Utils::send($this->barrier,'免费离场语音',['plate'=>$this->plate,'rulesType'=>$this->rulesType]);
            }
            return true;
        }
        return true;
    }

    public function havaNoEntryOpen(string $message,bool $open=true)
    {
        if($this->recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $this->recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            if($open){
                Utils::send($this->barrier,'开闸');
            }
            Utils::send($this->barrier,'无入场记录放行显示',['message'=>$message]);
            Utils::send($this->barrier,'无入场记录放行语音');
        }
    }

    public function showLastSpace(int $last_space)
    {
        if($this->recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $this->recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            $show_last_space=json_decode($this->barrier->show_last_space,true);
            $title=str_replace('{剩余车位}',(string)$last_space,$show_last_space['text']);
            Utils::send($this->barrier,'设置广告',[
                'line'=>$show_last_space['line'],
                'text'=>$title
            ]);
        }
    }

    public function voice(string $action)
    {
        if($this->recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $this->recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            if($action=='entry'){
                Utils::send($this->barrier,'入场语音',['plate'=>$this->plate,'rulesType'=>$this->rulesType]);
            }
            if($action=='exit'){
                if(isset($this->recordsPay)){
                    if($this->recordsPay->pay_id){
                        Utils::send($this->barrier,'已付款语音',['plate'=>$this->plate,'rulesType'=>$this->rulesType,'records'=>$this->records,'recordsPay'=>$this->recordsPay]);
                    }else{
                        Utils::send($this->barrier,'请缴费语音',['plate'=>$this->plate,'rulesType'=>$this->rulesType,'records'=>$this->records,'recordsPay'=>$this->recordsPay]);
                    }
                }else{
                    Utils::send($this->barrier,'免费离场语音',['plate'=>$this->plate,'rulesType'=>$this->rulesType]);
                }
            }
        }
        return $this;
    }

    public function screen(string $action)
    {
        if($this->recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $this->recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            if($action=='entry'){
                Utils::send($this->barrier,'入场显示',['plate'=>$this->plate,'rulesType'=>$this->rulesType]);
            }
            if($action=='exit'){
                if(isset($this->recordsPay)){
                    if($this->recordsPay->pay_id){
                        Utils::send($this->barrier,'已付款显示',['plate'=>$this->plate,'rulesType'=>$this->rulesType,'records'=>$this->records,'recordsPay'=>$this->recordsPay]);
                    }else{
                        Utils::send($this->barrier,'缴费离场显示',['plate'=>$this->plate,'rulesType'=>$this->rulesType,'records'=>$this->records,'recordsPay'=>$this->recordsPay]);
                    }
                }else{
                    Utils::send($this->barrier,'免费离场显示',['plate'=>$this->plate,'rulesType'=>$this->rulesType,'records'=>$this->records]);
                }
            }
        }
        return $this;
    }

    public function showPayQRCode()
    {

    }

    public function showEntryQRCode()
    {

    }

    public function invoke(array $message)
    {
        $name=$message['name'];
        if(!method_exists($this,$name)){
            return false;
        }
        return $this->$name($message,$message['payload']);
    }

    private function serial_data($message,$payload)
    {
        return false;
    }

    private function gpio_out($message,$payload)
    {
        return true;
    }

    //主动拍照
    private function snapshot($message,$payload)
    {
        if(isset($payload['image_content']) && $payload['image_content']){
            $file=$this->barrier->parking_id.'/'.date('Ymd').'/'.md5(time().rand(1000,9999)).'.jpg';
            $oss=AliyunOss::instance();
            $imageFile=$oss->upload($file,base64_decode($payload['image_content']));
            Cache::set('barrier-photo-'.$this->barrier->serialno,$imageFile);
        }else if(isset($payload['imgPath']) && $payload['imgPath']){
            $imageFile=base64_decode($payload['imgPath']);
            if(strpos($imageFile,'?')!==false){
                $imageFile=substr($imageFile,0,strpos($imageFile,'?'));
            }
            Cache::set('barrier-photo-'.$this->barrier->serialno,$imageFile);
        }else{
            Cache::set('barrier-photo-'.$this->barrier->serialno,'');
        }
        return false;
    }

    private function lanectrl_result($message,$payload)
    {
        $starttime=microtime(true);
        $serialno = $message['sn'];
        $triggerType = $payload['event_type'];
        $plate_type = base64_decode($payload['result'][0]['plate_result']['color_type']);
        $imageUrl='';
        if(isset($payload['result'][0]['full_image'])){
            $imageUrl=base64_decode($payload['result'][0]['full_image']['full_image_path']);
            if(strpos($imageUrl,'?')!==false){
                $imageUrl=substr($imageUrl,0,strpos($imageUrl,'?'));
            }
        }
        $plate_number = $payload['result'][0]['plate_result']['license'];
        $plate_number=base64_decode($plate_number);
        $triggerTypeEmun=[
            73=>'人滞留解除',
            81=>'人滞留',
            82=>'车滞留',
            83=>'机动车辆通行事件',
            84=>'机动车辆折返事件',
            85=>'跟车事件',
            96=>'人员拥堵',
            97=>'人员拥堵解除',
            112=>'非机动车滞留',
            113=>'非机动车滞留解除',
        ];
        $trigger=new ParkingTrigger();
        $triggerData=[
            'parking_id'=>$this->barrier->parking_id,
            'supplier'=>'成都臻视',
            'serialno'=>$serialno,
            'trigger_type'=>isset($triggerTypeEmun[$triggerType])?$triggerTypeEmun[$triggerType]:$triggerType,
            'plate_number'=>$plate_number,
            'plate_type'=>$plate_type,
            'image'=>$imageUrl,
            'createtime'=>time(),
        ];
        if($triggerType!=84){
            $usetime=round(microtime(true)-$starttime,5);
            $triggerData['usetime']=$usetime;
            $trigger->save($triggerData);
            return;
        }
        $parentBarrier=ParkingBarrier::find($this->barrier->pid);
        if($parentBarrier){
            $xtrigger=ParkingTrigger::where(['parking_id'=>$this->barrier->parking_id,'serialno'=>$parentBarrier->serialno])->order('id desc')->find();
            if($xtrigger && $xtrigger->message=='正常开启通道'){
                Utils::send($parentBarrier,'关闸');
                $records=ParkingRecords::where(['plate_number'=>$plate_number,'parking_id'=>$this->barrier->parking_id])->order('id desc')->find();
                //如果是入口，且有入场记录则删除
                if(
                    $records &&
                    $records->status==ParkingRecords::STATUS('正在场内')
                    && (time()-$records->entry_time)<60*5
                    && $parentBarrier->barrier_type=='entry'
                ){
                    $records->delete();
                }
                //如果是出口，且已经出场则修改状态
                if(
                    $records &&
                    $records->status!=(int)ParkingRecords::STATUS('正在场内')
                    && (time()-$records->exit_time)<60*5
                    && $parentBarrier->barrier_type=='exit'
                ){
                    $records->status=ParkingRecords::STATUS('正在场内');
                    $records->save();
                }
                $usetime=round(microtime(true)-$starttime,5);
                $triggerData['usetime']=$usetime;
                $triggerData['message']='车辆折返关闸';
                $trigger->save($triggerData);
            }
        }
    }

    private function ivs_result($message,$payload)
    {
        //辅机
        if($this->barrier->pid>0){
            return false;
        }
        $starttime=microtime(true);
        $serialno = $payload['AlarmInfoPlate']['serialno'];
        $triggerType = $payload['AlarmInfoPlate']['result']['PlateResult']['triggerType'];
        $imageUrl='';
        if(isset($payload['AlarmInfoPlate']['result']['PlateResult']['imagePath'])){
            $imageUrl=base64_decode($payload['AlarmInfoPlate']['result']['PlateResult']['imagePath']);
            if(strpos($imageUrl,'?')!==false){
                $imageUrl=substr($imageUrl,0,strpos($imageUrl,'?'));
            }
        }
        $plate_number = $payload['AlarmInfoPlate']['result']['PlateResult']['license'];
        $plate_number=base64_decode($plate_number);
        ParkingScreen::sendBlackMessage($this->barrier,'识别到车牌号'.$plate_number);
        $plate_type = self::PLATE_TYPE[$payload['AlarmInfoPlate']['result']['PlateResult']['colorType']];
        $trigger=new ParkingTrigger();
        $triggerData=[
            'parking_id'=>$this->barrier->parking_id,
            'supplier'=>'成都臻视',
            'serialno'=>$serialno,
            'trigger_type'=>isset(self::TRIGGER_TYPE[$triggerType])?self::TRIGGER_TYPE[$triggerType]:$triggerType,
            'plate_number'=>$plate_number,
            'plate_type'=>$plate_type,
            'image'=>$imageUrl,
            'createtime'=>time(),
        ];
        if($this->barrier->tjtc){
            $tjtclist=ParkingBarrierTjtc::cache('barrier_tjtc_'.$this->barrier->parking_id)->where(['parking_id'=>$this->barrier->parking_id])->select();
            foreach ($tjtclist as $tjtc){
                $tjtc_serialno=explode(',',$tjtc->serialno);
                $tjtc_key=array_search($serialno,$tjtc_serialno);
                $tjtc_times=time()-$tjtc->times;
                if($tjtc_key!==false){
                    unset($tjtc_serialno[$tjtc_key]);
                    $hastriger=ParkingTrigger::where(function ($query) use ($plate_number,$tjtc_times,$tjtc_serialno){
                        $query->where('plate_number','=',$plate_number);
                        $query->where('createtime','>',$tjtc_times);
                        $query->where('serialno','in',$tjtc_serialno);
                    })->count();
                    if($hastriger){
                        $usetime=round(microtime(true)-$starttime,5);
                        $triggerData['usetime']=$usetime;
                        $triggerData['message']='30秒内异常识别';
                        $trigger->save($triggerData);
                        ParkingScreen::sendRedMessage($this->barrier,$plate_number.'开闸失败，停留时间过短');
                        Utils::send($this->barrier,'开闸异常显示',['message'=>'开闸失败，停留时间过短']);
                        return false;
                    }
                }
            }
        }
        if($plate_number=='_无_' || $plate_number=='无牌车'){
            if($this->barrier->support_led){
                $this->showEntryQRCode();
            }
            /* @var ParkingBarrier $xbarrier*/
            $xbarrier=ParkingBarrier::where(['pid'=>$this->barrier->id,'support_led'=>1])->find();
            if($xbarrier){
                /* @var BarrierService $xservice*/
                $xservice=$xbarrier->getBarrierService();
                $xservice->showEntryQRCode();
            }
            $usetime=round(microtime(true)-$starttime,5);
            $triggerData['usetime']=$usetime;
            $triggerData['message']='没有识别到车牌号';
            $trigger->save($triggerData);
            $txg='';
            if($this->barrier->barrier_type=='entry'){
                $txg='入场';
            }
            if($this->barrier->barrier_type=='exit'){
                $txg='出场';
            }
            ParkingScreen::sendRedMessage($this->barrier,'识别到无牌车'.$txg);
            return false;
        }
        $barrier_type=$this->barrier->barrier_type;
        $trigger_type=$this->barrier->trigger_type;
        $service=false;
        try{
            if($trigger_type=='infield' || $trigger_type=='outfield'){
                $parking=Parking::cache('parking_'.$this->barrier->parking_id,24*3600)->withJoin(['setting'])->find($this->barrier->parking_id);
                $theadkey=md5($this->barrier->parking_id.'-'.$this->barrier->id.'-'.$plate_number);
                $service = ParkingService::newInstance([
                    'parking' => $parking,
                    'barrier'=>$this->barrier,
                    'plate_number' => $plate_number,
                    'plate_type' => $plate_type,
                    'photo' => $imageUrl,
                    'records_type'=>ParkingRecords::RECORDSTYPE('自动识别'),
                    'entry_time' =>($this->barrier->barrier_type=='entry')?time():null,
                    'exit_time' =>($this->barrier->barrier_type=='exit')?time():null
                ],$theadkey);
                if($barrier_type=='entry' && $this->barrier->manual_confirm && $service->isProvisional()){
                    $usetime=round(microtime(true)-$starttime,5);
                    $triggerData['usetime']=$usetime;
                    $triggerData['message']='人工确认';
                    $trigger->save($triggerData);
                    ParkingScreen::sendManualMessage($this->barrier,$trigger->id,$plate_number,$this->barrier->id,$imageUrl);
                    Utils::send($this->barrier,'人工确认语音',['plate_number'=>$plate_number]);
                    Utils::send($this->barrier,'人工确认显示',['plate_number'=>$plate_number]);
                    $service->destroy();
                    return false;
                }
                //外场入场
                if($barrier_type=='entry' && $trigger_type=='outfield'){
                    $isopen=$service->entry();
                    //微信入场通知
                    if($isopen){
                        WechatMsg::entry($parking,$plate_number);
                    }
                }
                //外场出场
                if($barrier_type=='exit' && $trigger_type=='outfield'){
                    $isopen=$service->exit();
                    //微信出场通知
                    if($isopen){
                        WechatMsg::exit($parking,$plate_number);
                    }
                }
                //内场入场
                if($barrier_type=='entry' && $trigger_type=='infield'){
                    $isopen=$service->infieldEntry();
                }
                //内场出场
                if($barrier_type=='exit' && $trigger_type=='infield'){
                    $isopen=$service->infieldExit();
                }
                if($isopen){
                    $triggerData['message']='正常开启通道';
                }
                $service->destroy();
            }
            if($trigger_type=='inside'){
                $insideParking=Parking::cache('parking_'.$this->barrier->parking_id,24*3600)->withJoin(['setting'])->find($this->barrier->parking_id);
                $outsideParking=Parking::cache('parking_'.$insideParking->pid,24*3600)->withJoin(['setting'])->find($insideParking->pid);
                $insideBarrier=$this->barrier;
                $outsideBarrier=ParkingBarrier::where(['serialno'=>$this->barrier->serialno,'parking_id'=>$outsideParking->id])->find();
                $theadkey=md5($insideParking->id.'-'.$outsideParking->id.'-'.$insideBarrier->id.'-'.$outsideBarrier->id.'-'.$plate_number);
                $service = InsideService::newInstance([
                    'insideParking' => $insideParking,
                    'outsideParking' => $outsideParking,
                    'insideBarrier' => $insideBarrier,
                    'outsideBarrier'=>$outsideBarrier,
                    'plate_number' => $plate_number,
                    'plate_type' => $plate_type,
                    'photo' => $imageUrl
                ],$theadkey);
                //场内场入场
                if($barrier_type=='entry'){
                    $isopen=$service->entry();
                }
                //场内场出场
                if($barrier_type=='exit'){
                    $isopen=$service->exit();
                }
                if($isopen){
                    $triggerData['message']='正常开启通道';
                }
                $service->destroy();
            }
        }catch (\Exception $e){
            if($service){
                $service->destroy();
            }
            $message=$e->getMessage();
            $triggerData['message']=$message;
            $triggerData['file']=$e->getFile();
            $triggerData['line']=$e->getLine();
            ParkingScreen::sendRedMessage($this->barrier,$plate_number.'开闸失败，'.$message);
            if($trigger_type=='inside'){
                ParkingScreen::sendRedMessage($outsideBarrier,$plate_number.'开闸失败，'.$message);
            }
            Utils::send($this->barrier,'开闸异常显示',['message'=>$message]);
            if(strpos($message,'余额不足')!==false){
                Utils::send($this->barrier,'余额不足语音');
            }else{
                Utils::send($this->barrier,'禁止通行语音');
            }
        }
        $usetime=round(microtime(true)-$starttime,5);
        $triggerData['usetime']=$usetime;
        $trigger->save($triggerData);
        return false;
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

    private static function convertScreenline(int $time,int $color,string $string)
    {
        $arr=self::stringToGbkHexArray($string);
        $length=count($arr);
        return array_merge([$time,$color,$length],$arr);
    }

    private static function convertTimeToString(int $time){
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

    public static function convertNumberToChinese(mixed $num){
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

    private static function convertArrayToHex(array $message,int $action) {
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
    private static function stringToGbkHexArray(string $str) {
        $gbkStr = mb_convert_encoding($str, 'GBK', 'UTF-8');
        $length = strlen($gbkStr);
        $hexArray = [];
        // 遍历GBK编码字符串的每个字节，并将其转换为16进制
        for ($i = 0; $i < $length; $i++) {
            $hexArray[] = unpack('C', $gbkStr[$i])[1];
        }
        return $hexArray;
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