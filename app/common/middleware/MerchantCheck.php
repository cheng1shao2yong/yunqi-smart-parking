<?php
/**
 * ----------------------------------------------------------------------------
 * 行到水穷处，坐看云起时
 * 开发软件，找贵阳云起信息科技，官网地址:https://www.56q7.com/
 * ----------------------------------------------------------------------------
 * Author: 老成
 * email：85556713@qq.com
 */
declare (strict_types = 1);

namespace app\common\middleware;

use app\common\service\PayService;

class MerchantCheck
{
    public function handle($request, \Closure $next)
    {
        return $next($request);
    }

    public static function recordsFilter()
    {
        return true;
    }

    public static function checkSubMerchNo(PayService $service)
    {
        return true;
    }

    public static function checkSplitMerchNo(PayService $service)
    {
        return true;
    }
}