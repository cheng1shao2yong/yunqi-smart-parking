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

namespace app\common\model\parking;


use app\common\model\PayUnion;
use app\common\model\User;
use think\Model;

class ParkingStoredLog Extends Model
{
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;

    protected $append = ['log_type_txt'];

    const LOGTYPE=[
        'admin'=>'管理员充值',
        'recharge'=>'用户充值',
        'records'=>'停车消费',
    ];

    protected $type = [
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    public function payunion()
    {
        return $this->hasOne(PayUnion::class,'id','pay_id')->field('id,out_trade_no,pay_type,pay_price,order_type');
    }

    public function plate()
    {
        return $this->hasMany(ParkingPlate::class,'cars_id','cars_id');
    }


    public function records()
    {
        return $this->hasOne(ParkingRecords::class,'id','records_id');
    }

    public function getLogTypeTxtAttr($data,$row)
    {
        $type=$row['log_type'];
        return isset(self::LOGTYPE[$type])?self::LOGTYPE[$type]:'';
    }

    public static function addAdminLog(ParkingCars $cars,$change_type,$change,$remark)
    {
        $payunion=null;
        if($change_type=='add'){
            $payunion=PayUnion::underline(
                $change,
                PayUnion::ORDER_TYPE('停车储值卡充值'),
                ['parking_id'=>$cars->parking_id],
                $cars->plates[0]->plate_number.'储值卡充值'
            );
        }
        self::addLog('admin',$cars,$change_type,$change,$remark,$payunion);
    }

    public static function addRechargeLog(ParkingCars $cars,$payunion)
    {
        self::addLog('recharge',$cars,'add',$payunion->pay_price,'用户在线充值',$payunion);
    }

    public static function addRecordsLog(ParkingCars $cars,$change,$remark)
    {
        self::addLog('records',$cars,'minus',$change,$remark);
    }

    private static function addLog($log_type,ParkingCars $cars,$change_type,$change,$remark,$payunion=null)
    {
        if(!$cars || $cars->rules_type!='stored'){
            throw new \Exception('储值卡不存在');
        }
        $logtxt=[
            'add'=>'充值余额',
            'last'=>'余额变动',
            'minus'=>'扣款余额',
        ];
        $logremark=$logtxt[$change_type];
        if($remark){
            $logremark.="，备注：".$remark;
        }
        $before=(string)$cars->balance;
        $after='';
        $scale=2;
        switch ($change_type){
            case 'add':
                $after=bcadd($before,(string)$change,$scale);
                break;
            case 'minus':
                $after=bcsub($before,(string)$change,$scale);
                break;
            case 'last':
                $after=$change;
                break;
        }
        if($after<0){
            throw new \Exception('储值卡余额不能小于0');
        }
        $log=new self();
        $log->parking_id=$cars->parking_id;
        $log->cars_id=$cars->id;
        $log->log_type=$log_type;
        $log->before=$before;
        $log->change=$change;
        $log->after=$after;
        if($payunion){
            $log->pay_id=$payunion->id;
        }
        $log->remark=$logremark;
        $log->save();
        $cars->balance=$after;
        $cars->save();
    }
}
