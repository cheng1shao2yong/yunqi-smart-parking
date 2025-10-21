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
use app\common\service\BoardService;
use app\common\service\InsideService;
use app\common\service\msg\WechatMsg;
use app\common\service\ParkingService;
use app\common\library\AliyunOss;
use think\facade\Cache;

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
        '主动拍照'=>'snapshot',
        '主动识别'=>'ivs_trigger',
        '通道记录'=>'screen_record',
        '设置时间'=>'set_time',
        '离线白名单'=>'white_list_operator',
    ];
    
    public static function get_subject(string $serialno):array
    {
        $arr=[];
        foreach (self::SUBJECT as $key=>$num){
            $arr[$serialno.$key]=$num;
        }
        return $arr;
    }
    
    public static function get_keep_alive(string $serialno):array
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
        if(BoardService::isScreenAction($name) || BoardService::isVoiceAction($name)){
            return $barrier->serialno.'/device/message/down/serial_data';
        }
        $action=self::ACTION[$name];
        switch ($action){
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


    public static function getMessage(ParkingBarrier $barrier,string $name,array $param=[],mixed $data=''):array
    {
        $id=uniqid();
        $body=[];
        if(isset(self::ACTION[$name])){
            $action=self::ACTION[$name];
            switch ($name){
                case '设置时间':
                    $body=$param['time'];
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
                case '主动拍照':
                case '主动识别':
                    $body=[];
                    break;
                case '获取版本号':
                    $body=[];
                    break;
            }
        }
        if(BoardService::isVoiceAction($name) || BoardService::isScreenAction($name)){
            $action='serial_data';
            $body=[
                'serialData'=>[
                    [
                        'serialChannel'=>0,
                        'data'=>base64_encode($data),
                        'dataLen'=>strlen($data),
                    ]
                ]
            ];
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

    public static function invoke(ParkingBarrier $barrier,array $message)
    {
        $name=$message['name'];
        if(!method_exists(self::class,$name)){
            return false;
        }
        return self::$name($barrier,$message,$message['payload']);
    }

    private static function gpio_out(ParkingBarrier $barrier,$message,$payload)
    {
        return true;
    }

    //主动拍照
    private static function snapshot(ParkingBarrier $barrier,$message,$payload)
    {
        if(isset($payload['image_content']) && $payload['image_content']){
            $file=$barrier->parking_id.'/'.date('Ymd').'/'.md5(time().rand(1000,9999)).'.jpg';
            $oss=AliyunOss::instance();
            $imageFile=$oss->upload($file,base64_decode($payload['image_content']));
            Cache::set('barrier-photo-'.$barrier->serialno,$imageFile);
        }else if(isset($payload['imgPath']) && $payload['imgPath']){
            $imageFile=base64_decode($payload['imgPath']);
            if(strpos($imageFile,'?')!==false){
                $imageFile=substr($imageFile,0,strpos($imageFile,'?'));
            }
            Cache::set('barrier-photo-'.$barrier->serialno,$imageFile);
        }else{
            Cache::set('barrier-photo-'.$barrier->serialno,'');
        }
        return false;
    }

    private static function lanectrl_result(ParkingBarrier $barrier,$message,$payload)
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
            'parking_id'=>$barrier->parking_id,
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
        $parentBarrier=ParkingBarrier::find($barrier->pid);
        if($parentBarrier){
            $xtrigger=ParkingTrigger::where(['parking_id'=>$barrier->parking_id,'serialno'=>$parentBarrier->serialno])->order('id desc')->find();
            if($xtrigger && $xtrigger->message=='正常开启通道'){
                Utils::close($parentBarrier,ParkingRecords::RECORDSTYPE('自动识别'));
                $records=ParkingRecords::where(['plate_number'=>$plate_number,'parking_id'=>$barrier->parking_id])->order('id desc')->find();
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

    private static function ivs_result(ParkingBarrier $barrier,$message,$payload)
    {
        //辅机
        if($barrier->pid>0){
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
        ParkingScreen::sendBlackMessage($barrier,'识别到车牌号'.$plate_number);
        $plate_type = self::PLATE_TYPE[$payload['AlarmInfoPlate']['result']['PlateResult']['colorType']];
        $trigger=new ParkingTrigger();
        $triggerData=[
            'parking_id'=>$barrier->parking_id,
            'supplier'=>'成都臻视',
            'serialno'=>$serialno,
            'trigger_type'=>isset(self::TRIGGER_TYPE[$triggerType])?self::TRIGGER_TYPE[$triggerType]:$triggerType,
            'plate_number'=>$plate_number,
            'plate_type'=>$plate_type,
            'image'=>$imageUrl,
            'createtime'=>time(),
        ];
        //判断是否为主动拍照
        $isphoto=Cache::get('barrier-photo-'.$barrier->serialno);
        if($isphoto && $isphoto===true){
            $usetime=round(microtime(true)-$starttime,5);
            $triggerData['usetime']=$usetime;
            $triggerData['message']='主动拍照';
            $trigger->save($triggerData);
            $isplate=false;
            if($plate_number!='_无_'){
                $isplate=true;
            }
            $result=[
                'trigger_id'=>$trigger->id,
                'isplate'=>$isplate,
                'plate_number'=>$plate_number,
                'plate_type'=>$plate_type,
                'photo'=>$imageUrl,
            ];
            Cache::set('barrier-photo-'.$barrier->serialno,$result,5);
            return false;
        }
        if($barrier->tjtc){
            $tjtclist=ParkingBarrierTjtc::cache('barrier_tjtc_'.$barrier->parking_id)->where(['parking_id'=>$barrier->parking_id])->select();
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
                        ParkingScreen::sendRedMessage($barrier,$plate_number.'开闸失败，停留时间过短');
                        Utils::openGateException($barrier,'开闸失败，停留时间过短');
                        return false;
                    }
                }
            }
        }
        if($plate_number=='_无_' || $plate_number=='无牌车'){
            $usetime=round(microtime(true)-$starttime,5);
            $triggerData['usetime']=$usetime;
            $triggerData['message']='没有识别到车牌号';
            $trigger->save($triggerData);
            $txg='';
            if($barrier->barrier_type=='entry'){
                Utils::noPlateEntry($barrier);
                $txg='入场';
            }
            if($barrier->barrier_type=='exit'){
                Utils::noPlateExit($barrier);
                $txg='出场';
            }
            ParkingScreen::sendRedMessage($barrier,'识别到无牌车'.$txg);
            return false;
        }
        $barrier_type=$barrier->barrier_type;
        $trigger_type=$barrier->trigger_type;
        $service=false;
        try{
            if($trigger_type=='infield' || $trigger_type=='outfield'){
                $parking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
                $theadkey=md5($barrier->parking_id.'-'.$barrier->id.'-'.$plate_number);
                $service = ParkingService::newInstance([
                    'parking' => $parking,
                    'barrier'=>$barrier,
                    'plate_number' => $plate_number,
                    'plate_type' => $plate_type,
                    'photo' => $imageUrl,
                    'records_type'=>ParkingRecords::RECORDSTYPE('自动识别'),
                    'entry_time' =>($barrier->barrier_type=='entry')?time():null,
                    'exit_time' =>($barrier->barrier_type=='exit')?time():null
                ],$theadkey);
                if($barrier_type=='entry' && $barrier->manual_confirm && $service->isProvisional()){
                    $usetime=round(microtime(true)-$starttime,5);
                    $triggerData['usetime']=$usetime;
                    $triggerData['message']='人工确认';
                    $trigger->save($triggerData);
                    ParkingScreen::sendManualMessage($barrier,$trigger->id,$plate_number,$barrier->id,$imageUrl);
                    Utils::confirm($barrier,$plate_number);
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
                $insideParking=Parking::cache('parking_'.$barrier->parking_id,24*3600)->withJoin(['setting'])->find($barrier->parking_id);
                $outsideParking=Parking::cache('parking_'.$insideParking->pid,24*3600)->withJoin(['setting'])->find($insideParking->pid);
                $insideBarrier=$barrier;
                $outsideBarrier=ParkingBarrier::where(['serialno'=>$barrier->serialno,'parking_id'=>$outsideParking->id])->find();
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
                    $service->entry();
                }
                //场内场出场
                if($barrier_type=='exit'){
                    $service->exit();
                }
                $triggerData['message']='正常开启通道';
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
            ParkingScreen::sendRedMessage($barrier,$plate_number.'开闸失败，'.$message);
            if($trigger_type=='inside'){
                ParkingScreen::sendRedMessage($outsideBarrier,$plate_number.'开闸失败，'.$message);
            }
            if(strpos($message,'余额不足')!==false){
                Utils::insufficientBalance($barrier);
            }else{
                Utils::openGateException($barrier,$message);
            }
        }
        $usetime=round(microtime(true)-$starttime,5);
        $triggerData['usetime']=$usetime;
        $trigger->save($triggerData);
        return false;
    }
}