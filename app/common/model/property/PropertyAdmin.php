<?php
declare(strict_types=1);

namespace app\common\model\property;

use app\common\model\Admin;
use app\common\model\manage\Parking;
use app\common\model\manage\Property;
use think\facade\Validate;
use think\Model;

class PropertyAdmin Extends Model
{
    const USER_TYPE=[
        'admin'=>'管理员',
        'watchhouse'=>'岗亭',
        'treasurer'=>'财务',
        'clerk'=>'文员',
        'else'=>'其他'
    ];

    public function admin()
    {
        return $this->hasOne(Admin::class,'id','admin_id')->field('id,username,nickname,mobile,status,third_id');
    }

    public function property()
    {
        return $this->hasOne(Property::class,'id','property_id');
    }

    public static function addAdmin(Property $property, PropertyAdmin $propertyadmin, array $admin)
    {
        $salt = str_rand(4);
        if (!$admin['password']) {
            throw new \Exception('请输入密码');
        }
        if (!Validate::is($admin['password'], '\S{6,30}')) {
            throw new \Exception('密码长度不对');
        }
        $username=$property->uniqid.'-'.$admin['username'];
        $has=Admin::where('username', $username)->find();
        if ($has) {
            throw new \Exception('用户名已存在');
        }
        $insert=[
            'username'=>$username,
            'nickname'=>$admin['nickname'],
            'salt'=>$salt,
            'third_id'=>$admin['third_id']??null,
            'password'=>md5(md5($admin['password']) . $salt),
            'groupids'=>2
        ];
        $admin=new Admin();
        $admin->save($insert);
        $propertyadmin->admin_id=$admin->id;
        $propertyadmin->save();
    }

    public static function editAdmin(Property $property, PropertyAdmin $propertyadmin, array $admin)
    {
        $username=$property->uniqid.'-'.$admin['username'];
        $has=Admin::where(function ($query) use ($username,$propertyadmin){
            $query->where('id','<>',$propertyadmin->admin_id);
            $query->where('username', $username);
        })->find();
        if ($has) {
            throw new \Exception('用户名已存在');
        }
        $insert=[
            'id'=>$propertyadmin->admin_id,
            'username'=>$username,
            'nickname'=>$admin['nickname'],
            'third_id'=>$admin['third_id']??null,
            'groupids'=>2
        ];
        if($admin['password']){
            $salt = str_rand(4);
            if (!Validate::is($admin['password'], '\S{6,30}')) {
                throw new \Exception('密码长度不对');
            }
            $insert['salt']=$salt;
            $insert['password']=md5(md5($admin['password']) . $salt);
        }
        $propertyadmin->admin->save($insert);
        $propertyadmin->save();
    }
}
