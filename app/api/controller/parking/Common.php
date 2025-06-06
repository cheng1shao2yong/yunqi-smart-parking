<?php
declare (strict_types = 1);

namespace app\api\controller\parking;

use app\common\model\Admin;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingAdmin;
use app\common\model\parking\ParkingLog;
use app\common\model\property\PropertyAdmin;
use app\common\model\Third;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Cache;

#[Group("parking/common")]
class Common extends Base
{
    protected $noNeedParkingLogin='*';

    #[Get('info')]
    public function info()
    {
        $app=site_config("basic");
        $app['logo']=formatImage($app['logo']);
        $app['logo_white']=formatImage($app['logo_white']);
        $this->success('',$app);
    }

    #[Get('list')]
    public function list()
    {
        $thirds=Third::where('user_id',$this->auth->id)->column('id');
        $r=[];
        $loginParkingAdmin=$this->auth->getParkingAdmin();
        $loginPropertyAdmin=$this->auth->getPropertyAdmin();
        $list1=Admin::where('groupids',2)->whereIn('third_id',$thirds)->select()->toArray();
        foreach ($list1 as $value){
            $propertyadmin=PropertyAdmin::withJoin(['property'],'left')->where('admin_id',$value['id'])->find();
            if(!$propertyadmin){
                continue;
            }
            $value['property']=$propertyadmin->property;
            unset($value['password']);
            unset($value['salt']);
            if($loginPropertyAdmin && time()<$loginPropertyAdmin['expire'] && $loginPropertyAdmin['property_id']==$value['property']->id){
                $value['active']=1;
            }
            $r[]=$value;
        }
        $list2=Admin::where('groupids',3)->whereIn('third_id',$thirds)->select()->toArray();
        foreach ($list2 as $value){
            $parkingadmin=ParkingAdmin::withJoin(['parking'],'left')->where('admin_id',$value['id'])->find();
            if(!$parkingadmin){
                continue;
            }
            $value['parking']=$parkingadmin->parking;
            unset($value['password']);
            unset($value['salt']);
            if($loginParkingAdmin && time()<$loginParkingAdmin['expire'] && $loginParkingAdmin['parking_id']==$value['parking']->id){
                $value['active']=1;
            }
            $r[]=$value;
        }
        $this->success('',$r);
    }

    #[Post('login')]
    public function login()
    {
        $username=$this->request->post('username');
        $password=$this->request->post('password');
        $type=$this->request->post('type');
        $groupids=[
            'property'=>2,
            'parking'=>3
        ];
        $loginnumber=Cache::get('parking-admin-'.$this->auth->id);
        if($loginnumber>5){
            $this->error('登录次数过多，请稍后再试');
        }
        $admin=Admin::where(['username'=>$username,'groupids'=>$groupids[$type]])->find();
        if(!$admin){
            $loginnumber++;
            Cache::set('parking-admin-'.$this->auth->id,$loginnumber,10*60);
            $this->error('管理员不存在');
        }
        if(md5(md5($password).$admin->salt)!=$admin->password){
            $loginnumber++;
            Cache::set('parking-admin-'.$this->auth->id,$loginnumber,10*60);
            $this->error('账号或密码不正确');
        }
        if($admin->status!='normal'){
            $this->error('管理员已被禁用');
        }
        if($type=='parking'){
            $parkingadmin=ParkingAdmin::where('admin_id',$admin->id)->find();
            $this->auth->setParkingAdmin($admin,$parkingadmin->parking_id,$parkingadmin->mobile_rules);
        }
        if($type=='property'){
            $propertyadmin=PropertyAdmin::where('admin_id',$admin->id)->find();
            $this->auth->setPropertyAdmin($admin,$propertyadmin);
            $parking=Parking::whereIn('property_id',$propertyadmin->property_id)->find();
            if(!$parking){
                $this->error('你所在物业没有绑定任何停车场');
            }
            $this->auth->setParkingAdmin($admin,$parking->id,'*');
        }
        $this->success('登录成功');
    }

    //更新删除
    #[Post('change')]
    public function change()
    {
        $id=$this->request->post('id');
        $thirds=Third::where('user_id',$this->auth->id)->column('id');
        $admin=Admin::find($id);
        if($admin->status!='normal'){
            $this->error('账号已禁用');
        }
        if(!$admin || !in_array($admin->third_id,$thirds)){
            $this->error('没有权限');
        }
        //集团账户
        if($admin->groupids==2){
            $propertyadmin=PropertyAdmin::where('admin_id',$admin->id)->find();
            $this->auth->setPropertyAdmin($admin,$propertyadmin);
            $parking=Parking::where('property_id',$propertyadmin->property_id)->find();
            $this->auth->setParkingAdmin($admin,$parking->id,'*');
        }
        //停车场账户
        if($admin->groupids==3){
            $parkingadmin=ParkingAdmin::where('admin_id',$admin->id)->find();
            $this->auth->setParkingAdmin($admin,$parkingadmin->parking_id,$parkingadmin->mobile_rules);
        }
        $this->success('操作成功');
    }

    #[Post('change-parking')]
    public function changeParking()
    {
        $parking_id=$this->request->post('parking_id');
        $propertyadmin=$this->auth->getPropertyAdmin();
        if(!$propertyadmin){
            $this->error('没有权限');
        }
        $parking=Parking::find($parking_id);
        if(!$parking || $parking->property_id!=$propertyadmin['property_id']){
            $this->error('没有权限');
        }
        $admin=Admin::find($propertyadmin['id']);
        $this->auth->setParkingAdmin($admin,$parking->id,'*');
        $this->success('操作成功');
    }

    #[Get('log')]
    public function log()
    {
        $page=$this->request->get('page/d');
        $list=ParkingLog::where(function ($query){
            $loginParkingAdmin=$this->auth->getParkingAdmin();
            $query->where('parking_id',$loginParkingAdmin['parking_id']);
            $starttime=$this->request->get('starttime');
            $endtime=$this->request->get('endtime');
            $radio=$this->request->get('radio');
            $plate_number=$this->request->get('plate_number');
            if($plate_number && is_car_license($plate_number)){
                $plate_number=strtoupper($plate_number);
                $query->whereLike('message','%'.$plate_number.'%');
            }
            if($starttime){
                $starttime=strtotime($starttime.' 00:00:00');
            }
            if($endtime){
                $endtime=strtotime($endtime.' 23:59:59');
            }
            if($starttime && $endtime){
                $query->whereBetween('createtime',[$starttime,$endtime]);
            }elseif($starttime){
                $query->where('createtime','>=',$starttime);
            }elseif($endtime){
                $query->where('createtime','<=',$endtime);
            }
            if($radio){
                $query->where('manual',1);
            }
        })
        ->order('id desc')
        ->limit(($page-1)*15,15)
        ->select();
        $this->success('',$list);
    }

    #[Get('logout')]
    public function logout()
    {
        $this->auth->updateToken(['parking_admin'=>null,'property_admin'=>null]);
        $this->success('操作成功');
    }
}
