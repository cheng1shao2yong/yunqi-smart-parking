<?php
declare(strict_types=1);
namespace app\api\service;

use app\api\service\auth\Adapter;
use app\api\service\authAdapter\BaseAdapter;
use app\common\model\Accesskey;
use app\common\model\Admin;
use app\common\model\Daili;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingAdmin;
use app\common\model\parking\ParkingMerchant;
use app\common\model\Third;
use app\common\model\User;
use app\common\model\UserToken;
use app\common\service\AuthService;


class ApiAuthService extends AuthService
{
    protected $allowFields = ['id', 'nickname', 'mobile', 'avatar', 'balance', 'score'];
    private Adapter $adapter;

    public function userinfo(bool $allinfo = false)
    {
        $user=$this->adapter->userinfo();
        if(!$user){
            return false;
        }
        if($allinfo){
            return $user;
        }else{
            return array_intersect_key($user,array_flip($this->allowFields));
        }
    }

    public function logout()
    {
        $this->adapter->logout();
    }

    public function getMerchAdmin()
    {
        $usertoken=$this->adapter->getUserToken();
        if(!$usertoken->merch_admin){
            return false;
        }
        $adminInfo=json_decode($usertoken->merch_admin,true);
        return $adminInfo;
    }

    public function setMerchAdmin($merch,$is_api=false)
    {
        $merch_admin=json_encode([
            'id'=>$merch->id,
            'username'=>$merch->username,
            'nickname'=>$merch->merch_name,
            'mobile'=>$merch->phone,
            'parking_id'=>$merch->parking_id,
            'expire'=>$is_api?time()+2*3600:time()+24*3600,
            'is_api'=>$is_api?1:0
        ],JSON_UNESCAPED_UNICODE);
        $usertoken=$this->adapter->getUserToken();
        UserToken::where('id',$usertoken->id)->update(['merch_admin'=>$merch_admin]);
    }

    public function getParkingAdmin()
    {
        $usertoken=$this->adapter->getUserToken();
        if(!$usertoken->parking_admin){
            return false;
        }
        $adminInfo=json_decode($usertoken->parking_admin,true);
        if(!$adminInfo['rules']){
            $adminInfo['rules']=[];
        }
        if(!is_array($adminInfo['rules'])){
            $adminInfo['rules']=explode(',',$adminInfo['rules']);
        }
        return $adminInfo;
    }

    public function getPropertyAdmin()
    {
        $usertoken=$this->adapter->getUserToken();
        if(!$usertoken->property_admin){
            return false;
        }
        $adminInfo=json_decode($usertoken->property_admin,true);
        return $adminInfo;
    }

    public function setParkingAdmin($admin,$parking_id,$rules,$is_api=false)
    {
        $parking_admin=json_encode([
            'id'=>$admin->id,
            'username'=>$admin->username,
            'nickname'=>$admin->nickname,
            'mobile'=>$admin->mobile,
            'parking_id'=>$parking_id,
            'rules'=>$rules,
            'expire'=>$is_api?time()+2*3600:time()+24*3600,
            'is_api'=>$is_api?1:0
        ],JSON_UNESCAPED_UNICODE);
        $usertoken=$this->adapter->getUserToken();
        UserToken::where('id',$usertoken->id)->update(['parking_admin'=>$parking_admin]);
    }

    public function setAccess(Accesskey $access)
    {
        $usertoken=$this->adapter->getUserToken();
        if($access->access_type=='merchant'){
            $merch=ParkingMerchant::find($access->merchant_id);
            $this->setMerchAdmin($merch);
        }
        if($access->access_type=='parking'){
            $parkingadmin=ParkingAdmin::where(['role'=>'admin','parking_id'=>$access->parking_id])->find();
            $admin=Admin::find($parkingadmin->admin_id);
            $this->setParkingAdmin($admin,$access->parking_id,'*',true);
        }
        UserToken::where('id',$usertoken->id)->update(['access'=>json_encode($access->toArray(),JSON_UNESCAPED_UNICODE)]);
    }

    public function setDaili(Daili $daili)
    {
        $usertoken=$this->adapter->getUserToken();
        UserToken::where('id',$usertoken->id)->update(['daili'=>json_encode($daili->toArray(),JSON_UNESCAPED_UNICODE)]);
    }

    public function getDaili()
    {
        $usertoken=$this->adapter->getUserToken();
        if(!$usertoken->daili){
            return false;
        }
        $daili=json_decode($usertoken->daili,true);
        return $daili;
    }

    public function setPropertyAdmin($admin,$propertyadmin)
    {
        $property_admin=json_encode([
            'id'=>$admin->id,
            'username'=>$admin->username,
            'nickname'=>$admin->nickname,
            'mobile'=>$admin->mobile,
            'property_id'=>$propertyadmin->property_id,
            'rules'=>'*',
            'expire'=>time()+24*3600
        ],JSON_UNESCAPED_UNICODE);
        $usertoken=$this->adapter->getUserToken();
        UserToken::where('id',$usertoken->id)->update(['property_admin'=>$property_admin]);
    }

    public function getToken()
    {
        $usertoken=$this->adapter->getUserToken();
        return $usertoken->token;
    }

    public function login(string $username, string $password)
    {
        $token=uuid();
        $user=User::where('username',$username)->find();
        if(!$user){
            throw new \Exception('账号或密码错误');
        }
        if($user->password!=md5(md5($password.$user->salt))){
            throw new \Exception('账号或密码错误');
        }
        if($user->status!='normal'){
            throw new \Exception('账号已经被禁用');
        }
        $this->adapter->login($token,$user);
        $this->login_user=$this->adapter->userinfo();
    }

    public function loginByUserId(int $user_id)
    {
        $token=uuid();
        $user=User::find($user_id);
        if(!$user){
            throw new \Exception('账号或密码错误');
        }
        if($user->status!='normal'){
            throw new \Exception('账号已经被禁用');
        }
        $this->adapter->login($token,$user);
        $this->login_user=$this->adapter->userinfo();
    }

    public function loginByMobile(string $mobile, string $code)
    {
        // TODO: Implement loginByMobile() method.
    }

    public function loginByThirdPlatform(string $platform,Third $third)
    {
        $token=uuid();
        $user=User::find($third->user_id);
        if(!$user){
            throw new \Exception('账号不存在');
        }
        if($user->status!='normal'){
            throw new \Exception('账号已经被禁用');
        }
        $this->adapter->login($token,$user);
        $this->login_user=$this->adapter->userinfo();
    }

    public function updateToken(array $arr)
    {
        $usertoken=$this->adapter->getUserToken();
        UserToken::where('id',$usertoken->id)->update($arr);
    }

}