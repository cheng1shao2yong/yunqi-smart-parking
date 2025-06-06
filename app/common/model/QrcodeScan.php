<?php

namespace app\common\model;

use think\Model;

/**
 * 二维码扫码记录模型
 */
class QrcodeScan extends Model
{
    public function third()
    {
        return $this->hasOne(Third::class,'openid','openid');
    }

    public function qrcode()
    {
        return $this->belongsTo(Qrcode::class,'qrcode_id','id');
    }
}
