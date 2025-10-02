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

namespace app\common\service\barrier;

use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\Qrcode;
use app\common\service\board\FkRs485;
use app\common\service\BoardService;
use think\facade\Cache;
use Swoole\Coroutine;

class Utils
{
    /* @var \Redis $redis*/
    private static $redis;

    private static function send($barrier,$name,$param=[],$callback='',$timeout=5)
    {
        if(BoardService::isScreenAction($name) || BoardService::isVoiceAction($name)){
            self::sendScreenOrVoice($barrier,$name,$param);
        }else{
            self::sendCameraAction($barrier,$name,$param,$callback,$timeout);
        }
    }

    private static function sendCameraAction($barrier,$name,$param=[],$callback='',$timeout=5)
    {
        $body=[
            'name'=>$name,
            'topic'=>self::getTopic($barrier,$name),
            'message'=>self::getMessage($barrier,$name,$param)
        ];
        $id=$body['message'][self::getUniqidName($barrier)];
        $redis=self::connectRedis();
        $redis->rpush('mqtt_publish_queue',json_encode($body));
        if($callback){
            $i=0;
            while($i<$timeout*10){
                $result=$redis->get($id);
                if($result){
                    $callback(json_decode($result,true));
                    return;
                }
                Coroutine\System::sleep(0.1);
                $i++;
            }
            $callback(false);
        }
    }

    private static function sendScreenOrVoice($barrier,$name,$param=[])
    {
        //控制屏显
        if(BoardService::isScreenAction($name)){
            $support=$barrier->screen_support;
        }
        //控制语音
        if(BoardService::isVoiceAction($name)){
            $support=$barrier->voice_support;
        }
        if($support=='none'){
            return;
        }
        $supports=explode('-',$support);
        /* @var BoardService $boardClass */
        $boardClass='\\app\\common\\service\\board\\';
        foreach ($supports as $key=>$support){
            //首字母设置成大写
            $boardClass.=ucfirst($support);
        }
        $dataStream='';
        try{
            switch ($name){
                case '入场显示':
                    $dataStream=$boardClass::entryDisplay($barrier,$param['plate'],$param['rulesType']);
                    break;
                case '请缴费显示':
                    $dataStream=$boardClass::paidLeaveDisplay($barrier,$param['plate'],$param['records'],$param['recordsPay'],$param['rulesType']);
                    break;
                case '免费离场显示':
                    $dataStream=$boardClass::freeLeaveDisplay($barrier,$param['plate'],$param['records'],$param['rulesType']);
                    break;
                case '已付款显示':
                    $dataStream=$boardClass::paidDisplay($barrier,$param['plate'],$param['records'],$param['rulesType']);
                    break;
                case '开闸异常显示':
                    $dataStream=$boardClass::openGateExceptionDisplay($barrier,$param['message']);
                    break;
                case '人工确认显示':
                    $dataStream=$boardClass::confirmDisplay($barrier,$param['plate_number']);
                    break;
                case '设置广告':
                    $dataStream=$boardClass::setAdvertisement($param['line'],$param['text']);
                    break;
                case '无入场记录放行显示':
                    $dataStream=$boardClass::noEntryRecordDisplay($barrier);
                    break;
                case '内场放行显示':
                    $dataStream=$boardClass::insidePassDisplay($barrier);
                    break;
                case '显示出场付款码':
                    $dataStream=$boardClass::showPayQRCode($param['qrcode'],$param['text']);
                    break;
                case '显示无牌车入场二维码':
                    $dataStream=$boardClass::showEntryQRCode($param['qrcode'],$param['text']);
                    break;
                case '显示无牌车出场二维码':
                    $dataStream=$boardClass::showExitQRCode($param['qrcode'],$param['text']);
                    break;
                case '无牌车语音':
                    $dataStream=$boardClass::noPlateVoice();
                    break;
                case '无牌车显示':
                    $dataStream=$boardClass::noPlateDisplay($barrier,$param['type']);
                    break;
                case '入场语音':
                    $dataStream=$boardClass::entryVoice($barrier,$param['plate'],$param['rulesType']);
                    break;
                case '请缴费语音':
                    $dataStream=$boardClass::payVoice($param['plate'],$param['recordsPay']);
                    break;
                case '免费离场语音':
                    $dataStream=$boardClass::freeLeaveVoice($barrier,$param['plate'],$param['rulesType']);
                    break;
                case '付款后离场语音':
                    $dataStream=$boardClass::paidVoice($barrier,$param['plate'],$param['records'],$param['recordsPay'],$param['rulesType']);
                    break;
                case '余额不足语音':
                    $dataStream=$boardClass::insufficientBalanceVoice();
                    break;
                case '支付成功语音':
                    $dataStream=$boardClass::paySuccessVoice();
                    break;
                case '支付成功显示':
                    $dataStream=$boardClass::paySuccessScreen($barrier,$param['plate_number']);
                    break;
                case '禁止通行语音':
                    $dataStream=$boardClass::noEntryVoice();
                    break;
                case '人工确认语音':
                    $dataStream=$boardClass::confirmVoice($param['plate_number']);
                    break;
                case '设置音量':
                    $dataStream=$boardClass::setVolume($param['step'],$param['voice']);
                    break;
                case '无入场记录放行语音':
                    $dataStream=$boardClass::noEntryRecordVoice();
                    break;
            }
        }catch (\Exception $e) {
            return;
        }
        $body=[
            'name'=>$name,
            'topic'=>self::getTopic($barrier,$name),
            'message'=>self::getMessage($barrier,$name,$param,$dataStream)
        ];
        $redis=self::connectRedis();
        $redis->rpush('mqtt_publish_queue',json_encode($body));
    }

