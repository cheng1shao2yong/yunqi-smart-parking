<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\Admin;
use app\common\model\manage\Parking;
use app\common\model\UserToken;
use think\facade\Cache;
use think\facade\Validate;
use think\Model;

class ParkingAdmin Extends Model
{
    const USER_TYPE=[
        'admin'=>'管理员',
        'watchhouse'=>'岗亭',
        'treasurer'=>'财务',
        'clerk'=>'文员',
        'else'=>'其他'
    ];

    const AUTH=[
        [
            'id'=>'records-info',
            'name'=>'车场情况',
            'controller'=>\app\api\controller\parking\Index::class,
            'action'=>'records',
            'menus'=>''
        ],
        [
            'id'=>'pay-info',
            'name'=>'收入情况',
            'controller'=>\app\api\controller\parking\Index::class,
            'action'=>'pay',
            'menus'=>''
        ],
        [
            'id'=>'merchant-info',
            'name'=>'商户情况',
            'controller'=>\app\api\controller\parking\Index::class,
            'action'=>'merchant',
            'menus'=>''
        ],
        [
            'id'=>'monitor',
            'name'=>'通道监控',
            'controller'=>\app\api\controller\parking\Screen::class,
            'action'=>'barrier,online,open,close,photo',
            'menus'=>''
        ],
        [
            'id'=>'search',
            'name'=>'车辆查询',
            'controller'=>\app\api\controller\parking\Index::class,
            'action'=>'search',
            'menus'=>'parking/plate?type=search'
        ],
        [
            'id'=>'records-instock',
            'name'=>'在库车辆',
            'controller'=>\app\api\controller\parking\Records::class,
            'action'=>'instock',
            'menus'=>'parking/records/instock'
        ],
        [
            'id'=>'records-list',
            'name'=>'出入记录',
            'controller'=>\app\api\controller\parking\Records::class,
            'action'=>'list,detail,edit',
            'menus'=>'parking/records/list'
        ],
        [
            'id'=>'records-exception',
            'name'=>'出入异常',
            'controller'=>\app\api\controller\parking\Records::class,
            'action'=>'list,detail',
            'menus'=>'parking/records/list?exception=1'
        ],
        [
            'id'=>'records-unable',
            'name'=>'限行记录',
            'controller'=>\app\api\controller\parking\Records::class,
            'action'=>'exception',
            'menus'=>'parking/records/exception'
        ],
        [
            'id'=>'entry',
            'name'=>'手动入场',
            'controller'=>\app\api\controller\parking\Records::class,
            'action'=>'getInfo,photo,entry',
            'menus'=>'parking/records/entry'
        ],
        [
            'id'=>'exit',
            'name'=>'手动出场',
            'controller'=>\app\api\controller\parking\Records::class,
            'action'=>'getInfo,photo,exit,qrcode',
            'menus'=>'parking/records/exit'
        ],
        [
            'id'=>'records-pay',
            'name'=>'临停缴费',
            'controller'=>\app\api\controller\parking\Records::class,
            'action'=>'payInfo,qrcode,pay',
            'menus'=>'parking/plate?type=pay'
        ],
        [
            'id'=>'recovery',
            'name'=>'逃费追缴',
            'controller'=>\app\api\controller\parking\Records::class,
            'action'=>'recovery,free,cancelRecovery,setRecovery,recoveryInfo',
            'menus'=>'parking/records/recovery'
        ],
        [
            'id'=>'cars-monthly',
            'name'=>'月租车',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'list,detail,edit,rechargeDetail,log,recharge,del,info,plates,occupat,delplate,addplate',
            'menus'=>'parking/cars/list?type=monthly'
        ],
        [
            'id'=>'cars-day',
            'name'=>'日租车',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'list,detail,edit,rechargeDetail,log,recharge,del,info',
            'menus'=>'parking/cars/list?type=day'
        ],
        [
            'id'=>'cars-stored',
            'name'=>'储值车',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'list,detail,edit,rechargeDetail,log,recharge,del,info',
            'menus'=>'parking/cars/list?type=stored'
        ],
        [
            'id'=>'cars-member',
            'name'=>'会员车',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'list,detail,edit,rechargeDetail,log,recharge,del,info',
            'menus'=>'parking/cars/list?type=member'
        ],
        [
            'id'=>'cars-vip',
            'name'=>'VIP车',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'list,detail,edit,rechargeDetail,log,recharge,del,info',
            'menus'=>'parking/cars/list?type=vip'
        ],
        [
            'id'=>'cars-blacklist',
            'name'=>'黑名单',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'blacklist,addBlack,delBlack',
            'menus'=>'parking/cars/black'
        ],
        [
            'id'=>'cars-recyclebin',
            'name'=>'回收站',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'list,restore,destroy',
            'menus'=>'parking/cars/recyclebin'
        ],
        [
            'id'=>'monthly-recharge',
            'name'=>'月卡充值',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'rechargeDetail,recharge',
            'menus'=>'parking/plate?type=monthly_pay'
        ],
        [
            'id'=>'stored-recharge',
            'name'=>'储值卡充值',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'rechargeDetail,recharge',
            'menus'=>'parking/plate?type=stored_pay'
        ],
        [
            'id'=>'cars-apply',
            'name'=>'用户申请',
            'controller'=>\app\api\controller\parking\Cars::class,
            'action'=>'apply,applyDetail,doApply',
            'menus'=>'parking/plate?type=stored_pay'
        ],
        [
            'id'=>'merchant-add',
            'name'=>'添加商户',
            'controller'=>\app\api\controller\parking\Merch::class,
            'action'=>'edit',
            'menus'=>'parking/merch/detail'
        ],
        [
            'id'=>'merchant-normal',
            'name'=>'普通商户',
            'controller'=>\app\api\controller\parking\Merch::class,
            'action'=>'list,edit,info,detail,rechargeDetail,recharge,log,del',
            'menus'=>'parking/merch/list'
        ],
        [
            'id'=>'merchant-ziying',
            'name'=>'自营商户',
            'controller'=>\app\api\controller\parking\Merch::class,
            'action'=>'list,edit,info,detail,rechargeDetail,recharge,log,del',
            'menus'=>'parking/merch/list?ziying=1'
        ],
        [
            'id'=>'merchant-send',
            'name'=>'发停车券',
            'controller'=>\app\api\controller\parking\Merch::class,
            'action'=>'search,getCoupon,couponSend',
            'menus'=>'parking/merch/search?type=send'
        ],
        [
            'id'=>'merchant-recharge',
            'name'=>'商户充值',
            'controller'=>\app\api\controller\parking\Merch::class,
            'action'=>'search,rechargeDetail,recharge',
            'menus'=>'parking/merch/search?type=recharge'
        ],
        [
            'id'=>'merchant-coupon',
            'name'=>'停车券查看',
            'controller'=>\app\api\controller\parking\Merch::class,
            'action'=>'couponList,couponDetail,couponTrush',
            'menus'=>'parking/merch/coupon'
        ],
        [
            'id'=>'merchant-setting',
            'name'=>'停车券设置',
            'controller'=>\app\api\controller\parking\Merch::class,
            'action'=>'couponSettingList,changeCouponStatus,couponSettingDetail,couponSetting,delCoupon',
            'menus'=>'parking/merch/coupon-setting-list'
        ],
        [
            'id'=>'finance-list',
            'name'=>'收入流水',
            'controller'=>\app\api\controller\parking\Finance::class,
            'action'=>'list,refund',
            'menus'=>'parking/finance/list'
        ],
        [
            'id'=>'finance-settle',
            'name'=>'结算账单',
            'controller'=>\app\api\controller\parking\Finance::class,
            'action'=>'settle',
            'menus'=>'parking/finance/settle'
        ],
        [
            'id'=>'finance-invoice',
            'name'=>'发票申领',
            'controller'=>\app\api\controller\parking\Finance::class,
            'action'=>'invoice',
            'menus'=>'parking/finance/invoice'
        ],
    ];

