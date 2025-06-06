<?php
declare(strict_types=1);
namespace app\api\service\auth;
use app\common\model\User;
use app\common\model\UserToken;

interface Adapter{
    /**
     * 获取用户信息
     */
    public function userinfo():array|bool;

    /**
     * 获取用户token
     */
    public function getUserToken():UserToken|false;
    /**
     * 退出登录
     */
    public function logout();
    /**
     * 登录
     */
    public function login(string $token,User $user);
}