    public static function makePhoto(ParkingBarrier $barrier)
    {
        Cache::set('barrier-photo-'.$barrier->serialno,'');
        Utils::send($barrier,'主动拍照');
        $i=0;
        $photo=false;
        while($i<50){
            $photo=Cache::get('barrier-photo-'.$barrier->serialno);
            if($photo){
                break;
            }
            usleep(100000);
            $i++;
        }
        if(!$photo){
            throw new \Exception('主动拍照失败');
        }
        return $photo;
    }

    public static function trigger(ParkingBarrier $barrier)
    {
        $photo='';
        self::send($barrier,'主动识别',[],function($result) use (&$photo){
            if($result){
                $photo=$result;
            }else{
                throw new \Exception('主动识别失败');
            }
        },3);
        return $photo;
    }

    public static function open(ParkingBarrier $barrier,string $recordsType,\Closure|null $callback = null)
    {
        if($recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            self::send($barrier,'开闸',[],$callback);
        }
    }

    public static function close(ParkingBarrier $barrier,string $recordsType,\Closure|null $callback = null)
    {
        if($recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            self::send($barrier,'关闸',[],$callback);
        }
    }

    public static function addBarrierLog(ParkingBarrier $barrier,array $data)
    {
        self::send($barrier,'通道记录',$data);
    }

    public static function openGateException(ParkingBarrier $barrier,string $message)
    {
        self::send($barrier,'开闸异常显示',['message'=>$message]);
        self::send($barrier,'禁止通行语音');
    }

    public static function payOpen(ParkingBarrier $barrier,string $plate_number)
    {
        self::send($barrier,'开闸',[],function($res) use ($barrier){
            //没开闸的情况下再开一次
            if(!$res){
                self::send($barrier,'开闸');
            }
        },2);
        self::send($barrier,'支付成功语音');
        self::send($barrier,'支付成功显示',['plate_number'=>$plate_number]);
    }

    public static function confirm(ParkingBarrier $barrier,string $plate_number)
    {
        self::send($barrier,'人工确认语音',['plate_number'=>$plate_number]);
        self::send($barrier,'人工确认显示',['plate_number'=>$plate_number]);
    }

    public static function inFieldOpen(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType,string $recordsType)
    {
        if($recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            self::send($barrier,'开闸');
            self::send($barrier,'内场放行显示');
            if($barrier->barrier_type=='entry'){
                self::send($barrier,'入场语音',['plate'=>$plate,'rulesType'=>$rulesType]);
            }
            if($barrier->barrier_type=='exit'){
                self::send($barrier,'免费离场语音',['plate'=>$plate,'rulesType'=>$rulesType]);
            }
        }
    }

    public static function havaNoEntryOpen(ParkingBarrier $barrier,string $recordsType,bool $open)
    {
        if($recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            if($open){
                self::send($barrier,'开闸');
            }
            self::send($barrier,'无入场记录放行显示');
            self::send($barrier,'无入场记录放行语音');
        }
    }

    public static function showLastSpace(ParkingBarrier $barrier,string $recordsType,int $last_space)
    {
        if($recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            $show_last_space=json_decode($barrier->show_last_space,true);
            $title=str_replace('{剩余车位}',(string)$last_space,$show_last_space['text']);
            self::send($barrier,'设置广告',[
                'line'=>$show_last_space['line'],
                'text'=>$title
            ]);
        }
    }

