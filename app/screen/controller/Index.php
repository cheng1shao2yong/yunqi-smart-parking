<?php
declare(strict_types=1);

namespace app\screen\controller;

use app\common\controller\BaseController;
use app\common\library\ParkingTestAccount;
use think\annotation\route\Get;

class Index extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
        $domain=get_domain('api');
        $this->assign('apiHost',$domain."/sentrybox/");
    }

    #[Get('/index')]
    public function index()
    {
        return $this->fetch();
    }

    #[Get('/login')]
    public function login()
    {
        $uniqid=$this->request->get('uniqid');
        if(!$uniqid){
            $this->error('参数错误');
        }
        $this->assign('uniqid',$uniqid);
        return $this->fetch();
    }
}