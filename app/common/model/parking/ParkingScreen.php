<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\library\ParkingAccount;
use app\common\model\manage\Parking;
use app\common\model\PayUnion;
use app\common\service\barrier\Utils;
use app\common\library\Http;
use app\common\model\base\ConstTraits;
use app\common\service\msg\WechatMsg;
use app\common\service\ParkingService;
use think\facade\Cache;
use think\Model;

class ParkingScreen extends Model
{
    use ConstTraits;

    private static $log=[];

    public function barrier()
    {
        return $this->hasOne(ParkingBarrier::class,'id','barrier_id');
    }

    public function fuji()
    {
        return $this->hasMany(ParkingBarrier::class,'pid','barrier_id');
    }

    public static function open(Parking $parking,ParkingBarrier $barrier,mixed $records_id,string $openuser,mixed $remark='')
    {
        //内场不能控制外场出场通道
        if($barrier->trigger_type=='inside' && $barrier->barrier_type=='entry'){
            throw new \Exception('内场不能控制外场出场通道');
        }
        //外场不能控制内场出场通道
        if($barrier->trigger_type=='outside' && $barrier->barrier_type=='entry'){
            throw new \Exception('外场不能控制内场出场通道');
        }
        Utils::open($barrier,ParkingRecords::RECORDSTYPE('人工确认'),function($result) use ($parking,$barrier,$records_id,$openuser,$remark){
            if($result){
                $records=false;
                if($records_id){
                    $limit_pay_time = time() - $barrier->limit_pay_time;
                    $recordspay=ParkingRecordsPay::where(['parking_id'=>$barrier->parking_id,'records_id'=>$records_id,'barrier_id'=>$barrier->id])->order('id desc')->find();
                    if($recordspay && !$recordspay->pay_id && $recordspay->createtime>$limit_pay_time){
                        $records=ParkingRecords::find($records_id);
                    }
                }else{
                    //检查有没有正在闸口等待的车辆，如果有，则修改出场状态为手动开闸出场
                    $limit_pay_time = time() - $barrier->limit_pay_time;
                    $recordspay=ParkingRecordsPay::where(['parking_id'=>$barrier->parking_id,'barrier_id'=>$barrier->id])->order('id desc')->find();
                    if($recordspay && !$recordspay->pay_id && $recordspay->createtime>$limit_pay_time){
                        $records=ParkingRecords::find($recordspay->records_id);
                    }
                }
                if($records){
                    //处理优惠券
                    $service=ParkingService::newInstance([
                        'parking'=>$parking,
                        'plate_number'=>$records->plate_number,
                        'plate_type'=>$records->plate_type
                    ]);
                    $total_fee=$service->getTotalFee($records,time());
                    [$activities_fee,$activities_time,$coupon_type,$couponlist,$coupont_title]=$service->getActivitiesFee($records,$total_fee);
                    ParkingMerchantCouponList::settleCoupon($records,$coupon_type,$couponlist);
                    $records->status=ParkingRecords::STATUS('手动开闸出场');
                    if(strpos($remark,'现金')!==false){
                        $payunion=PayUnion::underline(
                            $recordspay->pay_price,
                            PayUnion::ORDER_TYPE('停车缴费'),
                            ['parking_id'=>$parking->id],
                            $records->plate_number.'停车缴费'
                        );
                        $recordspay->pay_id=$payunion->id;
                        $recordspay->save();
                        $records->pay_fee=$records->pay_fee+$recordspay->pay_price;
                        $records->status=ParkingRecords::STATUS('现金缴费出场');
                    }
                    if($recordspay->activities_fee){
                        $records->activities_fee=$recordspay->activities_fee;
                    }
                    if($recordspay->activities_time){
                        $records->activities_time=$recordspay->activities_time;
                    }
                    $records->exit_type=ParkingRecords::RECORDSTYPE('手动操作');
                    $records->remark=$remark;
                    $records->save();
                    //更新车位总数
                    ParkingRecords::parkingSpaceEntry($parking,'exit');
                    //推动到交管平台
                    if($parking->setting->push_traffic && $records->rules_type==ParkingRules::RULESTYPE('临时车')){
                        (new ParkingTrafficRecords())->save([
                            'parking_id'=>$records->parking_id,
                            'records_id'=>$records->id,
                            'traffic_type'=>'exit',
                            'status'=>0
                        ]);
                        Cache::set('traffic_event',1);
                    }
                    //发送微信消息
                    WechatMsg::exit($parking,$records->plate_number);
                }
                //开闸记录
                ParkingManualOpen::create([
                    'barrier_id'=>$barrier->id,
                    'parking_id'=>$parking->id,
                    'records_id'=>$records_id,
                    'openuser'=>$openuser,
                    'message'=>$remark,
                    'createtime'=>time(),
                ]);
            }else{
                throw new \Exception('开闸失败');
            }
        });
    }

