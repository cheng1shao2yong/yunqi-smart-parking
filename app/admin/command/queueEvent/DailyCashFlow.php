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

use app\common\model\Daili;
use app\common\model\DailiLog;
use think\facade\Db;

class DailyCashFlow implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $prefix=getDbPrefix();
        $sql="TRUNCATE TABLE {$prefix}parking_daily_cash_flow";
        Db::execute($sql);
        $sql = "INSERT INTO {$prefix}parking_daily_cash_flow (parking_id, date, total_income, parking_income, parking_monthly_income, parking_stored_income, merch_recharge_income, handling_fees, total_refund, net_income)
               SELECT parking_id, date, total_income, parking_income, parking_monthly_income, parking_stored_income, merch_recharge_income, handling_fees, total_refund, net_income
               FROM parking_daily_cash_flow";
        Db::execute($sql);
        $dailis=Daili::where(['status'=>'normal'])->select();
        $date=date('Y-m-d',time()-24*3600);
        foreach ($dailis as $daili){
            DailiLog::settle($daili,$date);
        }
    }
}