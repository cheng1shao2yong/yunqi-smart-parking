<?php
declare(strict_types=1);

namespace app\common\service\barrier;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingScreen;
use app\common\model\PayUnion;
use app\common\model\Qrcode as QrcodeModel;
use app\common\service\BarrierService;
use app\common\service\PayService;

defined('DS') or define('DS',DIRECTORY_SEPARATOR);

class Saifeimu extends BarrierService {

    const ACTION=[
        '显示+语音'=>'setDeviceCustomDisplay',
        '显示付款码'=>'setQrcodeCustomDisplay',
        '显示入场码'=>'setQrcodeCustomDisplay',
        '服务器应答对讲'=>'startTalking',
        '状态'=>'version'
    ];

    public static function get_subject(string $serialno){
        $arr=[];
        $arr['/camera/push/result']=0;
        $arr['/gate/push/result']=0;
        return $arr;
    }
    
    public static function get_keep_alive(string $serialno)
    {
        return [];
    }

    public function open(): bool
    {
        return true;
    }

    public function inFieldOpen(): bool
    {
        return true;
    }

    public function voice(string $action)
    {

    }

    public function screen(string $action)
    {

    }

    public static function isOnline(ParkingBarrier $barrier):bool
    {
        $r=false;
        Utils::send($barrier,'状态',[],function($result) use (&$r){
            if($result){
                $r=true;
            }
        },3);
        return $r;
    }

    public function invoke(array $message)
    {
        if($this->barrier->pid){
            $action=$message['actionName'];
            switch ($action){
                case 'uploadScanReadData':
                    return $this->uploadScanReadData($message['data']);
                case 'version':
                    return $this->version($message['data']);
                case 'startTalking':
                    return $this->startTalking($message);
            }
        }else{
            return $this->ivs_result($message);
        }
    }

    private function startTalking(array $message)
    {
        $fail=[
            'resultCode'=>0,
            'message'=>'服务器应答对讲失败'
        ];
        Utils::send($this->barrier,'服务器应答对讲',$fail);
        return false;
    }
    

    private function version(string $data)
    {
        return true;
    }

    private function uploadScanReadData(array $data)
    {
        $mediumNo=$data['qrcodeData'];
        Utils::send($this->barrier,'显示+语音',['message'=>'扫码成功','voice'=>'扫码成功']);
        try{
            $service=false;
            $barrier=ParkingBarrier::where(['id'=>$this->barrier->pid,'status'=>'normal'])->find();
            if(!$barrier){
                throw new \Exception('没有找到对应的道闸');
            }
            $pay=ParkingRecordsPay::with(['records'])->where([
                'barrier_id'=>$barrier->id,
                'parking_id'=>$barrier->parking_id,
            ])->order('id desc')->find();
            if(!$pay){
                throw new \Exception('没有找到支付订单');
            }
            if($pay->pay_id){
                throw new \Exception('订单已经支付');
            }
            if($pay->createtime<=time() - $barrier->limit_pay_time){
                throw new \Exception('订单已经超时');
            }
            $records=$pay->records;
            $parking=Parking::cache('parking_'.$records->parking_id,24*3600)->withJoin(['setting'])->find($records->parking_id);
            $service=PayService::newInstance([
                'pay_type_handle'=>$parking->pay_type_handle,
                'parking_id'=>$parking->id,
                'sub_merch_no'=>$parking->sub_merch_no,
                'split_merch_no'=>$parking->split_merch_no,
                'persent'=>$parking->parking_records_persent,
                'pay_price'=>$pay->pay_price,
                'mediumNo'=>$mediumNo,
                'terminalId'=>$barrier->serialno,
                'order_type'=>PayUnion::ORDER_TYPE('停车缴费'),
                'order_body'=>$records->plate_number.'停车缴费',
                'attach'=>json_encode([
                    'records_pay_id'=>$pay->id,
                    'records_id'=>$records->id,
                    'plate_number'=>$records->plate_number,
                    'parking_title'=>$parking->title
                ],JSON_UNESCAPED_UNICODE)
            ]);
            $service->qrcodePay();
            $service->destroy();
        }catch (\Exception $e){
            if($service){
                $service->destroy();
            }
            Utils::send($this->barrier,'显示+语音',['message'=>$e->getMessage(),'voice'=>'支付失败']);
            ParkingScreen::sendRedMessage($barrier,'支付失败');
        }
    }

    private function ivs_result(array $message)
    {
        return false;
    }

    public function havaNoEntryOpen(string $message,bool $open)
    {

    }

    public function showLastSpace(int $last_space)
    {

    }

    public function showPayQRCode()
    {
        $host=site_config("mqtt.mqtt_host");
        $str='https://'.$host.'/qrcode/exit?serialno='.$this->barrier->serialno;
        Utils::send($this->barrier,'显示付款码',['qrcode'=>$str]);
        return true;
    }

    public function showEntryQRCode()
    {
        $config=[
            'appid'=>site_config("addons.uniapp_mpapp_id"),
            'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
        ];
        $qrcode= QrcodeModel::createQrcode('parking-entry-qrcode',$this->barrier->serialno,24*3600*365*80);
        $wechat=new \WeChat\Qrcode($config);
        $ticket = $wechat->create($qrcode->id)['ticket'];
        $url=$wechat->url($ticket);
        Utils::send($this->barrier,'显示入场码',['qrcode'=>$url]);
    }

    public static function getUniqidName(ParkingBarrier $barrier)
    {
        return 'msgId';
    }

    public static function getTopic(ParkingBarrier $barrier,string $name)
    {
        if($barrier->pid){
            return '/gate/'.$barrier->serialno.'/command';
        }else{
            return '/camera/'.$barrier->serialno.'/command';
        }
    }

    public static function getMessage(ParkingBarrier $barrier,string $name, array $param = [])
    {
        $action=self::ACTION[$name];
        $result=[
            'msgId'=>uniqid(),
            'deviceNo'=>$barrier->serialno,
            'actionName'=>$action,
            'ack'=>1
        ];
        switch ($name){
             case '显示付款码':
                 $result['data']=[
                    'voiceText'=>'',
                    'paymentQrcode'=>$param['qrcode'],
                    'qrcodeType'=>0,
                    'topText'=>'支付请扫码',
                    'displayPageTimeout'=>$barrier->limit_pay_time,
                ];
                break;
            case '显示入场码':
                $result['data']=[
                    'voiceText'=>'',
                    'paymentQrcode'=>$param['qrcode'],
                    'qrcodeType'=>1,
                    'topText'=>'入场请扫码',
                    'displayPageTimeout'=>$barrier->limit_pay_time,
                ];
                break;
            case '显示+语音':
                $result['data']=[
                    'voiceText'=>$param['voice'],
                    'messageText'=>$param['message'],
                    'displayPageTimeout'=>30
                ];
                break;
            case '服务器应答对讲':
                $result['data']=[
                    'resultCode'=>$param['resultCode'],
                    'message'=>$param['message'],
                ];
                break;
            case '状态':
                $result['ack']=0;
                break;
        }
        return $result;
    }
}