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
        $date=date('Y-m-d',time());
        $sql="SELECT parking_id,date,net_income FROM parking_daily_cash_flow where date='{$date}' and parking_id in (SELECT id FROM yun_parking where pay_type_handle='dougong')";
        $list=Db::query($sql);
        $dougong=PayService::newInstance(['pay_type_handle'=>'dougong']);
        foreach ($list as $item){
            if($item['parking_id']==1){
                continue;
            }
            $parking=Parking::find($item['parking_id']);
            $dougong->settle($parking,$date,floatval($item['net_income']));
            sleep(1);
        }
    }
}