    public static function sendBlackMessage(ParkingBarrier $barrier,string $message)
    {
        self::send($barrier,['type'=>'message','color'=>'black','message'=>$message]);
    }

    public static function sendGreenMessage(ParkingBarrier $barrier,string $message)
    {
        self::send($barrier,['type'=>'message','color'=>'green','message'=>$message]);
    }

    public static function sendRedMessage(ParkingBarrier $barrier,string $message)
    {
        self::send($barrier,['type'=>'message','color'=>'red','message'=>$message]);
    }

    public static function sendPayMessage(ParkingBarrier $barrier,ParkingRecords $records,string $rules_type,float $pay_price)
    {
        self::send($barrier,[
            'type'=>'pay',
            'barrier_id'=>$barrier->id,
            'records_id'=>$records->id,
            'plate_number'=>$records->plate_number,
            'photo'=>$records->exit_photo,
            'color'=>'red',
            'message'=>$records->plate_number.' - '.$rules_type.'，入场时间'.$records->entry_time_txt.'，待支付'.formatNumber($pay_price).'元'
        ]);
    }

    public static function sendManualMessage(ParkingBarrier $barrier,int $trigger_id,string $plate_number,int $barrier_id,string $photo)
    {
        self::send($barrier,[
            'type'=>'manual',
            'trigger_id'=>$trigger_id,
            'barrier_id'=>$barrier_id,
            'plate_number'=>$plate_number,
            'photo'=>$photo,
            'color'=>'red',
            'message'=>'临时车【'.$plate_number.'】，人工确认开闸？'
        ]);
    }

    public static function sendRecoveryMessage(ParkingBarrier $barrier,int $recovery_id,string $plate_number,int $barrier_id,string $photo)
    {
        self::send($barrier,[
            'type'=>'recovery',
            'recovery_id'=>$recovery_id,
            'barrier_id'=>$barrier_id,
            'plate_number'=>$plate_number,
            'photo'=>$photo,
            'color'=>'red',
            'message'=>'车辆【'.$plate_number.'】存在欠费，人工确认开闸？'
        ]);
    }

    private static function send(ParkingBarrier $barrier,array $data)
    {
        $time=date('Y/m/d H:i');
        $title='【'.$barrier->title.'】';
        $data['message']=$time.$title.$data['message'];
        Utils::addBarrierLog($barrier,$data);
    }

    public static function getBarrierControl(ParkingBarrier $barrier)
    {
        if($barrier->camera==ParkingBarrier::CAMERA('成都臻识科技')){
            $data=[
                'sn'=>$barrier->serialno,
            ];
            $path='/openapi/v1/stp/user/devices/pdns/web';
            $url=self::getUrl('GET',$path,$data);
            $response=Http::get($url);
            if($response->isSuccess()){
                return $response->content['url'];
            }else{
                throw new \Exception($response->errorMsg);
            }
        }
    }

