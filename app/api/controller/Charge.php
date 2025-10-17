<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\api\service\ApiAuthService;
use app\common\controller\Api;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingCharge;
use app\common\service\charge\FastCloudCharge;
use app\common\service\charge\Telaidian;
use app\common\service\charge\Weilai;
use app\common\service\charge\XiangQianChong;
use app\common\service\charge\Xiaoju;
use app\common\service\charge\XinDianTu;
use app\common\service\charge\Xingxing;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\facade\Config;

#[Group("charge")]
class Charge extends Api
{
    protected $noNeedLogin = ['*'];

    #[Route('GET,POST','event')]
    public function event()
    {
        $postdata=$this->request->post();
        try{
            FastCloudCharge::run($postdata);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        return "{\"result\":0}";
    }

    #[Route('GET,POST','xiang-qian-chong')]
    public function xiangQianChong()
    {
        $postdata=$this->request->post();
        try{
            XiangQianChong::run($postdata);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        echo 'OK';
    }

    #[Route('GET,POST','xiaoju/queryPlateNo')]
    public function xiaojuQueryPlateNo()
    {
        $postdata=$this->request->post();
        $merchId=$postdata['merchId'];
        $plateNo=$postdata['plateNo'];
        $r=[
            'code'=>10000,
            'msg'=>'请求成功',
            'data'=>[
                "plateNo"=>$plateNo,
                "merchId"=>$merchId,
                "carState"=>1
            ]
        ];
        return json_encode($r,JSON_UNESCAPED_UNICODE);
    }

    #[Route('GET,POST','xiaoju/pushWaiver')]
    public function xiaojuPushWaiver()
    {
        $postdata=$this->request->post();
        $result=[
            'code'=>10000,
            'msg'=>'减免成功',
            'data'=>[
                'enterTime'=>date('Y-m-d hH:i:s')
            ]
        ];
        try{
            Xiaoju::run($postdata);
        }catch (\Exception $e){
            $result['code']=90001;
            $result['msg']=$e->getMessage();
        }
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    #[Route('GET,POST','telaidian/notification_charge_end_order_info')]
    public function telaidianInfo()
    {
        $str=file_get_contents('php://input');
        $str=json_decode($str,true);
        $result=[
            'Ret'=>0,
            'Msg'=>'',
            'Sig'=>md5(date('YmdHis'))
        ];
        try{
            $result['Data']=Telaidian::run($str['Data']);
        }catch (\Exception $e){
            $result['Msg']=$e->getMessage();
            $result['Ret']=4004;
        }
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    #[Route('GET,POST','telaidian/query_token')]
    public function telaidianQueryToken()
    {
        $result=[
            'Ret'=>0,
            'Msg'=>'',
            'Sig'=>md5(date('YmdHis'))
        ];
        try{
            $result['Data']=Telaidian::getAccessToken();
        }catch (\Exception $e){
            $result['Msg']=$e->getMessage();
            $result['Ret']=4004;
        }
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    #[Route('GET,POST','xindiantu/chargePile/chargingRecord')]
    public function xindiantu()
    {
        $postdata=$this->request->post();
        try{
            XinDianTu::run($postdata);
            return '{"result":0}';
        }catch (\Exception $e){
            return '{"result":1, "description":"'.$e->getMessage().'"}';
        }
    }

    #[Route('GET,POST','weilai/deduct')]
    public function weilai()
    {
        $postdata=$this->request->post();
        try{
            Weilai::run($postdata);
            return '{"result_code":"success","message":"减免成功"}';
        }catch (\Exception $e){
            return '{"result_code":"failed","message":"'.$e->getMessage().'"}';
        }
    }

    #[Route('GET,POST','xingxing/notification_parking_charge_result')]
    public function xingxing()
    {
        $postdata=$this->request->post();
        if(intval($postdata['StartChargeSeqStat'])==4){
            try{
                Xingxing::run($postdata);
                return '{"result_code":"success","message":"减免成功"}';
            }catch (\Exception $e){
                return '{"result_code":"failed","message":"'.$e->getMessage().'"}';
            }
        }
        return '{"result_code":"failed","message":"充电未完成"}';
    }

    #[Route('POST','run')]
    public function run()
    {
        $token=request()->header('token');
        $class=Config::get('site.auth.adapter');
        $this->auth=ApiAuthService::newInstance(['adapter'=>new $class($token)]);
        if(!$this->auth->isLogin()){
            $this->error('未登录');
        }
        $parking_id=$this->request->post('parking_id');
        $parking=Parking::where('uniqid',$parking_id)->find();
        if(!$parking){
            $this->error('停车场不存在');
        }
        /* @var ParkingCharge $charge*/
        $charge=ParkingCharge::where('parking_id',$parking->id)->find();
        if(!$charge){
            $this->error('未配置收费规则');
        }
        $platenumber=$this->request->post('plate_number');
        if(!is_car_license($platenumber)){
            $this->error('车牌号格式错误');
        }
        $fee=$this->request->post('fee');
        $kwh=$this->request->post('kwh');
        $starttime=$this->request->post('starttime');
        $endtime=$this->request->post('endtime');
        $time=strtotime($endtime)-strtotime($starttime);
        if($time<=0){
            throw new \Exception('时间错误');
        }
        try {
            $charge->send($platenumber,$fee,$kwh,$time);
        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('成功');
    }
}