    public static function entryVoiceAndScreen(ParkingBarrier $barrier,ParkingPlate $plate,string $recordsType,string $rulesType)
    {
        if($recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            self::send($barrier,'入场语音',['plate'=>$plate,'rulesType'=>$rulesType]);
            self::send($barrier,'入场显示',['plate'=>$plate,'rulesType'=>$rulesType]);
        }
    }

    public static function exitScreenAndVoice(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,ParkingRecordsPay|null $recordsPay,string $recordsType,string $rulesType)
    {
        if($recordsType==ParkingRecords::RECORDSTYPE('自动识别') || $recordsType==ParkingRecords::RECORDSTYPE('人工确认')){
            if($recordsPay && $recordsPay->pay_id){
                self::send($barrier,'支付成功语音');
                self::send($barrier,'支付成功显示',['plate'=>$plate,'rulesType'=>$rulesType,'records'=>$records,'recordsPay'=>$recordsPay]);
            }else if($recordsPay && !$recordsPay->pay_id){
                self::send($barrier,'请缴费语音',['plate'=>$plate,'rulesType'=>$rulesType,'records'=>$records,'recordsPay'=>$recordsPay]);
                self::send($barrier,'请缴费显示',['plate'=>$plate,'rulesType'=>$rulesType,'records'=>$records,'recordsPay'=>$recordsPay]);
                self::send($barrier,'显示出场付款码');
            }else{
                self::send($barrier,'免费离场语音',['plate'=>$plate,'rulesType'=>$rulesType]);
                self::send($barrier,'免费离场显示',['plate'=>$plate,'rulesType'=>$rulesType,'records'=>$records]);
            }
        }
    }

    //无牌车入场
    public static function noPlateEntry(ParkingBarrier $barrier)
    {
        self::send($barrier,'无牌车显示',['type'=>'entry']);
        self::send($barrier,'无牌车语音');
        self::send($barrier,'显示无牌车入场二维码');
    }

    //无牌车出场
    public static function noPlateExit(ParkingBarrier $barrier)
    {
        self::send($barrier,'无牌车显示',['type'=>'exit']);
        self::send($barrier,'无牌车语音');
        self::send($barrier,'显示无牌车出场二维码');
    }

    public static function setWhitelist(ParkingBarrier $barrier,array $cars)
    {
        self::send($barrier,'离线白名单',['cars'=>$cars,'action'=>'update_or_add']);
    }

    public static function setSystemTime(ParkingBarrier $barrier,int $time)
    {
        self::send($barrier,'设置时间',[
            'time'=>[
                'year'=>date('Y',$time),
                'month'=>date('m',$time),
                'day'=>date('d',$time),
                'hour'=>date('H',$time),
                'min'=>date('i',$time),
                'sec'=>date('s',$time)
            ],
        ]);
    }

    public static function setVolume(ParkingBarrier $barrier,int $step,int|null $voice)
    {
        if($step==1){
            self::send($barrier,'设置音量',[
                'voice'=>$voice,
                'step'=>1
            ]);
        }
        if($step==2){
            self::send($barrier,'设置音量',[
                'step'=>2
            ]);
        }
    }

    //设置屏显广告
    public static function setScreentextAd(ParkingBarrier $barrier,string $text,int $line)
    {
        self::send($barrier,'设置广告',[
            'line'=>$line,
            'text'=>$text
        ]);
    }

    public static function insufficientBalance(ParkingBarrier $barrier)
    {
        self::send($barrier,'开闸异常显示',['message'=>'余额不足']);
        self::send($barrier,'余额不足语音');
    }

    public static function checkPlate(string $photo)
    {
        throw new \Exception('未定义车牌识别方法');
    }

    public static function getVersion(ParkingBarrier $barrier)
    {
        $r=false;
        self::send($barrier,'获取版本号',[],function($result) use (&$r){
            if($result){
                $r=$result;
            }
        },3);
        return $r;
    }

    public static function getTopic(ParkingBarrier $barrier,string $name)
    {
        $classname='\\app\\common\\service\\barrier\\'.$barrier->camera;
        return $classname::getTopic($barrier,$name);
    }

    public static function getMessage(ParkingBarrier $barrier,string $name,array $param=[],mixed $data='')
    {
        $classname='\\app\\common\\service\\barrier\\'.$barrier->camera;
        return $classname::getMessage($barrier,$name,$param,$data);
    }

    public static function getUniqidName(ParkingBarrier $barrier)
    {
        $classname='\\app\\common\\service\\barrier\\'.$barrier->camera;
        return $classname::getUniqidName($barrier);
    }

    private static function connectRedis()
    {
        if(self::$redis && self::$redis->isConnected()){
            return self::$redis;
        }
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        self::$redis=$redis;
        return self::$redis;
    }
}