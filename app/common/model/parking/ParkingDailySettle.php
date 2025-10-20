<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\manage\Parking;
use think\Model;
use think\model\concern\SoftDelete;

class ParkingDailySettle extends Model
{
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';

    protected $type = [
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];
    public function parking()
    {
        return $this->belongsTo(Parking::class,'parking_id','id')->field('id,title,sub_merch_no');
    }
}
