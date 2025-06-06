<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\ConstTraits;
use app\common\model\manage\Parking;
use think\Model;

class ParkingRules extends Model
{
    use ConstTraits;

    const RULESTYPE=[
        'provisional'=>'临时车',
        'monthly'=>'月租车',
        'day'=>'日租车',
        'member'=>'会员车',
        'stored'=>'储值车',
        'vip'=>'VIP车'
    ];

    public static function getRulesType(Parking $parking)
    {
        $r=[];
        $setting=$parking->setting;
        foreach (self::RULESTYPE as $k=>$v){
            if($setting[$k]){
                $r[$k]=$v;
            }
        }
        if(count($r)>0){
            return $r;
        }
        return self::RULESTYPE;
    }

    public static function getRulesDefaultType(Parking $parking)
    {
        $setting=$parking->setting;
        foreach (self::RULESTYPE as $k=>$v){
            if($setting[$k]){
                return $k;
            }
        }
        return 'provisional';
    }

    public static function onAfterInsert($rule)
    {
        $rule->weigh=1000-$rule->id;
        $rule->save();
    }

    public function provisionalmode()
    {
        return $this->hasOne(ParkingMode::class,'id','mode_id');
    }

    public function getModeAttr($data)
    {
        if($data){
            return json_decode($data,true);
        }
        return null;
    }

    public function getGiftsAttr($data)
    {
        if($data){
            return json_decode($data,true);
        }
        return null;
    }

    public function getOnlineApplyRemarkAttr($data)
    {
        if($data && is_string($data)){
            return json_decode($data,true);
        }
        if($data){
            return $data;
        }
        return null;
    }

    public function getOnlineRenewRemarkAttr($data)
    {
        if($data && is_string($data)){
            return json_decode($data,true);
        }
        if($data){
            return $data;
        }
        return null;
    }

    public function getRemarkListAttr($data)
    {
        if($data && is_string($data)){
            return json_decode($data,true);
        }
        if($data){
            return $data;
        }
        return null;
    }

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id');
    }
}
