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

class DailiLog Extends Model
{
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;

    protected $type = [
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    public function parking()
    {
        return $this->belongsTo(Parking::class,'remark','id')->field('id,title');
    }

    public static function settle(Daili $daili,string $date)
    {
        $log=DailiLog::where(['daili_id'=>$daili->id,'date'=>$date,'change_type'=>'add'])->find();
        if($log){
            return;
        }
        $starttime=$date.' 00:00:00';
        $endtime=$date.' 23:59:59';
        $parking=DailiParking::where(['daili_id'=>$daili->id])->field('parking_id,persent')->select();
        $balance=$daili->balance;
        $now=time();
        $result=[];
        $prefix=getDbPrefix();
        foreach ($parking as $park){
            $pay_price=PayUnion::where(['parking_id'=>$park->parking_id,'pay_status'=>1,'refund_price'=>null])
            ->whereBetween('pay_time',[$starttime,$endtime])
            ->whereRaw("pay_type<>'underline' and pay_type<>'stored' and id not in (select pay_id from {$prefix}parking_records_filter where parking_id={$park->parking_id})")
            ->sum('pay_price');
            $payprice=(string)($pay_price*100);
            $persent=bcdiv((string)$park->persent,'1000',4);
            $handling_fees=bcmul($payprice,$persent,1);
            $handling_fees=round(floatval($handling_fees));
            if($handling_fees>0){
                $handling_fees=$handling_fees/100;
                $result[]=[
                    'daili_id'=>$daili->id,
                    'change_type'=>'add',
                    'change'=>$handling_fees,
                    'before'=>$balance,
                    'after'=>$balance+$handling_fees,
                    'remark'=>$park->parking_id,
                    'date'=>$date,
                    'createtime'=>$now
                ];
                $balance=$balance+$handling_fees;
            }
        }
        DailiLog::insertAll($result);
        $daili->balance=$balance;
        $daili->save();
    }
}
