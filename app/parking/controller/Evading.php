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
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecovery;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("evading")]
class Evading extends ParkingBase
{
    protected function _initialize()
    {
        parent::_initialize();
        $this->assign('status',ParkingRecords::STATUS);
        $this->assign('recoveryType',ParkingRecovery::RECOVERYTYPE);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        $type=$this->request->get('type');
        if (false === $this->request->isAjax()) {
            $this->assign('type',$type);
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id', '=', $this->parking->id];
        $prefix=getDbPrefix();
        $raw='';
        if($type==1){
            $where[]=['status', 'in', [5,6,7]];
            $raw="id not in (select records_id from {$prefix}parking_recovery where parking_id={$this->parking->id})";
        }
        if($type==2){
            $raw="id in (select records_id from {$prefix}parking_recovery where parking_id={$this->parking->id})";
        }
        $this->model=new ParkingRecords();
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['recovery'])
            ->where($where)
            ->whereRaw($raw)
            ->order($order)
            ->paginate($limit)
            ->each(function($row){
                if($row['exit_time']){
                    $row['park_time']=$row['exit_time']-$row['entry_time'];
                }
            });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('POST','free')]
    public function free()
    {
        $ids=$this->request->post('ids');
        ParkingRecords::where('id','in',$ids)
        ->where('parking_id',$this->parking->id)
        ->update([
            'remark'=>date('Y-m-d H:i:s').'【'.$this->auth->nickname.'】设为免费出场',
            'status'=>ParkingRecords::STATUS('免费出场')
        ]);
        $this->success('操作成功');
    }

    #[Route('POST','del')]
    public function del()
    {
        $ids=$this->request->post('ids');
        ParkingRecovery::where('parking_id',$this->parking->id)->whereIn('id',$ids)->delete();
        $this->success();
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $records_id=$this->request->post('row.records_id');
            $recovery_type=$this->request->post('row.recovery_type');
            if($recovery_type=='platform'){
                $this->error('请先加入《平台追缴计划》！');
            }
            $list=ParkingRecovery::with(['records'])->where('parking_id',$this->parking->id)->whereIn('records_id',$records_id)->select();
            if(count($list)>0){
                $this->error('【'.$list[0]->records->plate_number.'-￥'.$list[0]->records->total_fee.'】追缴记录已存在');
            }
            $msg=$this->request->post('row.msg');
            $arr=[];
            foreach ($records_id as $v){
                $records=ParkingRecords::where(['parking_id'=>$this->parking->id,'id'=>$v])->find();
                $search_parking=null;
                if($recovery_type==ParkingRecovery::RECOVERYTYPE('车场追缴')){
                    $search_parking=$this->parking->id;
                }
                if($recovery_type==ParkingRecovery::RECOVERYTYPE('集团追缴')){
                    $search_parking=Parking::where('property_id',$this->parking->property_id)->column('id');
                    $search_parking=implode(',',$search_parking);
                }
                $arr[]=[
                    'parking_id'=>$this->parking->id,
                    'records_id'=>$records->id,
                    'plate_number'=>$records->plate_number,
                    'total_fee'=>round($records->total_fee-$records->pay_fee-$records->activities_fee,2),
                    'search_parking'=>$search_parking,
                    'recovery_type'=>$recovery_type,
                    'entry_set'=>$this->request->post('row.entry_set'),
                    'exit_set'=>$this->request->post('row.exit_set'),
                    'msg'=>$msg?1:0,
                ];
            }
            $recovery=new ParkingRecovery();
            $recovery->saveAll($arr);
            $this->success();
        }
        $ids=$this->request->get('ids');
        $ids=explode(',',$ids);
        $list=ParkingRecords::where('id','in',$ids)->where('parking_id',$this->parking->id)->field('id,plate_number,total_fee')->select();
        $records=[];
        foreach ($list as $v){
            $records[$v->id]=$v['plate_number'].'-￥'.$v['total_fee'];
        }
        if($this->parking->property_id){
            $parkings=Parking::where('property_id',$this->parking->property_id)->column('title','id');
        }else{
            $parkings=[
                $this->parking->id=>$this->parking->title
            ];
        }
        $this->assign('records',$records);
        $this->assign('parkings',$parkings);
        return $this->fetch();
    }
}
