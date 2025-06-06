<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\parking\ParkingCarsApply;
use app\common\model\parking\ParkingPlate;
use app\common\model\PlateBinding;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\facade\Db;

#[Group("rules")]
class Rules extends Api
{
    #[Get('list')]
    public function list()
    {
        $rules_type=$this->request->get('rules_type');
        $plate_number=PlateBinding::getUserPlate($this->auth->id);
        $list=ParkingPlate::withJoin([
            'cars'=>function ($query) use($rules_type) {
                $query->where('deletetime','=',null);
                $query->where('rules_type','=',$rules_type);
            }
        ],'inner')->whereIn('plate_number',$plate_number)->select();
        $parking_ids=[];
        foreach ($list as $v){
           if(!in_array($v->cars->parking_id,$parking_ids)){
               $parking_ids[]=$v->cars->parking_id;
           }
        }
        $apply=ParkingCarsApply::where(function ($query) use ($plate_number,$rules_type){
            $atype=[
                'monthly'=>'month_apply',
                'day'=>'day_apply',
                'stored'=>'stored_apply'
            ];
            $query->where('user_id','=',$this->auth->id);
            $query->where('status','=',0);
            $query->where('apply_type','=',$atype[$rules_type]);
            $query->where('cars_id','=',null);
        })->select();
        foreach ($apply as $v){
            if(!in_array($v->parking_id,$parking_ids)){
                $parking_ids[]=$v->parking_id;
            }
        }
        $parking=Db::name('parking')->alias('p')->join('parking_setting ps','p.id=ps.parking_id')->whereIn('p.id',$parking_ids)->column('p.id,p.title,ps.phone','p.id');
        foreach ($list as $k=>$v){
            $id=$v->cars->parking_id;
            $list[$k]->parking=$parking[$id];
            if($v->cars->status=='normal' && $v->cars->endtime<time()){
                $list[$k]->cars->status='expire';
                $hasapply=ParkingCarsApply::where(['cars_id'=>$v->cars->id,'status'=>0])->count();
                if($hasapply){
                    $list[$k]->cars->status='apply';
                }
            }
        }
        foreach ($apply as $k=>$v){
            $id=$v['parking_id'];
            $apply[$k]->parking=$parking[$id];
        }
        $this->success('',compact('list','apply'));
    }
}
