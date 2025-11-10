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

namespace app\admin\command;

use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingLog;
use app\common\service\barrier\Utils;
use app\common\service\BarrierService;
use Simps\MQTT\Config\ClientConfig;
use Simps\MQTT\Client;
use think\facade\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Swoole\Coroutine;

class Mqtt extends Command
{
    protected $output;

    /* @var \Redis $redis */
    private $redis;

    private array $reply=[];

    //停车场日志
    private array  $parking_log=[];

    protected function configure()
    {
        $this->setName('Mqtt')->addArgument('action')->setDescription('Mqtt服务');
    }
    protected function execute(Input $input, Output $output)
    {
        $action=$input->getArgument('action');
        $this->output = $output;
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1');
        if($action=='receive'){
            $this->output('启动Mqtt receive服务');
            Coroutine::create(function() {
                $this->receive();
            });
            Coroutine::create(function() {
                $this->clearMessage();
            });
        }
        
        if($action=='publish'){
            $this->output('启动Mqtt publish服务');
            Coroutine::create(function() {
                $this->publish();
            });
        }
        
        if($action=='keepalive'){
            $this->output('启动Mqtt keepalive服务');
            Coroutine::create(function() {
                $this->keepalive();
            });
        }
        \Swoole\Event::wait();
    }

    private function publish()
    {
        try{
            $client=$this->getClient('mqtt-publish-server-'.rand(1000,9000));
            $client->connect();
        }catch (\Exception $e)
        {
            $this->output('mqtt连接失败，2秒后重试');
            Coroutine\System::sleep(2);
            $this->publish();
        }
        $this->output('publish连接成功');
        $timeSincePing=time();
        while(true){
            if ($timeSincePing <= (time() - 5*60)) {
                $buffer = $client->ping();
                if ($buffer) {
                    $timeSincePing = time();
                }
            }
            try{
                while($this->redis->lLen('mqtt_publish_queue')>0){
                    $queuebody=$this->redis->lPop('mqtt_publish_queue');
                    $body=json_decode($queuebody,true);
                    if($body['name']=='通道记录'){
                        $parking_id=substr($body['topic'],strrpos($body['topic'],'/')+1);
                        $data=$body['message']['payload']['body'];
                        $manual=0;
                        if(str_ends_with($data['message'],'手动开闸') || str_ends_with($data['message'],'手动关闸')){
                            $manual=1;
                        }
                        $this->parking_log[]=[
                            'createtime'=>time(),
                            'parking_id'=>$parking_id,
                            'type'=>$data['type'],
                            'color'=>$data['color'],
                            'manual'=>$manual,
                            'message'=>$data['message'],
                        ];
                        if(count($this->parking_log)>=120){
                            (new ParkingLog())->saveAll($this->parking_log);
                            $this->parking_log=[];
                        }
                    }
                    $this->output('发布消息,'.$body['topic']);
                    $client->publish($body['topic'], json_encode($body['message']),1);
                }
            }catch (\Exception $e){
                $message=$e->getMessage();
                $this->output($message);
                if($message=='Client no connection'){
                    $this->output('mqtt客户端断开连接');
                    $client->close();
                    $this->publish();
                    break;
                }
            }
            Coroutine\System::sleep(1);
        }
    }

    //清除超过1一个小时的消息
    private function clearMessage()
    {
        while (true){
            foreach ($this->reply as $id=>$time){
                if(time()-$time>60){
                    $this->redis->del($id);
                    unset($this->reply[$id]);
                }
            }
            Coroutine\System::sleep(1);
        }
    }

    private function getSubscribe($mqtt_barrier_add=null)
    {
        $arr=[];
        if($mqtt_barrier_add){
            $barriers=ParkingBarrier::whereIn('serialno',$mqtt_barrier_add)->where('serialno','<>',null)->field('id,serialno,camera')->select();
        }else{
            $barriers=ParkingBarrier::group('serialno')->where('serialno','<>',null)->field('id,serialno,camera')->select();
        }
        foreach ($barriers as $barrier){
            $classname='\\app\\common\\service\\barrier\\'.$barrier->camera;
            $arr=array_merge($arr,$classname::get_subject($barrier->serialno));
        }
        return $arr;
    }
    
    private function getKeepAlive($mqtt_barrier_alive=null)
    {
        $arr=[];
        if($mqtt_barrier_alive){
            $barriers=ParkingBarrier::whereIn('serialno',$mqtt_barrier_alive)->where('serialno','<>',null)->field('id,serialno,camera')->select();
        }else{
            $barriers=ParkingBarrier::group('serialno')->where('serialno','<>',null)->field('id,serialno,camera')->select();
        }
        foreach ($barriers as $barrier){
            $classname='\\app\\common\\service\\barrier\\'.$barrier->camera;
            $arr=array_merge($arr,$classname::get_keep_alive($barrier->serialno));
        }
        return $arr;
    }

