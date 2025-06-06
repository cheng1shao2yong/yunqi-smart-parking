<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\Admin;
use think\Model;

class ParkingBlack Extends Model
{
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';

    protected $type = [
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    public function admin()
    {
        return $this->hasOne(Admin::class,'id','admin_id')->field('id,nickname');
    }

}
