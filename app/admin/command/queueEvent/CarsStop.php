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

use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingCarsStop;

//处理月卡报停恢复
class CarsStop implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $date=date('Y-m-d',time());
        $stops=ParkingCarsStop::where(['date'=>$date,'status'=>0])->select();
        foreach ($stops as $stop){
            /* @var ParkingCars $cars */
            $cars=ParkingCars::find($stop->cars_id);
            if($cars){
                $cars->restart($stop);
            }
        }
    }
}