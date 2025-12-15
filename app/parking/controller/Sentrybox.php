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

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingSentryboxOperate;
use app\parking\traits\Actions;
use app\common\model\parking\ParkingMerchant;
use app\common\controller\ParkingBase;
use app\common\model\parking\ParkingSentrybox;
use app\common\model\parking\ParkingBarrier;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("sentrybox")]
class Sentrybox extends ParkingBase
{
    use Actions{
        add as _add;
        edit as _edit;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingSentrybox();
        if($this->parking->property_id){
            $parking_id=Parking::where('property_id',$this->parking->property_id)->column('id');
        }else{
            $parking_id=[$this->parking->id];
        }
        $this->assign('merch',ParkingMerchant::where('parking_id',$this->parking->id)->column('merch_name','id'));
        $this->assign('barrier',ParkingBarrier::where(['pid'=>0])->whereIn('parking_id',$parking_id)->column('title','id'));
        $this->postParams['parking_id']=$this->parking->id;
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with($with)
            ->where($where)
            ->order($order)
            ->paginate($limit)
            ->each(function($item){
                $item->webversion=get_domain('screen').'/login?uniqid='. $item->uniqid;
            });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if ($this->request->isPost()) {
            $remark=$this->request->post('row.remark');
            $operator=$this->request->post('row.operator');
            if($remark){
                $this->postParams['remark']=json_encode($remark,JSON_UNESCAPED_UNICODE);
            }
            if($operator){
                $this->postParams['operator']=json_encode($operator,JSON_UNESCAPED_UNICODE);
            }
            $this->postParams['parking_id']=$this->parking->id;
            $this->postParams['uniqid']=uniqid();
        }
        return $this->_add();
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if ($this->request->isPost()) {
            $remark=$this->request->post('row.remark');
            $operator=$this->request->post('row.operator');
            $this->postParams['remark']=null;
            $this->postParams['operator']=null;
            if($remark){
                $this->postParams['remark']=json_encode($remark,JSON_UNESCAPED_UNICODE);
            }
            if($operator){
                $this->postParams['operator']=json_encode($operator,JSON_UNESCAPED_UNICODE);
            }
        }
        return $this->_edit();
    }

    #[Route('GET,JSON','operate')]
    public function operate()
    {
        if (false === $this->request->isAjax()) {
            $this->assign('sentrybox',ParkingSentrybox::where(['parking_id'=>$this->parking->id])->column('title','id'));
            return $this->fetch();
        }
        $this->model=new ParkingSentryboxOperate();
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['sentrybox'])
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }
}
