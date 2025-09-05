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

use think\facade\Db;

//删除脏数据
class DeleteDirty implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $time1=time()-24*3600;
        $time10=time()-24*3600*10;
        $time200=time()-24*3600*200;
        $str="
            DELETE FROM yun_parking_cars where deletetime is NOT null;
            DELETE FROM yun_parking_cars where endtime<{$time200};
            DELETE FROM yun_parking_monthly_recharge where cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_plate where cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_cars_apply where cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_cars_logs where cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_cars_occupat where cars_id not in (SELECT id FROM yun_parking_cars);
            UPDATE yun_parking_records SET cars_id=null where cars_id is not null and cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_trigger where createtime<{$time10};
            DELETE FROM yun_parking_log where createtime<{$time10};
            DELETE FROM yun_parking_records_pay where pay_id is null and createtime<{$time1};
            DELETE FROM yun_pay_union where pay_status=0 and createtime<{$time1};
        ";
        $sqls=explode(';',$str);
        foreach ($sqls as $sql){
            $sql=trim($sql);
            if($sql){
                Db::execute($sql);
            }
        }
    }
}