    const MENU = [
        'records' => [
            [
                'title' => '车辆查询',
                'page' => 'parking/plate?type=search',
                'icon' => 'search.png',
                'width' => 50,
                'height' => 50
            ],
            [
                'title' => '在库车辆',
                'page' => 'parking/records/instock',
                'icon' => 'entry_car.png',
                'width' => 50,
                'height' => 50
            ],
            [
                'title' => '出入记录',
                'page' => 'parking/records/list',
                'icon' => 'jilu.png',
                'width' => 50,
                'height' => 50
            ],
            [
                'title' => '出入异常',
                'page' => 'parking/records/list?exception=1',
                'icon' => 'excep.png'
            ],
            [
                'title' => '限行记录',
                'page' => 'parking/records/exception',
                'icon' => 'rlog.png',
                'width' => 50,
                'height' => 50
            ],
            [
                'title' => '手动入场',
                'page' => 'parking/records/entry',
                'icon' => 'pentry.png',
                'width' => 50,
                'height' => 50
            ],
            [
                'title' => '手动出场',
                'page' => 'parking/records/exit',
                'icon' => 'rexit.png',
                'width' => 50,
                'height' => 50
            ],
            [
                'title' => '手动缴费',
                'page' => 'parking/plate?type=pay',
                'icon' => 'rpay.png',
                'width' => 50,
                'height' => 50
            ],
            [
                'title' => '逃费追缴',
                'page' => 'parking/records/recovery',
                'icon' => 'recovery.png',
                'width' => 50,
                'height' => 50
            ]
        ],
        'cars' => [
            [
                'title' => '月租车',
                'page' => 'parking/cars/list?type=monthly',
                'icon' => 'rmonthy.png',
                'width' => 54,
                'height' => 54
            ],
            [
                'title' => '日租车',
                'page' => 'parking/cars/list?type=day',
                'icon' => 'rday.png',
                'width' => 66,
                'height' => 66
            ],
            [
                'title' => '储值车',
                'page' => 'parking/cars/list?type=stored',
                'icon' => 'rstored.png',
                'width' => 56,
                'height' => 56
            ],
            [
                'title' => '会员车',
                'page' => 'parking/cars/list?type=member',
                'icon' => 'member.png',
                'width' => 56,
                'height' => 56
            ],
            [
                'title' => 'VIP车',
                'page' => 'parking/cars/list?type=vip',
                'icon' => 'VIP.png',
                'width' => 56,
                'height' => 56
            ],
            [
                'title' => '黑名单',
                'page' => 'parking/cars/black',
                'icon' => 'black.png',
                'width' => 56,
                'height' => 56
            ],
            [
                'title' => '回收站',
                'page' => 'parking/cars/recyclebin',
                'icon' => 'recyclebin.png',
                'width' => 64,
                'height' => 64
            ],
            [
                'title' => '月卡充值',
                'page' => 'parking/plate?type=monthly_pay',
                'icon' => 'recharge.png',
                'width' => 56,
                'height' => 56
            ],
            [
                'title' => '储值卡充值',
                'page' => 'parking/plate?type=stored_pay',
                'icon' => 'chong.png',
                'width' => 56,
                'height' => 56
            ],
            [
                'title' => '用户申请',
                'page' => 'parking/cars/apply',
                'icon' => 'apply.png',
                'width' => 50,
                'height' => 50
            ]
        ],
        'merchant' => [
            [
                'title' => '添加商户',
                'page' => 'parking/merch/detail',
                'icon' => 'add.png'
            ],
            [
                'title' => '普通商户',
                'page' => 'parking/merch/list',
                'icon' => 'merch.png',
                'width' => 56,
                'height' => 56
            ],
            [
                'title' => '自营商户',
                'page' => 'parking/merch/list?ziying=1',
                'icon' => 'zmerch.png',
                'width' => 56,
                'height' => 56
            ],
            [
                'title' => '发停车券',
                'page' => 'parking/merch/search?type=send',
                'icon' => 'scoupon.png',
                'width' => 70,
                'height' => 70
            ],
            [
                'title' => '商户充值',
                'page' => 'parking/merch/search?type=recharge',
                'icon' => 'srecharge.png',
                'width' => 56,
                'height' => 56
            ],
            [
                'title' => '停车券查看',
                'page' => 'parking/merch/coupon',
                'icon' => 'list.png'
            ],
            [
                'title' => '停车券设置',
                'page' => 'parking/merch/coupon-setting-list',
                'icon' => 'set.png'
            ],
        ],
        'finance' => [
            [
                'title' => '收入流水',
                'page' => 'parking/finance/list',
                'icon' => 'xpay.png'
            ],
            [
                'title' => '结算账单',
                'page' => 'parking/finance/settle',
                'icon' => 'settle.png'
            ],
            [
                'title' => '发票申领',
                'page' => 'parking/finance/invoice',
                'icon' => 'xfapiao.png'
            ]
        ]
    ];

