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

namespace app\admin\command\queueEvent;

use app\common\model\Admin;
use app\common\model\parking\ParkingAdmin;
use app\common\model\parking\ParkingMerchantUser;
use app\common\model\Third;
use app\common\model\UserToken;

//检测过期管理员账号
class CheckAdmin implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        self::clearMerch();
        self::clearAdmin();
    }

    private static function clearMerch()
    {
        $tokens=UserToken::where('merch_admin','<>',null)->where('expire','>',time())->select();
        foreach ($tokens as $token){
            $merch_admin=json_decode($token->merch_admin,true);
            if(isset($merch_admin['expire']) && $merch_admin['expire']>time()) {
                $user_id=$token->user_id;
                $merch_id=$merch_admin['id'];
                $parking_id=$merch_admin['parking_id'];
                $third_id=Third::where('user_id',$user_id)->column('id');
                $merchuser=ParkingMerchantUser::where(['parking_id'=>$parking_id,'merch_id'=>$merch_id])->whereIn('third_id',$third_id)->find();
                if(!$merchuser){
                    $token->merch_admin=null;
                    $token->save();
                }
            }
        }
    }

    private static function clearAdmin()
    {
        $tokens=UserToken::where('parking_admin','<>',null)->where('expire','>',time())->select();
        foreach ($tokens as $token){
            $parking_admin=json_decode($token->parking_admin,true);
            if(isset($parking_admin['expire']) && $parking_admin['expire']>time()){
                $success=false;
                $user_id=$token->user_id;
                $admin_id=$parking_admin['id'];
                $parking_id=$parking_admin['parking_id'];
                $admin=Admin::find($admin_id);
                if($admin && $admin->status=='normal' && $admin->third_id){
                    $third=Third::find($admin->third_id);
                    $parking_admin=ParkingAdmin::where(['parking_id'=>$parking_id,'admin_id'=>$admin->id])->find();
                    if($third && $third->user_id==$user_id && $parking_admin){
                        $success=true;
                    }
                }
                if(!$success){
                    $token->parking_admin=null;
                    $token->save();
                }
            }
        }
    }
}