    public static function checkPlate(string $photourl)
    {
        $photo=base64_encode(file_get_contents($photourl));
        $path='/openapi/v1/prs_cn/plates/detect';
        $data=[
            'image_type'=>'BASE64',
            'image'=>$photo
        ];
        $url=self::getUrl('POST',$path,$data);
        $data=json_encode($data);
        $response=Http::post($url,$data,'',["Content-Type:application/json","Content-Length:".strlen($data)]);
        if($response->isSuccess()){
            $result_list=$response->content['result_list'];
            if(count($result_list)>0){
                $color=[
                    '未知' => 'unknown',
                    '蓝色' => 'blue',
                    '黄色' => 'yellow',
                    '白色' => 'white',
                    '黑色' => 'black',
                    '绿色' => 'green',
                    '黄绿色' => 'yellow-green'
                ];
                $plate_color=isset($color[$result_list[0]['plate_color']])?$color[$result_list[0]['plate_color']]:'unknown';
                $plate_number=$result_list[0]['license'];
                return [true,$plate_number,$plate_color];
            }
            return [false,'',''];
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    public static function getTolkingUrl(ParkingBarrier $barrier)
    {
        if($barrier->camera==ParkingBarrier::CAMERA('成都臻识科技')){
            $data=[
                'sn'=>$barrier->serialno,
            ];
            $path='/openapi/v1/stp/user/devices/intercom';
            $url=self::getUrl('GET',$path,$data);
            $response=Http::get($url);
            if($response->isSuccess()){
                return $response->content['url'];
            }else{
                throw new \Exception($response->errorMsg);
            }
        }
    }

    //获取监控地址
    public static function getVideoUrl(ParkingBarrier $barrier,string $video_type)
    {
        if($barrier->camera==ParkingBarrier::CAMERA('成都臻识科技')){
            if($video_type=='wide'){
                $data=[
                    'sn'=>$barrier->serialno,
                    'type'=>'auto'
                ];
                $path='/openapi/v1/stp/user/devices/vurl';
                $url=self::getUrl('GET',$path,$data);
                $response=Http::get($url);
                if($response->isSuccess()){
                    return $response->content['url'];
                }else{
                    return '';
                }
            }
            if($video_type=='local'){
                $info=self::getVideoInfo($barrier);
                $url="ws://".$info['local_ip'].":9080/ws.flv?channel=0";
                return $url;
            }
        }
    }

    //获取设备信息
    public static function getVideoInfo(ParkingBarrier $barrier)
    {
        if($barrier->camera==ParkingBarrier::CAMERA('成都臻识科技')){
            $path='/openapi/v1/stp/user/devices/'.$barrier->serialno;
            $url=self::getUrl('GET',$path,[]);
            $response=Http::get($url);
            if($response->isSuccess()){
                return $response->content;
            }else{
                return '';
            }
        }
    }

    public static function getZhenshiMqttLink()
    {
        $path='/openapi/v1/stp/clients/route';
        $url=self::getUrl('GET',$path,[]);
        $response=Http::get($url);
        if($response->isSuccess()){
            return $response->content;
        }else{
            return '';
        }
    }

    //绑定设备
    public static function bindBarrier(ParkingBarrier $barrier)
    {
        if($barrier->camera==ParkingBarrier::CAMERA('成都臻识科技')){
            $data=array(
                [
                    'sn'=>$barrier->serialno,
                    'group_id'=>70164,
                ]
            );
            $path='/openapi/v1/stp/user/devices';
            $url=self::getUrl('POST',$path,$data);
            $data=json_encode($data);
            $response=Http::post($url,$data,'',["Content-Type:application/json","Content-Length:".strlen($data)]);
            if($response->isSuccess()){
                if(!empty($response->content['failures'])){
                    $msg=$response->content['failures'][0]['error']['message'];
                    throw new \Exception($msg);
                }
                return true;
            }else{
                throw new \Exception($response->errorMsg);
            }
        }
    }

    private static function getUrl(string $method,string $path,array $data)
    {
        $access_key_id=site_config("camera.zhenshi_access_key");
        $access_key_secret=site_config("camera.zhenshi_access_secret");
        $host='https://open.vzicloud.com';
        $expires=time()+60*10;
        if($method=='GET'){
            $urlPamarsPath = "";
            foreach ($data as $key => $value) {
                $urlPamarsPath=$urlPamarsPath.$key."=".$value."&";
            }
            $targetPath=$path."?".$urlPamarsPath;
            $targetPath=substr($targetPath, 0, -1);
            $canonical=sprintf("%s\n%s\n%s\n%s\n%s",'GET','','', $expires, $targetPath);
            $signature=urlencode(base64_encode(hash_hmac('sha1', $canonical, $access_key_secret, TRUE)));
            $thid='&';
            if(strpos($targetPath,'?')===false){
                $thid='?';
            }
            $url=$host.$targetPath.$thid.'accesskey_id='.$access_key_id.'&signature='.$signature.'&expires='.$expires;
        }
        if($method=='POST'){
            $contentMd5=base64_encode(md5(json_encode($data),true));
            $canonical=sprintf("%s\n%s\n%s\n%s\n%s",'POST',$contentMd5,'application/json', $expires, $path);
            $signature=urlencode(base64_encode(hash_hmac('sha1', $canonical, $access_key_secret, TRUE)));
            $url=$host.$path.'?accesskey_id='.$access_key_id.'&signature='.$signature.'&expires='.$expires;
        }
        return $url;
    }
}
