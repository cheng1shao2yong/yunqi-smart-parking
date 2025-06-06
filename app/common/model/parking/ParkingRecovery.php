<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\ConstTraits;
use think\Model;

class ParkingRecovery extends Model
{
    use ConstTraits;

    protected $append=['recovery_type_txt'];
    const RECOVERYTYPE=[
        'local'=>'车场追缴',
        'network'=>'集团追缴',
        'platform'=>'平台追缴'
    ];

    public function getRecoveryTypeTxtAttr($data,$row)
    {
        return self::RECOVERYTYPE[$row['recovery_type']];
    }

    public function records()
    {
        return $this->hasOne(ParkingRecords::class,'id','records_id');
    }
}
