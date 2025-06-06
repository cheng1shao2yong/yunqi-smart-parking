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

//计算并更新剩余车位
use think\facade\Cache;
use think\facade\Db;

class Spaceentry implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $prefix=getDbPrefix();
        $sql="select parking_id,count(1) as count from {$prefix}parking_records where status=0 or status=1 group by parking_id";
        $list=Db::query($sql);
        foreach ($list as $item){
            $parking_id=$item['parking_id'];
            $count=$item['count'];
            Cache::set('parking_space_entry_'.$parking_id,$count);
        }
    }
}