<?php
declare (strict_types = 1);

namespace app\api\controller\merchant;

use app\common\controller\Api;
use app\common\model\parking\ParkingMerchantUser;
use app\common\model\Third;
use think\exception\HttpResponseException;
use think\Response;

class Base extends Api
{
    protected $noNeedRight=[];
    protected $merch_id;
    protected $parking_id;

    protected function _initialize()
    {
        parent::_initialize();
        if(!$this->auth->isLogin()){
            return;
        }
        $actionname = $this->request->action();
        $noNeedRightSet=is_string($this->noNeedRight)?[$this->noNeedRight]:$this->noNeedRight;
        $noNeedRight = in_array('*',$noNeedRightSet) || in_array($actionname,$noNeedRightSet);
        //无需权限
        if($noNeedRight){
            return;
        }
        $time=time();
        $merchadmin=$this->auth->getMerchAdmin();
        if(!$merchadmin){
            $this->error('请先登录商户','redirect:/pages/merchant/login');
        }
        if($time>$merchadmin['expire']){
            $this->error('登录超时','redirect:/pages/merchant/login');
        }
        if(!$merchadmin['is_api'] && $merchadmin['expire']-$time<12*3600){
            $merchadmin['expire']=$time+24*3600;
            $this->auth->updateToken(['merch_admin'=>json_encode($merchadmin,JSON_UNESCAPED_UNICODE)]);
        }
        $this->merch_id=$merchadmin['id'];
        $this->parking_id=$merchadmin['parking_id'];
    }
}
