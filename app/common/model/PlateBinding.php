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

namespace app\common\model;

use think\Model;

class PlateBinding Extends Model
{
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;

    protected $type = [
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    public function user()
    {
        return $this->hasOne(User::class,'id','user_id');
    }

    public static function getUserPlate(int $user_id,string $action=null):Array
    {
        $plate_number=self::where(['user_id'=>$user_id,'status'=>1])->column('plate_number');
        return $plate_number;
    }
}
