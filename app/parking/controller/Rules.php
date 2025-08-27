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
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingRules;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("rules")]
class Rules extends ParkingBase
{

    use Actions{
        add as _add;
        edit as _edit;
        del as _del;
    }

    private $rules_type;

    public function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingRules();
        $this->rules_type=$this->request->get('rules_type',ParkingRules::getRulesDefaultType($this->parking));
        $this->assign('rules_type_tabs',ParkingRules::getRulesType($this->parking));
        $this->assign('rules_type_value',$this->rules_type);
        $this->assign('parking_mode',ParkingMode::where('parking_id',$this->parking->id)->column('title','id'));
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        $where[]=['rules_type','=',$this->rules_type];
        if($this->request->post('selectpage')){
            $where[]=['status','=','normal'];
            return $this->selectpage($where);
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['provisionalmode'])
            ->where($where)
            ->order('weigh desc')
            ->select();
        $result = ['total' => 100, 'rows' => $list];
        return json($result);
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $this->postParams=[
                'parking_id'=>$this->parking->id,
                'rules_type'=>$this->rules_type,
            ];
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
        $count=ParkingCars::where(['rules_id'=>$ids,'parking_id'=>$this->parking->id])->count();
        if($count>0){
            $this->error('有车辆使用了该停车规则，请先删除该车辆');
        }
        $barrier=ParkingBarrier::where('parking_id',$this->parking->id)->select();
        foreach ($barrier as $value){
            if(in_array($ids,$value->rules_id)){
                $this->error('有道闸使用了该停车规则，请先删除');
            }
        }
        return $this->_del();
    }

    private function parseParmer()
    {
        if($this->rules_type=='provisional'){
            $time_limit_entry=$this->request->post('row.time_limit_entry');
            $time_limit_setting=$this->request->post('row.time_limit_setting');
            if($time_limit_entry){
                $this->postParams['time_limit_setting']=json_encode($time_limit_setting,JSON_UNESCAPED_UNICODE);
            }else{
                $this->postParams['time_limit_setting']=null;
            }
        }
        if($this->rules_type=='monthly' || $this->rules_type=='day' ||  $this->rules_type=='stored'){
            $mode=$this->request->post('row.mode');
            $gifts=$this->request->post('row.gifts');
            $auto_online_apply=$this->request->post('row.auto_online_apply');
            $auto_online_renew=$this->request->post('row.auto_online_renew');
            $online_apply_remark=$this->request->post('row.online_apply_remark');
            $online_renew_remark=$this->request->post('row.online_renew_remark');
            $remark_list=$this->request->post('row.remark_list');
            if(!$mode){
                $this->error('收费规则不能为空');
            }
            foreach ($mode as $value){
                if(!$value['mode_id']){
                    $this->error('请选择收费规则');
                }
            }
            $this->postParams['mode']=json_encode($mode);
            if($gifts){
                $this->postParams['gifts']=json_encode($gifts);
            }
            $this->postParams['online_apply_remark']=null;
            $this->postParams['online_renew_remark']=null;
            if($auto_online_apply=='no' && $online_apply_remark){
                $this->postParams['online_apply_remark']=json_encode($online_apply_remark,JSON_UNESCAPED_UNICODE);
            }
            if($auto_online_renew=='no' && $online_renew_remark){
                $this->postParams['online_renew_remark']=json_encode($online_renew_remark,JSON_UNESCAPED_UNICODE);
            }
            if($remark_list){
                $this->postParams['remark_list']=json_encode($remark_list,JSON_UNESCAPED_UNICODE);
            }
        }
        if($this->rules_type=='member' || $this->rules_type=='vip'){
            $mode=$this->request->post('row.mode');
            $remark_list=$this->request->post('row.remark_list');
            if(!$mode){
                $this->error('收费规则不能为空');
            }
            foreach ($mode as $value){
                if(!$value['mode_id']){
                    $this->error('请选择收费规则');
                }
            }
            $this->postParams['mode']=json_encode($mode);
            if($remark_list){
                $this->postParams['remark_list']=json_encode($remark_list,JSON_UNESCAPED_UNICODE);
            }
        }
    }
}
