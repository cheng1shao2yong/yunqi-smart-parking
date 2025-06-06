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
use Simps\MQTT\Protocol\Types;
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
            Coroutine\go(function() {
                $this->receive();
            });
            Coroutine\go(function() {
                $this->clearMessage();
            });
        }
        
        if($action=='publish'){
            $this->output('启动Mqtt publish服务');
            Coroutine\go(function() {
                $this->publish();
            });
        }
        
        if($action=='keepalive'){
            $this->output('启动Mqtt keepalive服务');
            Coroutine\go(function() {
                $this->keepalive();
            });
        }
    }

    private function publish()
    {
        $client=$this->getClient('mqtt-publish-server-'.rand(1000,9000));
        $client->connect();
        $this->output('publish连接成功');
        while(true){
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
                        if(count($this->parking_log)>=50){
                            (new ParkingLog())->saveAll($this->parking_log);
                            $this->parking_log=[];
                        }
                    }
                    $this->output('发布消息,'.$body['topic']);
                    $client->publish($body['topic'], json_encode($body['message']),1);
                }
            }catch (\Exception $e){
                $this->output($e->getMessage());
            }
            Coroutine::sleep(0.1);
        }
    }

    //清除超过1分钟的消息
    private function clearMessage()
    {
        while (true){
            foreach ($this->reply as $id=>$time){
                if(time()-$time>60){
                    $this->redis->del($id);
                    unset($this->reply[$id]);
                }
            }
            Coroutine::sleep(5);
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
        $client=$this->getClient('mqtt-receive-server-'.rand(1000,9000),2);
        $client->connect();
        $topcarr=$this->getSubscribe();
        $client->subscribe($topcarr);
        $this->output('receive连接成功');
        $timeSincePing=time();
        while(true){
            if ($timeSincePing <= (time() - $client->getConfig()->getKeepAlive())) {
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
                if ($buffer && $buffer !== true){
                    if ($buffer['type'] === Types::DISCONNECT) {
                        $this->output('mqtt客户端断开连接');
                        $client->close();
                        $this->receive();
                        break;
                    }
                    if (isset($buffer['topic']) && isset($buffer['message'])) {
                        $topic=$buffer['topic'];
                        $message=json_decode($buffer['message'],true);
                        Coroutine\go(function() use ($topic,$message){
                            $this->output('收到消息,'.$topic);
                            /* @var ParkingBarrier $barrier */
                            $barrier=BarrierService::getBarriers($topic,$message);
                            if($barrier){
                                /* @var BarrierService $barrierService*/
                                $barrierService=$barrier->getBarrierService();
                                $callback=$barrierService->invoke($message);
                                $barrierService->destroy();
                                if($callback){
                                    $uniqid=Utils::getUniqidName($barrier);
                                    $this->redis->set($message[$uniqid],json_encode($message));
                                    $this->reply[$message[$uniqid]]=time();
                                }
                            }
                        });
                    }
                }
            }catch (\Exception $e){
                $this->output->info(date('Y-m-d H:i:s'));
                $this->output->error($e->getMessage());
                $this->output->error($e->getTraceAsString());
            }
        }
    }
    
    private function keepalive()
    {
        $client=$this->getClient('mqtt-keepalive-server-'.rand(1000,9000));
        $client->connect();
        $topcarr=$this->getKeepAlive();
        $client->subscribe($topcarr);
        $this->output('keepalive连接成功');
        $timeSincePing=time();
        while(true){
            if ($timeSincePing <= (time() - $client->getConfig()->getKeepAlive())) {
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
                if ($buffer && $buffer !== true){
                    if ($buffer['type'] === Types::DISCONNECT) {
                        $this->output('mqtt客户端断开连接');
                        $client->close();
                        $this->keepalive();
                        break;
                    }
                    if (isset($buffer['topic'])) {
                        $topic=$buffer['topic'];
                        $this->output('收到消息,'.$topic);
                        $serialno=BarrierService::getSn($topic);
                        Cache::set('barrier-online-'.$serialno,time());
                    }
                }
            }catch (\Exception $e){
                $this->output($e->getMessage());
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
        $config->setKeepAlive(60);
        $config->setDelay(3000);
        $config->setMaxAttempts(5);
        $config->setSwooleConfig([
            'open_mqtt_protocol' => true,
            'package_max_length' => 2 * 1024 * 1024,
            'connect_timeout' => 5,
            'write_timeout' => 5,
            'read_timeout' => 5,
        ]);
        $client=new Client($host,$port,$config);
        return $client;
    }

    private function output($msg)
    {
        $this->output->info(date('Y-m-d H:i:s').'-'.$msg);
    }
}