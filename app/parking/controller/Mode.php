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

use app\parking\traits\Actions;
use app\common\controller\ParkingBase;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingRules;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("mode")]
class Mode extends ParkingBase
{
    use Actions{
        add as _add;
        edit as _edit;
        del as _del;
    }

    public function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingMode();
        $this->assign('plateType',ParkingMode::PLATETYPE);
        $this->assign('special',ParkingMode::SPECIAL);
        $this->assign('feeSetting',ParkingMode::FEESETTING);
        $this->assign('timeSetting',ParkingMode::TIMESETTING);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        if($this->request->post('selectpage')){
            $where[]=['status','=','normal'];
            return $this->selectpage($where);
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $this->parseParmer();
        }
        return $this->_add();
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if($this->request->isPost()){
            $this->parseParmer();
        }
        return $this->_edit();
    }

    #[Route('GET,POST','del')]
    public function del()
    {
        $ids = $this->request->param("ids");
        $rules=ParkingRules::where(['parking_id'=>$this->parking->id])->select();
        foreach ($rules as $rule){
            if($rule->mode_id==$ids){
                $this->error('有停车规则使用了该收费规则，请先删除该停车规则');
            }
            if($rule->mode){
                 foreach ($rule->mode as $mode){
                    if($mode['mode_id']==$ids){
                        $this->error('有停车规则使用了该收费规则，请先删除该停车规则');
                    }
                }
            }
        }
        return $this->_del();
    }

    private function parseParmer()
    {
        $fee_setting=$this->request->post('row.fee_setting');
        $start_fee=$this->request->post('row.start_fee');
        $period_fee=$this->request->post('row.period_fee');
        $step_fee=$this->request->post('row.step_fee');
        $time_setting=$this->request->post('row.time_setting');
        $this->postParams['parking_id']=$this->parking->id;
        if($start_fee){
            foreach ($start_fee as $k=>$v){
               if($k>0 && $v['time']<$start_fee[$k-1]['time']){
                   $this->error('起步设置中，起步时长不能小于上一条');
               }
            }
        }
        if($fee_setting=='free'){
            $this->postParams['start_fee']=null;
            $this->postParams['period_fee']=null;
            $this->postParams['step_fee']=null;
            $this->postParams['day_top_fee']=null;
        }
        if($fee_setting=='normal'){
            $this->postParams['start_fee']=$start_fee?json_encode($start_fee):null;
            $this->postParams['period_fee']=null;
            $this->postParams['step_fee']=null;
        }
        if($fee_setting=='period'){
            $this->postParams['start_fee']=$start_fee?json_encode($start_fee):null;
            try{
                $p2=explode('-',$period_fee[count($period_fee)-1]['period'])[1];
                foreach ($period_fee as $value){
                    $period=explode('-',$value['period']);
                    if($p2!=$period[0]){
                        $this->error('计费规则设置，第二个时段必须以第一个时段结束开始');
                    }
                    $p2=$period[1];
                }
            }catch (\Exception $e){
                $this->error('计费规则设置不正确');
            }
            $this->postParams['period_fee']=json_encode($period_fee);
            $this->postParams['step_fee']=null;
        }
        if($fee_setting=='loop'){
            $this->postParams['start_fee']=$start_fee?json_encode($start_fee):null;
            $this->postParams['period_fee']=null;
            $this->postParams['step_fee']=null;
        }
        if($fee_setting=='step'){
            foreach ($step_fee as $k=>$v){
                if($k>0 && $v['time']<$step_fee[$k-1]['time']){
                    $this->error('阶梯设置中，后面一个阶梯的时间不能小于上一个阶梯');
                }
            }
            $this->postParams['start_fee']=$start_fee?json_encode($start_fee):null;
            $this->postParams['period_fee']=null;
            $this->postParams['step_fee']=json_encode($step_fee);
        }
        if($time_setting!='all'){
            $this->postParams['time_setting_rules']=$this->request->post('row.time_setting_'.$time_setting);
        }
    }
}
