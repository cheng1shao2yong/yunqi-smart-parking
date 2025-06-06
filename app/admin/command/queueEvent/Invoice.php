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


//每日发票通知管理员
use app\common\service\msg\WechatMsg;
use think\facade\Db;

class Invoice implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $prefix=getDbPrefix();
        $sql="select parking_id,sum(total_price) as price,count(1) as count from {$prefix}parking_invoice where status=0 group by parking_id";
        $list=Db::query($sql);
        foreach ($list as $item){
            WechatMsg::applyInvince($item['parking_id'],(int)$item['count'],(float)$item['price']);
        }
    }
}