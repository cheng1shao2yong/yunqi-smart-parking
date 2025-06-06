<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\Admin;
use think\Model;

class ParkingCarsLogs extends Model
{
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';

    protected $type = [
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    public function admin()
    {
        return $this->hasOne(Admin::class, 'id', 'admin_id')->field('id,nickname');
    }

    public function merch()
    {
        return $this->hasOne(ParkingMerchant::class, 'id', 'merch_id')->field('id,merch_name');
    }

    public static function addLog(ParkingCars $cars,int $admin_id,string $message)
    {
        (new self())->save([
            'admin_id'=>$admin_id,
            'cars_id'=>$cars->id,
            'message'=>$message,
            'parking_id'=>$cars->parking_id,
        ]);
    }

    public static function addMerchLog(ParkingCars $cars,int $merch_id,string $message)
    {
        (new self())->save([
            'merch_id'=>$merch_id,
            'cars_id'=>$cars->id,
            'message'=>$message,
            'parking_id'=>$cars->parking_id,
        ]);
    }
}
