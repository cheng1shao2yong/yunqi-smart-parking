<?php
declare(strict_types=1);
namespace app\admin\command\queueEvent\traffic;

use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingTraffic;

interface BaseTraffic
{

    //心跳
    public function heartbeat(ParkingTraffic $traffic);

    //进场记录
    public function inrecord(ParkingTraffic $traffic,ParkingRecords $records):bool;

    //出场记录
    public function outrecord(ParkingTraffic $traffic,ParkingRecords $records):bool;

    //剩余车位情况
    public function restberth(ParkingTraffic $traffic);

    //停车规则
    public function ruleinfo(ParkingTraffic $traffic);

}