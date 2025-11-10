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

use app\common\library\Http;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingContactless;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingTraffic;
use app\common\model\PayUnion;
use app\common\service\contactless\Hzbrain;
use app\common\service\ContactlessService;
use Simps\MQTT\Client;
use Simps\MQTT\Config\ClientConfig;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Swoole\Coroutine;
use think\facade\Env;
use think\facade\Log;

class Hzcbparking extends Command
{
    const URL="http://220.191.209.248:8990";

    private $accessid;
    private $privatekey;
    private $publickey;
    private $secret;

    private $inrecord=[];

    protected function configure()
    {
        $this->setName('Hzcbparking')->setDescription('杭州智慧大脑下行数据接收程序');
        $this->accessid=Env::get('TRAFFIC_ACCESSID');
        $this->privatekey=Env::get('TRAFFIC_PRIVATE_KEY');
        $this->publickey=Env::get('TRAFFIC_PUBLIC_KEY');
        $this->secret=Env::get('TRAFFIC_SECRET');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output('启动Hzcbparking服务');
        $this->output = $output;
        Coroutine::create(function() {
            $this->receive();
        });
        Coroutine::create(function() {
            $this->clearInrecord();
        });
        \Swoole\Event::wait();
    }

    private function receive()
    {
        try{
            $clientId=$this->accessid;
            $client=$this->getClient($clientId);
            $client->connect();
        }catch (\Exception $e) {
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
                    if(Hzbrain::verifySign($message['cipher'],$message['sign'],$this->publickey)){
                        try{
                            $result=Hzbrain::decrypt($message['cipher'],$this->publickey);
                            if(str_starts_with($topic,'/hzcity/v2/departurePayInfo')){
                                $this->departurePayInfo((string)$message['requestId'],$result);
                            }
                            if(str_starts_with($topic,'/hzcity/v2/fee')){
                                $this->fee((string)$message['requestId'],$result);
                            }
                            if(str_starts_with($topic,'/hzcity/v2/payResult')){
                                $this->payResult((string)$message['requestId'],$result);
                            }
                        }catch (\Exception $e){
                            $this->output($e->getMessage());
                        }
                    }else{
                        $this->output('验签失败');
                    }
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

    private function departurePayInfo(string $requestId,array $data)
    {
        $plate_number=$data['plateNo'];
        if(key_exists($plate_number,$this->inrecord)){
            return;
        }
        if($plate_number!='浙ACT1772'){
            return;
        }
        $this->inrecord[$plate_number]=time();
        $parking_code=$data['parkingCode'];
        $no_sense=intval($data['noSense']);
        $departure_pay=intval($data['departurePay']);
        $money_limit=intval($data['moneyLimit']);
        $traffic=ParkingTraffic::where(['filings_code'=>$parking_code])->find();
        if(!$traffic){
            return;
        }
        $records=ParkingRecords::where(['plate_number'=>$plate_number,'parking_id'=>$traffic->parking_id])->order('id desc')->find();
        if(!$records){
            return;
        }
        if($records->status!=ParkingRecords::STATUS('正在场内')){
            return;
        }
        if($money_limit>0 && $no_sense==1 && $departure_pay==1){
            $parking=Parking::cache('parking_'.$traffic->parking_id,24*3600)->withJoin(['setting'])->find($traffic->parking_id);
            (new ParkingContactless())->save([
                'parking_id'=>$parking->id,
                'property_id'=>$parking->property_id,
                'parking_code'=>$traffic->filings_code,
                'handle'=>'\\app\\common\\service\\contactless\\Hzbrain',
                'money_limit'=>$money_limit,
                'records_id'=>$records->id,
                'createtime'=>time()
            ]);
            $post=[
                'requestId'=>$requestId,
                'success'=>true,
                'params'=>$data
            ];
            $package=Hzbrain::pack($post,$this->privatekey);
            $url=self::URL."/api/v2/cp/confirmDeparturePayInfo";
            $response=Http::post($url,[
                'accessID'=>$this->accessid,
                'sign'=>Hzbrain::sign($package,$this->privatekey),
                'cipher'=>$package
            ]);
            if(!$response->isSuccess()){
                throw new \Exception($response->errorMsg);
            }
        }
    }

    private function fee(string $requestId,array $data)
    {
        $this->output(var_export($data,true));
    }

    private function payResult(string $requestId,array $data)
    {
        /* @var Hzbrain $service*/
        $service=ContactlessService::getService("\\app\\common\\service\\contactless\\Hzbrain");
        $service->payResult($data);
        //返回消息
        $post=[
            'requestId'=>$requestId,
            'success'=>true,
            'params'=>$data
        ];
        $package=Hzbrain::pack($post,$this->privatekey);
        $url=self::URL."/api/v2/cp/confirmPayResult";
        $response=Http::post($url,[
            'accessID'=>$this->accessid,
            'sign'=>Hzbrain::sign($package,$this->privatekey),
            'cipher'=>$package
        ]);
        if(!$response->isSuccess()){
            throw new \Exception($response->errorMsg);
        }
    }

    private function clearInrecord()
    {
        while(true){
            foreach ($this->inrecord as $plate_number=>$time){
                if(time()-$time>60){
                    unset($this->inrecord[$plate_number]);
                }
            }
            Coroutine\System::sleep(1);
        }
    }

    private function getClient(string $name)
    {
        $host='parking.hzgxtc.com';
        $port=1883;
        $username=$this->accessid;
        $password=$this->secret;
        $config=new ClientConfig();
        $config->setClientId($name);
        $config->setUserName($username);
        $config->setPassword($password);
        $config->setKeepAlive(60*5);
        $client=new Client($host,$port,$config);
        return $client;
    }

    private function getSubscribe()
    {
        $accessID=$this->accessid;
        $arr=[
            "/hzcity/departurePayInfo/{$accessID}/#"=>1,
            "/hzcity/fee/{$accessID}/#"=>1,
            "/hzcity/payResult/{$accessID}/#"=>1,
            "/hzcity/v2/departurePayInfo/{$accessID}/#"=>1,
            "/hzcity/v2/fee/{$accessID}/#"=>1,
            "/hzcity/v2/payResult/{$accessID}/#"=>1
        ];
        return $arr;
    }

    private function output($msg)
    {
        $this->output->info(date('Y-m-d H:i:s').'-'.$msg);
    }
}