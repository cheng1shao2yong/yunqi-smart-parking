<?php
declare (strict_types = 1);

namespace app\parking\controller;

use app\common\controller\ParkingBase;
use think\annotation\route\Group;
use app\parking\traits\Actions;
use app\common\model\parking\ParkingBlack;
use think\annotation\route\Route;

#[Group("black")]
class Black extends ParkingBase
{
    use Actions{
        add as _add;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = new ParkingBlack();
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
            ->with(['admin'])
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
            $this->postParams['admin_id']=$this->auth->id;
            $plate_number=$this->request->post('row.plate_number');
        }else{
            $plate_number=$this->request->get('plate_number','');
        }
        if($plate_number){
            $black=ParkingBlack::where(['plate_number'=>$plate_number,'parking_id'=>$this->parking->id])->find();
            if($black){
                $this->error('车牌号已存在');
            }
        }
        $this->assign('plate_number',$plate_number);
        return $this->_add();
    }
}