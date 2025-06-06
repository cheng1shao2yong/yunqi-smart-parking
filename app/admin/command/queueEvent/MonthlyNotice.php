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

//月租到期通知

use app\common\service\msg\WechatMsg;
use think\facade\Db;

class MonthlyNotice implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $today=strtotime(date('Y-m-d 23:59:59',time()));
        $timequ=[$today,$today+24*3600,$today+2*24*3600,$today+3*24*3600,$today+4*24*3600,$today+5*24*3600,$today+6*24*3600];
        $timequ=implode(',',$timequ);
        $sql="
            SELECT t4.parking_id,t4.parking_title,t4.plate_number,t4.endtime,t4.notice,yun_mp_subscribe.openid FROM
            (
            SELECT t2.plate_number,t1.parking_id,t1.parking_title,t1.endtime,t1.notice,t3.user_id FROM
            (
            SELECT yun_parking.id as parking_id,yun_parking.title as parking_title,yun_parking_cars.id as cars_id,yun_parking_cars.endtime,yun_parking_rules.notice notice FROM yun_parking_cars 
            LEFT JOIN yun_parking_rules on yun_parking_cars.rules_id=yun_parking_rules.id
            LEFT JOIN yun_parking on yun_parking.id=yun_parking_cars.parking_id
            where yun_parking_cars.rules_type='monthly' and yun_parking_cars.endtime in ({$timequ})
            )t1,yun_parking_plate t2
            RIGHT JOIN yun_plate_binding t3 on t3.plate_number=t2.plate_number and `status`=1
            RIGHT JOIN yun_user_notice on yun_user_notice.user_id=t3.user_id and yun_user_notice.monthly=1
            where t1.cars_id=t2.cars_id GROUP BY t1.cars_id
            )t4,yun_third
            RIGHT JOIN yun_mp_subscribe on yun_mp_subscribe.unionid=yun_third.unionid
            where t4.user_id=yun_third.user_id GROUP BY yun_third.user_id
        ";
        $list=Db::query($sql);
        $sendarr=[];
        foreach ($list as $item){
            if($today>=$item['endtime']-($item['notice']-1)*24*3600){
                $item['lastday']=($item['endtime']-$today)/(24*3600);
                $item['endtime']=date('Y-m-d',$item['endtime']);
                $sendarr[]=$item;
            }
        }
        WechatMsg::monthlynotice($sendarr);
    }
}