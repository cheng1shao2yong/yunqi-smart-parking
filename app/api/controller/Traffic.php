<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingTraffic;
use app\common\service\barrier\Saifeimu;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("traffic")]
class Traffic extends Api
{
    protected $noNeedLogin = ['*'];

    #[Route('GET','show')]
    public function show()
    {
        $uniqid=$this->request->get('uniqid');
        $parking=Parking::where('uniqid',$uniqid)->find();
        $traffic=ParkingTraffic::where(['parking_id'=>$parking->id])->find();
        $str=<<<EOF
        <html>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <body>
        <div style="font-size: 32px;text-align: center;padding-top: 150px;">{$parking->title}</div>
        <div style="font-size: 24px;text-align: center;padding-top: 40px;">车位总数：{$traffic->total_parking_number}</div>
        <div style="font-size: 24px;text-align: center;padding-top: 40px;">开放车位数：{$traffic->open_parking_number}</div>
        <div style="font-size: 24px;text-align: center;padding-top: 40px;">保留车位数：{$traffic->reserved_parking_number}</div>
        <div style="font-size: 24px;text-align: center;padding-top: 40px;">空余车位数：{$traffic->remain_parking_number}</div>
        </body>
        </html>
EOF;
        return $str;
    }
}
