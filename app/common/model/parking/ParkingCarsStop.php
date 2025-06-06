<?php
declare(strict_types=1);

namespace app\common\model\parking;

use think\Model;

class ParkingCarsStop extends Model
{
    protected $append=[
        'begin_time',
        'end_time'
    ];

    public function getBeginTimeAttr($value,$data)
    {
        $nextDayMidnight = strtotime('tomorrow', $data['createtime']);
        return $nextDayMidnight;
    }

    public function getEndTimeAttr($value,$data)
    {
        return strtotime($data['date'].' 23:59:59');
    }

    public function delete():bool
    {
        $this->save(['status'=>1]);
        return true;
    }
}
