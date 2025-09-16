<?php
declare(strict_types=1);

namespace app\common\service\diymode;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingMode;

abstract class ParkingDiyMode{
    //自定义收费名称
    public string $title='';
    //免费时长
    public int $free_time=30;
    //每日封顶收费
    public float $day_top_fee=100;
    //收费明细
    protected array $detail=[];
    //超出收费计算的时间
    protected int $rangetime=0;

    //获取收费金额
    abstract public function account(ParkingMode $mode,int $key,int $starttime,int $endtime,int &$rangeTime);
    public function getDetail():array
    {
        return $this->detail;
    }
}