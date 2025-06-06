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

namespace app\common\controller;

use app\api\service\ApiAuthService;
use app\common\model\User;
use app\common\model\UserToken;
use think\exception\HttpResponseException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\Response;

class Api extends BaseController
{
    /**
     * 当前登录用户
     * @var \app\api\service\ApiAuthService
     */
    protected $auth;
    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    protected function _initialize()
    {
        $token=request()->header('token');
        if(!$token){
            $token=Cookie::get('token');
        }
        if(!$token){
            $token=request()->get('token');
        }
        if(!$token){
            $token=request()->post('token');
        }
        $actionname = $this->request->action();
        $controller = $this->request->controller();
        //防止cc攻击
        $this->ccToken($token,$controller,$actionname);
        $class=Config::get('site.auth.adapter');
        $this->auth=ApiAuthService::newInstance(['adapter'=>new $class($token)]);
        $noNeedLoginSet=is_string($this->noNeedLogin)?[$this->noNeedLogin]:$this->noNeedLogin;
        $noNeedLogin = in_array('*',$noNeedLoginSet) || in_array($actionname,$noNeedLoginSet);
        //需要登陆
        if(!$noNeedLogin && !$this->auth->isLogin()){
            $response = Response::create(__('请先登录!'), 'html', 401);
            throw new HttpResponseException($response);
        }
        if($this->auth->isLogin()){
            event('write_log','用户访问-ID:'.$this->auth->id.',昵称:'.$this->auth->nickname);
        }
    }
    
    private function ccToken($token,$controller,$actionname)
    {
        if($token){
            $minit=date('Y-m-d H:i',time());
            $vistmd5=md5($token.$minit.$controller.$actionname);
            $vistnum=Cache::get($vistmd5,0);
            if($vistnum>60){
                $class=Config::get('site.auth.adapter');
                $this->auth=ApiAuthService::newInstance(['adapter'=>new $class($token)]);
                if($this->auth->isLogin()){
                    User::where('id',$this->auth->id)->update(['status'=>'hidden']);
                    UserToken::where('user_id',$this->auth->id)->delete();
                }
                $response = Response::create('访问次数过多，系统已经保存了你的微信ID与攻击记录，公司会根据攻击情况考虑报警处理，请自重!', 'html', 403);
                throw new HttpResponseException($response);
            }
            $vistnum++;
            Cache::set($vistmd5, $vistnum, 60);
        }
    }
}