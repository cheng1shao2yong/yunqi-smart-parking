<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\ConstTraits;
use app\common\model\PayUnion;
use think\Model;

class ParkingMerchantLog extends Model
{
    use ConstTraits;

    const LOGTYPE=[
        'admin'=>'管理员充值',
        'recharge'=>'用户充值',
        'records'=>'停车消费'
    ];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';

    protected $type = [
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    protected $append=['log_type_txt'];

    public function payunion()
    {
        return $this->hasOne(PayUnion::class,'id','pay_id')->field('id,transaction_id,pay_type,pay_price,order_type');
    }

    public function records()
    {
        return $this->hasOne(ParkingRecords::class,'id','records_id');
    }

    public function merch()
    {
        return $this->hasOne(ParkingMerchant::class,'id','merch_id')->field('id,merch_name,price,settle_type');
    }

    public function getLogTypeTxtAttr($value,$data)
    {
        return isset(self::LOGTYPE[$data['log_type']])?self::LOGTYPE[$data['log_type']]:'';
    }

    public static function addAdminLog(ParkingMerchant $merch,$change_type,$change,$remark,$pay_id)
    {
        self::addLog('admin',$merch,$change_type,$change,$remark,null,$pay_id);
    }

    public static function addRechargeLog(ParkingMerchant $merch,$change,$payunion_id)
    {
        self::addLog('recharge',$merch,'add',$change,'商户在线充值',null,$payunion_id);
    }

    public static function addRecordsLog(ParkingMerchant $merch,$records_id,$change,$remark)
    {
        self::addLog('records',$merch,'minus',$change,$remark,$records_id);
    }

    private static function addLog($log_type,ParkingMerchant $merch,$change_type,$change,$remark,$records_id=null,$pay_id=null)
    {
        $logtxt=[
            'add'=>'充值余额',
            'last'=>'余额变动',
            'minus'=>'扣款余额',
        ];
        $logremark=$logtxt[$change_type];
        $before=(string)$merch->balance;
        $after='';
        $scale=2;
        switch ($change_type){
            case 'add':
                if($merch->discount && $merch->discount>0 && $merch->discount<10){
                    $change=bcdiv((string)$change,(string)($merch->discount/10),$scale);
                    $logremark=$merch->discount."折充值余额";
                }
                $after=bcadd($before,(string)$change,$scale);
                break;
            case 'minus':
                $after=bcsub($before,(string)$change,$scale);
                break;
            case 'last':
                $after=$change;
                if($after<0){
                    throw new \Exception('余额不能小于0');
                }
                break;
        }
        if($remark){
            $logremark.="，".$remark;
        }
        $log=new self();
        $log->parking_id=$merch->parking_id;
        $log->merch_id=$merch->id;
        $log->log_type=$log_type;
        $log->before=$before;
        $log->change=$change;
        $log->after=$after;
        $log->pay_id=$pay_id;
        $log->records_id=$records_id;
        $log->remark=$logremark;
        $log->save();
        $merch->balance=$after;
        $merch->save();
    }
}