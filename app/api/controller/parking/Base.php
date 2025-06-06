<?php
declare (strict_types = 1);

namespace app\api\controller\parking;

use app\common\controller\Api;
use app\common\model\parking\ParkingAdmin;

class Base extends Api
{
    protected $noNeedParkingLogin=[];
    protected $noNeedParkingRight=[];

    protected $parking_id;
    protected $parkingAdmin;

    protected function _initialize()
    {
        parent::_initialize();
        $actionname = $this->request->action();
        $controller = 'app\\api\\controller\\'.str_replace('.','\\',$this->request->controller());
        $noNeedLoginSet=is_string($this->noNeedParkingLogin)?[$this->noNeedParkingLogin]:$this->noNeedParkingLogin;
        $noNeedRightSet=is_string($this->noNeedParkingRight)?[$this->noNeedParkingRight]:$this->noNeedParkingRight;
        //无需车场登录
        if(in_array('*',$noNeedLoginSet) || in_array($actionname,$noNeedLoginSet)){
            return;
        }
        $time=time();
        $propertyadmin=$this->auth->getPropertyAdmin();
        $parkingadmin=$this->auth->getParkingAdmin();
        if($propertyadmin && $time>$propertyadmin['expire']){
            $this->error('登录超时','redirect:/pages/parking/login');
        }
        if($propertyadmin && $propertyadmin['expire']-$time<12*3600){
            $propertyadmin['expire']=$time+24*3600;
            $this->auth->updateToken(['property_admin'=>json_encode($propertyadmin,JSON_UNESCAPED_UNICODE)]);
        }
        if(!$parkingadmin){
            $this->error('请先登录停车场管理','redirect:/pages/parking/login');
        }
        if($time>$parkingadmin['expire']){
            if($parkingadmin['is_api']){
                $this->error('token过期');
            }else{
                $this->error('登录超时','redirect:/pages/parking/login');
            }
        }
        if(!$parkingadmin['is_api'] && $parkingadmin['expire']-$time<12*3600){
            $parkingadmin['expire']=$time+24*3600;
            $this->auth->updateToken(['parking_admin'=>json_encode($parkingadmin,JSON_UNESCAPED_UNICODE)]);
        }
        $isAccess=false;
        //无需权限
        if(in_array('*',$noNeedRightSet) || in_array($actionname,$noNeedRightSet)){
            $isAccess=true;
        //超级管理员
        }else if(in_array('*',$parkingadmin['rules'])){
            $isAccess=true;
        }else{
            $authId='';
            foreach (ParkingAdmin::AUTH as $auth){
                $auth['action']=explode(',',$auth['action']);
                if($auth['controller']==$controller && in_array($actionname,$auth['action'])){
                    $authId=$auth['id'];
                    break;
                }
            }
            if($authId && in_array($authId,$parkingadmin['rules'])){
                $isAccess=true;
            }
        }
        if(!$isAccess){
            $this->error('没有操作权限');
        }
        $this->parkingAdmin=$parkingadmin;
        $this->parking_id=$parkingadmin['parking_id'];
    }
}
