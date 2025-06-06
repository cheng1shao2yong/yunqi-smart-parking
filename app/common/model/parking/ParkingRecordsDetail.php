<?php
declare(strict_types=1);

namespace app\common\model\parking;

use think\Model;

class ParkingRecordsDetail extends Model
{
    protected $type = [
        'start_time'     =>  'timestamp:Y-m-d H:i:s',
        'end_time'      =>  'timestamp:Y-m-d H:i:s',
    ];
}
