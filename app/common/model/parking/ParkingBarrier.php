<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\ConstTraits;
use think\Model;

class ParkingBarrier extends Model
{
    use ConstTraits;
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $type = [
        'updatetime'     =>  'timestamp:Y-m-d H:i',
        'createtime'     =>  'timestamp:Y-m-d H:i',
    ];

    const CAMERA=[
        'Zhenshi'=>'成都臻识科技',
        'Saifeimu'=>'深圳赛菲姆',
    ];

    const TRIGGERTYPE=[
        'infield'=>'内场',
        'outfield'=>'外场',
        'outside'=>'外场跨内场',
        'inside'=>'内场跨外场',
    ];

    public static function findBarrierBySerialno(string $serialno,$where=[])
    {
        $barrier=self::where($where)->whereRaw("serialno='{$serialno}' or virtual_serialno='{$serialno}'")->find();
        if(!$barrier){
            return false;
        }
        if($barrier->pid && $barrier->pid>0){
            $barrier=self::find($barrier->pid);
        }
        return $barrier;
    }

    public function getPlateTypeAttr($data)
    {
        if(!$data){
            return [];
        }
        return explode(',',$data);
    }

    public function getRulesTypeAttr($data)
    {
        if(!$data){
            return [];
        }
        return explode(',',$data);
    }

    public function getRulesIdAttr($data)
    {
        if(!$data){
            return [];
        }
        return explode(',',$data);
    }

    public function fuji()
    {
        return $this->hasMany(ParkingBarrier::class,'pid','id');
    }

    public function getBarrierService()
    {
        $classname='\\app\\common\\service\\barrier\\'.$this->camera;
        $barrierService=$classname::newInstance(['barrier'=>$this],$this->serialno);
        return $barrierService;
    }

    public function isOnline()
    {
        $classname='\\app\\common\\service\\barrier\\'.$this->camera;
        return $classname::isOnline($this);
    }
}
