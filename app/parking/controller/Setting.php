<?php
/**
 * ----------------------------------------------------------------------------
 * 行到水穷处，坐看云起时
 * 开发软件，找贵阳云起信息科技，官网地址:https://www.56q7.com/
 * ----------------------------------------------------------------------------
 * Author: 老成
 * email：85556713@qq.com
 */
declare (strict_types = 1);

namespace app\parking\controller;

use app\common\controller\ParkingBase;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingSetting;
use app\common\service\barrier\Zhenshi;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\facade\Cache;
use think\facade\Session;

#[Group("setting")]
class Setting extends ParkingBase
{
    private $setting;

    public function _initialize()
    {
        parent::_initialize();
        $this->setting=ParkingSetting::where(['parking_id'=>$this->parking->id])->find();
        $this->assign('rules_type',ParkingRules::RULESTYPE);
        $this->assign('special',ParkingMode::SPECIAL);
        $this->assign('entry_tips',Zhenshi::MESSAGE_ENTRY);
        $this->assign('exit_tips',Zhenshi::MESSAGE_EXIT);
    }

    #[Route('GET,POST','index')]
    public function index()
    {
        $this->assign('setting',$this->setting);
        return $this->fetch();
    }

    #[Route('POST','update')]
    public function update()
    {
        $field = $this->request->param('field');
        $value = $this->request->param('value');
        if($field=='special_free'){
            $value=implode(',',$value);
        }
        $this->setting->save([$field=>$value]);
        $parking=Parking::withJoin(['setting'])->find($this->parking->id);
        Session::set('parking.parkingModel',$parking);
        Session::save();
        $this->success('修改成功');
    }

    #[Route('POST','clearCache')]
    public function clearCache()
    {
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id])->select();
        foreach ($barrier as $item){
            Cache::delete('parking_barrier_'.$item->serialno);
        }
        Cache::delete('parking_rules_'.$this->parking->id);
        Cache::delete('parking_infield_'.$this->parking->id);
        Cache::delete('parking_mode_'.$this->parking->id);
        Cache::delete('parking_coupon_'.$this->parking->id);
        Cache::delete('parking_charge_'.$this->parking->id);
        Cache::delete('parking_'.$this->parking->id);
        $this->success('操作成功');
    }
}
