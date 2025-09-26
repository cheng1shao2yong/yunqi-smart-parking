<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\ConstTraits;
use think\Model;

class ParkingMode extends Model
{
    use ConstTraits;

    protected $append=['time_setting_date','time_setting_period','time_setting_week','time_setting_month'];

    const PLATETYPE=[
        'blue'=>'蓝牌',
        'green'=>'绿牌',
        'yellow'=>'黄牌',
        'yellow-green'=>'黄绿牌',
        'black'=>'黑牌',
        'white'=>'白牌',
    ];

    const SPECIAL=[
        'police'=>'警车',
        'armed_police'=>'武警车',
        'military'=>'军车',
        'emergency'=>'应急车',
        'shiguan'=>'使馆车',
        'school'=>'校车',
        'coach'=>'教练车'
    ];

    const FEESETTING=[
        'free'=>'免费',
        'normal'=>'标准收费',
        'period'=>'时段收费',
        'loop'=>'周期收费',
        'step'=>'阶梯收费',
        'diy'=>'定制收费'
    ];

    const TIMESETTING=[
        'all'=>'所有日期',
        'date'=>'指定固定日期',
        'period'=>'指定范围日期',
        'week'=>'指定每周日期',
        'month'=>'指定每月日期'
    ];

    public function getStartFeeAttr($data)
    {
        if($data){
            return json_decode($data,true);
        }
        return null;
    }

    public function getPlateTypeAttr($data)
    {
        if(!$data){
            return [];
        }
        return explode(',',$data);
    }

    public function getSpecialAttr($data)
    {
        if(!$data){
            return [];
        }
        return explode(',',$data);
    }

    public function getPeriodFeeAttr($data)
    {
        if($data){
            return json_decode($data,true);
        }
        return null;
    }

    public function getStepFeeAttr($data)
    {
        if($data){
            return json_decode($data,true);
        }
        return null;
    }

    public function getTimeSettingDateAttr($data,$row)
    {
        if(!isset($row['time_setting'])){
            return null;
        }
        if($row['time_setting']=='date'){
            return $row['time_setting_rules'];
        }
        return null;
    }


    public function getTimeSettingPeriodAttr($data,$row)
    {
        if(!isset($row['time_setting'])){
            return null;
        }
        if ($row['time_setting'] == 'period') {
            return $row['time_setting_rules'];
        }
        return null;
    }


    public function getTimeSettingWeekAttr($data,$row)
    {
        if(!isset($row['time_setting'])){
            return null;
        }
        if ($row['time_setting'] == 'week') {
            return $row['time_setting_rules'];
        }
        return null;
    }


    public function getTimeSettingMonthAttr($data,$row)
    {
        if(!isset($row['time_setting'])){
            return null;
        }
        if ($row['time_setting'] == 'month') {
            return $row['time_setting_rules'];
        }
        return null;
    }

    public static function getDiyMode()
    {
        $files=glob(root_path().'/app/common/service/diymode/*.php');
        $list=[];
        foreach ($files as $file){
            $name=basename($file,'.php');
            if($name=='ParkingDiyMode'){
                continue;
            }
            $class='\\app\\common\\service\\diymode\\'.$name;
            $list[$class]=(new $class())->title;
        }
        return $list;
    }
}
