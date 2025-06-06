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

use app\common\model\manage\Parking;
use think\Model;

class DailiParking extends Model
{
    public function parking()
    {
        return $this->belongsTo(Parking::class,'parking_id','id')->field('id,title');
    }
}
