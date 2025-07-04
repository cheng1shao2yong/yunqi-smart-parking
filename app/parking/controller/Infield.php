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
use app\common\model\parking\ParkingInfield;
use app\common\model\parking\ParkingMode;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("infield")]
class Infield extends ParkingBase
{
    use Actions{
        add as _add;
        edit as _edit;
        del as _del;
    }

    public function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingInfield();
        $this->assign('entry_barrier',ParkingBarrier::where(['parking_id'=>$this->parking->id,'barrier_type'=>'entry'])->column('title','id'));
        $this->assign('exit_barrier',ParkingBarrier::where(['parking_id'=>$this->parking->id,'barrier_type'=>'exit'])->column('title','id'));
        $this->assign('parking_mode',ParkingMode::where('parking_id',$this->parking->id)->column('title','id'));
        $this->assign('rules',ParkingInfield::RULES);
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
            $this->postParams['parking_id']=$this->parking->id;
            $rules = $this->request->post('row.rules');
            if($rules=='diy'){
                $mode = $this->request->post('row.mode');
                if(!$mode){
                   $this->error('收费规则不能为空');
                }
                $this->postParams['mode'] = json_encode($mode);
            }else{
                $this->postParams['mode'] = null;
            }
            $this->callback=function (){
                $this->callback();
            };
        }
        return $this->_add();
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if($this->request->isPost()){
            $this->postParams['parking_id']=$this->parking->id;
            $rules = $this->request->post('row.rules');
            if($rules=='diy'){
                $mode = $this->request->post('row.mode');
                if(!$mode){
                    $this->error('收费规则不能为空');
                }
                $this->postParams['mode'] = json_encode($mode);
            }else{
                $this->postParams['mode'] = null;
            }
            $this->callback=function (){
                $this->callback();
            };
        }
        $row=$this->model->where(['id'=>$this->request->param('ids'),'parking_id'=>$this->parking->id])->find();
        if($row->mode){
            $row->mode=json_decode($row->mode);
        }
        return $this->_edit($row);
    }

    #[Route('GET,POST','del')]
    public function del()
    {
        $this->callback=function (){
            $this->callback();
        };
        return $this->_del();
    }

    private function callback()
    {
        $list=ParkingBarrier::where(['parking_id'=>$this->parking->id])->select();
        $barriers=[];
        $infield=ParkingInfield::where(['parking_id'=>$this->parking->id])->select();
        foreach ($infield as $value){
            $barriers=array_merge($barriers,$value->entry_barrier,$value->exit_barrier);
        }
        foreach ($list as $barrier){
            if(in_array($barrier->id,$barriers)){
                $barrier->trigger_type='infield';
            }else{
                $barrier->trigger_type='outfield';
            }
            $barrier->save();
        }
    }
}
