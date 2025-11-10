<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingSetting;
use app\common\model\parking\ParkingTraffic;
use app\common\model\parking\ParkingTrafficRecords;
use app\common\service\barrier\Utils;
use think\annotation\route\Group;
use app\admin\traits\Actions;
use think\annotation\route\Route;

#[Group("traffic")]
class Traffic extends Backend
{

    use Actions{
        add as _add;
        del as _del;
        multi as _multi;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = new ParkingTraffic();
        $this->relationField=['parking'];
        $this->assign('area',ParkingTraffic::AREA);
        $this->assign('parkingType',ParkingTraffic::PARKING_TYPE);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $apihost=get_domain('api');
        $list = $this->model
            ->withJoin($with)
            ->where($where)
            ->order('status asc,id desc')
            ->paginate($limit)
            ->each(function ($item) use ($apihost){
                 $item->link=$apihost.'/traffic/show?uniqid='.$item->parking->uniqid;
            });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,JSON','orders')]
    public function orders()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[['parking_id','=',$this->request->get('ids')]];
        $this->model=new ParkingTrafficRecords();
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['records'])
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
            $parking_id=$this->request->post('row.parking_id');
            $count=ParkingTraffic::where('parking_id',$parking_id)->count();
            if($count>0){
                return $this->error('该停车场已存在');
            }
            $open_parking_number=$this->request->post('row.open_parking_number');
            $parking_space_entry=ParkingRecords::where(['parking_id'=>$parking_id,'rules_type'=>'provisional'])->whereIn('status',[0,1])->count();
            $remain_parking_number=$open_parking_number-$parking_space_entry;
            $this->postParams['remain_parking_number']=$remain_parking_number>0?$remain_parking_number:0;
            $this->callback=function () use ($parking_id){
                ParkingSetting::where('parking_id',$parking_id)->update(['push_traffic'=>1]);
            };
        }
        return $this->_add();
    }

    #[Route('GET,POST','del')]
    public function del()
    {
        $ids=$this->request->param('ids');
        $traffic=ParkingTraffic::find($ids);
        ParkingSetting::where('parking_id',$traffic->parking_id)->update(['push_traffic'=>0]);
        $traffic->delete();
        $this->success();
    }

    #[Route('POST,GET','multi')]
    public function multi()
    {
        $ids = $this->request->param('ids');
        $field = $this->request->param('field');
        $value = $this->request->param('value');
        $traffic=ParkingTraffic::where('id',$ids[0])->find();
        if($field=='status' && $value=='normal'){
            $parking_space_entry=ParkingRecords::where(['parking_id'=>$traffic->parking_id,'rules_type'=>'provisional'])->whereIn('status',[0,1])->count();
            $open_parking_number=$traffic->open_parking_number;
            $remain_parking_number=$open_parking_number-$parking_space_entry;
            $traffic->remain_parking_number=$remain_parking_number>0?$remain_parking_number:0;
            $traffic->status='normal';
            $traffic->save();
            ParkingSetting::where('parking_id',$traffic->parking_id)->update(['push_traffic'=>1]);
        }
        if($field=='status' && $value=='hidden'){
            $traffic->status='hidden';
            $traffic->save();
            ParkingSetting::where('parking_id',$traffic->parking_id)->update(['push_traffic'=>0]);
        }
        $this->success();
    }
}