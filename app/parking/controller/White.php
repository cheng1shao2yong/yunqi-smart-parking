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
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingWhite;
use app\common\service\barrier\Utils;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("white")]
class White extends ParkingBase
{
    public function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingCars();
        $this->assign('rules_type',ParkingCars::getRulesType($this->parking));
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        $plates=$this->filter('plates');
        $rangetime=$this->filter('rangetime');
        $synch=$this->filter('synch');
        if($plates){
            $ids=ParkingPlate::where('parking_id',$this->parking->id)->whereLike('plate_number','%'.$plates.'%')->column('cars_id');
            $where[]=['id','in',$ids];
        }
        if($rangetime){
            $starttime=strtotime($rangetime[0]);
            $endtime=strtotime($rangetime[1]);
            $where[]=['starttime','>=',$starttime];
            $where[]=['endtime','<=',$endtime];
        }
        if($synch){
            if($synch==1){
                $where[]=["CONCAT(starttime,',',endtime)=synch"];
            }
            if($synch==2){
                $where[]=["synch is null or CONCAT(starttime,',',endtime)<>synch"];
            }
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['plates'=>function ($query) {
                $query->limit(1);
            }])
            ->where($where)
            ->order($order)
            ->paginate($limit)
            ->each(function ($res){
                if($res->synch==$res->starttime.','.$res->endtime){
                    $res->synch=1;
                }else{
                    $res->synch=2;
                }
            });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('POST','del')]
    public function del(){
        $ids=$this->request->post('ids');
        $barriers=ParkingBarrier::where(['parking_id'=>$this->parking->id])->select();
        $cars=ParkingCars::where('id','in',$ids)->where(['parking_id'=>$this->parking->id])->select();
        $prefix=getDbPrefix();
        foreach ($barriers as $barrier){
            Utils::send($barrier,'离线白名单',['cars'=>$cars,'action'=>'delete']);
            $ids=implode(',',$ids);
            $sql="update {$prefix}parking_cars set synch=null where parking_id={$this->parking->id} and id in ({$ids})";
            Db::execute($sql);
        }
        $this->success();
    }

    #[Route('POST','synch')]
    public function synch(){
        $ids=$this->request->post('ids');
        $barriers=ParkingBarrier::where(['parking_id'=>$this->parking->id,'status'=>'normal','pid'=>0])->select();
        $cars=ParkingCars::where('id','in',$ids)->where(['parking_id'=>$this->parking->id])->select();
        foreach ($barriers as $barrier){
            Utils::send($barrier,'离线白名单',['cars'=>$cars,'action'=>'update_or_add']);
        }
        $prefix=getDbPrefix();
        $ids=implode(',',$ids);
        $sql="update {$prefix}parking_cars set synch=CONCAT(starttime,',',endtime) where parking_id={$this->parking->id} and id in ({$ids})";
        Db::execute($sql);
        $this->success();
    }

    #[Route('POST,GET','timer')]
    public function timer(){
        if($this->request->isPost()){
            $row=ParkingWhite::where('parking_id',$this->parking->id)->find();
            if(!$row){
                $row=new ParkingWhite();
                $row->parking_id=$this->parking->id;
            }
            $row->time=$this->request->post('row.time');
            $row->day=$this->request->post('row.day');
            $row->rules_id=implode(',',$this->request->post('row.rules_id'));
            $row->save();
            $this->success();
        }
        $row=ParkingWhite::where('parking_id',$this->parking->id)->find();
        if(!$row){
            $row=new ParkingWhite();
            $row->time=3;
            $row->day=1;
        }
        $this->assign('row',$row);
        $this->assign('rules',ParkingRules::where(['parking_id'=>$this->parking->id])->where('rules_type','<>','provisional')->column('title', 'id'));
        return $this->fetch();
    }
}
