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

use think\facade\Route;
use think\Response;

//后台首页
Route::get('/',function(){
    $response = Response::create(['code'=>0,'msg'=>'找不到页面'], 'json', 404);
    return $response;
});