    public function admin()
    {
        return $this->hasOne(Admin::class,'id','admin_id')->field('id,username,nickname,mobile,status,third_id');
    }

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id');
    }

    public static function checkMenuAuth($menu,$rules)
    {
        if(in_array('*',$rules)){
            return true;
        }
        foreach ($rules as $rule){
            foreach (self::AUTH as $auth){
                if($auth['id']==$rule && $auth['menus']==$menu['page']){
                    return true;
                }
            }
        }
        return false;
    }

    public static function addAdmin(Parking $parking,ParkingAdmin $parkadmin,array $options)
    {
        $salt = str_rand(4);
        if (!$options['password']) {
            throw new \Exception('请输入密码');
        }
        if (!Validate::is($options['password'], '\S{6,30}')) {
            throw new \Exception('密码长度不对');
        }
        $username=$parking->uniqid.'-'.$options['username'];
        $has=Admin::where('username', $username)->find();
        if ($has) {
            throw new \Exception('用户名已存在');
        }
        $nickname=($parkadmin->role=='admin')?$parking['contact']:$options['nickname'];
        $mobile=($parkadmin->role=='admin')?$parking['phone']:$options['mobile'];
        $insert=[
            'username'=>$username,
            'nickname'=>$nickname,
            'mobile'=>$mobile,
            'salt'=>$salt,
            'third_id'=>$options['third_id']??null,
            'password'=>md5(md5($options['password']) . $salt),
            'groupids'=>3
        ];
        $admin=new Admin();
        $admin->save($insert);
        $parkadmin->admin_id=$admin->id;
        $parkadmin->save();
    }

    public static function editAdmin(Parking $parking,ParkingAdmin $parkadmin,array $options)
    {
        $username=$parking->uniqid.'-'.$options['username'];
        $has=Admin::where(function ($query) use ($username,$parkadmin){
            $query->where('id','<>',$parkadmin->admin_id);
            $query->where('username', $username);
        })->find();
        if ($has) {
            throw new \Exception('用户名已存在');
        }
        $nickname=($parkadmin->role=='admin')?$parking['contact']:$options['nickname'];
        $mobile=($parkadmin->role=='admin')?$parking['phone']:$options['mobile'];
        $insert=[
            'id'=>$parkadmin->admin_id,
            'username'=>$username,
            'nickname'=>$nickname,
            'mobile'=>$mobile,
            'third_id'=>$options['third_id']??null,
            'groupids'=>3
        ];
        if($options['password']){
            $salt = str_rand(4);
            if (!Validate::is($options['password'], '\S{6,30}')) {
                throw new \Exception('密码长度不对');
            }
            $insert['salt']=$salt;
            $insert['password']=md5(md5($options['password']) . $salt);
        }
        $parkadmin->admin->save($insert);
        $parkadmin->save();
        $tokens=UserToken::where('parking_admin','<>',null)->where('expire','>',time())->select();
        foreach ($tokens as $token){
            $parking_admin=json_decode($token->parking_admin,true);
            if($parking_admin['parking_id']==$parking->id && $parking_admin['id']==$parkadmin->admin_id){
                $token->parking_admin=null;
                $token->save();
            }
        }
    }
}
