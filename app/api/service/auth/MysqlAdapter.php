<?php
declare(strict_types=1);
namespace app\api\service\auth;


use app\common\model\UserToken;
use app\common\model\User;
use think\facade\Config;

class MysqlAdapter implements Adapter
{
    private UserToken $usertoken;

    public function __construct(string $token=null)
    {
        if(!$token){
            return;
        }
        $time=time();
        $usertoken=UserToken::where(function ($query) use ($token,$time){
            $token=md5($token);
            $query->where('token','=',$token);
            $query->where('expire','>',$time);
        })->withJoin('user','right')->find();
        if($usertoken){
            $auth=Config::get('site.auth');
            //当登陆时间小于保持登陆时间的一半时，自动续时
            if($auth['keepalive'] && $usertoken->expire-$time<$auth['keepalive_time']/2){
                $usertoken->expire=$time+$auth['keepalive_time'];
                $usertoken->save();
            }
            $usertoken->token=$token;
            $this->usertoken=$usertoken;
        }
    }

    public function userinfo():array|false
    {
        if(isset($this->usertoken)){
            return $this->usertoken->user->toArray();
        }
        return false;
    }

    public function getUserToken():UserToken|false
    {
        if(isset($this->usertoken)){
            return $this->usertoken;
        }
        return false;
    }

    public function login(string $token,User $user)
    {
        $keepalive_time=Config::get('site.auth.keepalive_time');
        $this->usertoken=UserToken::create([
            'token'=>md5($token),
            'user_id'=>$user->id,
            'expire'=>time()+$keepalive_time
        ]);
        $this->usertoken->token=$token;
        $this->usertoken->user=$user;
        $allow_device_num=Config::get('site.auth.allow_device_num');
        //如果数据库中保存的设备数大于允许的设备数，如果超出则挤出去最早登陆的设备
        $time=time();
        $count=UserToken::where('user_id',$user->id)->where('expire','>',$time)->count();
        if($count>$allow_device_num){
            $usertoken=UserToken::where('user_id',$user->id)->where('expire','>',$time)->order('id','asc')->find();
            $usertoken->delete();
        }
    }

    public function logout()
    {
        UserToken::where('token',$this->usertoken->token)->delete();
    }
}