    private function receive()
    {
        try{
            $client=$this->getClient('mqtt-receive-server-'.rand(1000,9000));
            $client->connect();
        }catch (\Exception $e)
        {
            $this->output('mqtt连接失败，2秒后重试');
            Coroutine\System::sleep(2);
            $this->receive();
        }
        $topcarr=$this->getSubscribe();
        if(!empty($topcarr)){
            $client->subscribe($topcarr);
        }
        $this->output('receive连接成功');
        $timeSincePing=time();
        while(true){
            if ($timeSincePing <= (time() - 5*60)) {
                $buffer = $client->ping();
                if ($buffer) {
                    $timeSincePing = time();
                }
            }
            //新添加的设备
            $mqtt_barrier_add=Cache::get('mqtt_barrier_add');
            if($mqtt_barrier_add){
                $topcarr=$this->getSubscribe($mqtt_barrier_add);
                if(!empty($topcarr)){
                    $client->subscribe($topcarr);
                }
                Cache::set('mqtt_barrier_add',null);
                $this->output('新增设备：'.implode(',',$mqtt_barrier_add));
            }
            try {
                $buffer = $client->recv();
                if (
                    $buffer &&
                    $buffer !== true &&
                    isset($buffer['topic']) &&
                    isset($buffer['message'])
                ){
                    $topic=$buffer['topic'];
                    $message=json_decode($buffer['message'],true);
                    $this->output('收到消息,'.$topic);
                    Coroutine::create(function() use ($topic,$message){
                        try {
                            /* @var ParkingBarrier $barrier */
                            $barrier=BarrierService::getBarriers($topic,$message);
                            if($barrier){
                                /* @var BarrierService $barrierService*/
                                $barrierService=$barrier->getBarrierService();
                                $callback=$barrierService::invoke($barrier,$message);
                                if($callback){
                                    $uniqid=Utils::getUniqidName($barrier);
                                    $this->redis->set($message[$uniqid],json_encode($message));
                                    $this->reply[$message[$uniqid]]=time();
                                }
                            }
                        }catch (\Exception $e){
                            $this->output->info(date('Y-m-d H:i:s'));
                            $this->output->error($e->getMessage());
                            $this->output->error($e->getTraceAsString());
                        }
                    });
                }
            }catch (\Exception $e) {
                $message=$e->getMessage();
                $this->output($message);
                if($message=='Client no connection'){
                    $this->output('mqtt客户端断开连接');
                    $client->close();
                    $this->receive();
                    break;
                }
            }
        }
    }
    
    private function keepalive()
    {
        try{
            $client=$this->getClient('mqtt-keepalive-server-'.rand(1000,9000));
            $client->connect();
        }catch (\Exception $e)
        {
            $this->output('mqtt连接失败，2秒后重试');
            Coroutine\System::sleep(2);
            $this->keepalive();
        }
        $topcarr=$this->getKeepAlive();
        if(!empty($topcarr)){
            $client->subscribe($topcarr);
        }
        $this->output('keepalive连接成功');
        $timeSincePing=time();
        while(true){
            if ($timeSincePing <= (time() - 5*60)) {
                $buffer = $client->ping();
                if ($buffer) {
                    $timeSincePing = time();
                }
            }
            //新添加的设备
            $mqtt_barrier_alive=Cache::get('mqtt_barrier_alive');
            if($mqtt_barrier_alive){
                $topcarr=$this->getKeepAlive($mqtt_barrier_alive);
                if(!empty($topcarr)){
                    $client->subscribe($topcarr);
                }
                Cache::set('mqtt_barrier_alive',null);
                $this->output('新增设备：'.implode(',',$mqtt_barrier_alive));
            }
            try {
                $buffer = $client->recv();
                if ($buffer && $buffer !== true && isset($buffer['topic'])){
                    $topic=$buffer['topic'];
                    $this->output('收到消息,'.$topic);
                    $serialno=BarrierService::getSn($topic);
                    Cache::set('barrier-online-'.$serialno,time());
                }
            }catch (\Exception $e){
                $message=$e->getMessage();
                $this->output($message);
                if($message=='Client no connection'){
                    $this->output('mqtt客户端断开连接');
                    $client->close();
                    $this->keepalive();
                    break;
                }
            }
        }
    }

    private function getClient(string $name)
    {
        $host=site_config("mqtt.mqtt_host");
        $port=(int)site_config("mqtt.mqtt_port");
        $username=site_config("mqtt.mqtt_username");
        $password=site_config("mqtt.mqtt_password");
        $config=new ClientConfig();
        $config->setClientId($name);
        $config->setUserName($username);
        $config->setPassword($password);
        $config->setKeepAlive(24*3600*365);
        $client=new Client($host,$port,$config);
        return $client;
    }

    private function output($msg)
    {
        $this->output->info(date('Y-m-d H:i:s').'-'.$msg);
    }
}