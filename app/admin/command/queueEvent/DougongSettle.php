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

use app\common\model\manage\Parking;
use app\common\service\PayService;
use think\facade\Db;

//处理斗拱每日结算
class DougongSettle implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {

    }
}