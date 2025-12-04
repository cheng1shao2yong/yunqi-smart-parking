<?php
declare (strict_types = 1);

namespace app\api\controller\parking;

use app\common\library\ParkingAccount;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingException;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingQrcode;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecovery;
use app\common\model\parking\ParkingScreen;
use app\common\model\parking\ParkingTemporary;
use app\common\service\barrier\Utils;
use app\common\service\ParkingService;
use think\annotation\route\Get;
use think\annotation\route\Post;
use think\annotation\route\Group;
use think\facade\Db;


#[Group("parking/records")]
class Records extends Base
{
    #[Get('instock')]
    public function instock()
    {
        $page=$this->request->get('page/d');
        $time=time();
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'status'=>'normal'])->column('title','id');
        $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
        $account=new ParkingAccount($parking);
        $list=ParkingRecords::where('parking_id',$this->parking_id)
            ->where(function($query){
                $starttime=$this->request->get('starttime');
                $endtime=$this->request->get('endtime');
                $plate_number=$this->request->get('plate_number');
                $query->whereIn('status',[0,1,6]);
                if($plate_number){
                    $query->where('plate_number',$plate_number);
                }
                if($starttime){
                    $starttime=strtotime($starttime.' 00:00:00');
                }
                if($endtime){
                    $endtime=strtotime($endtime.' 23:59:59');
                }
                if($starttime && $endtime){
                    $query->whereBetween('entry_time',[$starttime,$endtime]);
                }elseif($starttime){
                    $query->where('entry_time','>=',$starttime);
                }elseif($endtime){
                    $query->where('entry_time','<=',$endtime);
                }
            })
            ->order('id desc')
            ->limit(($page-1)*10,10)
            ->select()
            ->each(function($row,$key) use ($time,$account,$barrier){
                $fee=$account->setRecords($row->plate_type,$row->special,$row->entry_time,$time,$row->rules)->fee();
                $row['park_time']=$time-$row['entry_time'];
                $row['fee']=$fee->getTotal();
                $row['barrier']=$barrier[$row['entry_barrier']];
                return $row;
            });
        $this->success('',$list);
    }

    #[Get('get-records')]
    public function getRecords()
    {
        $plate_number=$this->request->get('plate_number');
        $records=ParkingRecords::where(['parking_id'=>$this->parking_id,'plate_number'=>$plate_number])->whereIn('status',[0,1])->find();
        if (!$records){
            $this->error('没有找到停车记录');
        }
        $this->success('',$records);
    }

    #[Get('pay-info')]
    public function payInfo()
    {
        $plate_number=$this->request->get('plate_number');
        $records=ParkingRecords::where(['parking_id'=>$this->parking_id,'plate_number'=>$plate_number])->whereIn('status',[0,1,6])->order('id desc')->find();
        if(!$records){
            $this->error('没有找到需要缴费的项目');
        }
        $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
        $service=ParkingService::newInstance([
            'parking'=>$parking,
            'plate_number'=>$records->plate_number,
            'plate_type'=>$records->plate_type
        ]);
        $total_fee=$service->getTotalFee($records,time());
        [$activities_fee,$activities_time,$coupon_type,$couponlist,$coupont_title]=$service->getActivitiesFee($records,$total_fee);
        $need_pay_fee=formatNumber($total_fee-$records->activities_fee-$activities_fee-$records->pay_fee);
        $this->success('',[
            'records'=>$records,
            'total_fee'=>$total_fee,
            'activities_fee'=>$records->activities_fee+$activities_fee,
            'pay_fee'=>$records->pay_fee,
            'need_pay_fee'=>$need_pay_fee,
            'coupon'=>$coupont_title,
        ]);
    }

    #[Get('exception')]
    public function exception()
    {
        $page=$this->request->get('page/d');
        $list=ParkingException::where('parking_id',$this->parking_id)
            ->order('id desc')
            ->limit(($page-1)*10,10)
            ->select();
        $this->success('',$list);
    }

    #[Post('pay')]
    public function pay()
    {
        $records_id=$this->request->post('records_id');
        $pay_type=$this->request->post('pay_type');
        $remark=$this->request->post('remark');
        $records=ParkingRecords::where(['parking_id'=>$this->parking_id,'id'=>$records_id])->find();
        if(!$records){
            $this->error('找不到停车记录');
        }
        if($pay_type=='underline'){
            $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
            $service=ParkingService::newInstance([
                'parking'=>$parking,
                'plate_number'=>$records->plate_number,
                'plate_type'=>$records->plate_type,
                'records_type'=>ParkingRecords::RECORDSTYPE('手动操作'),
                'pay_status'=>ParkingRecords::STATUS('缴费未出场'),
                'exit_time'=>time(),
                'remark'=>$remark
            ]);
            $service->createOrder($records);
        }
        if($pay_type=='scanqrcode'){
            if($records->status!=ParkingRecords::STATUS('缴费未出场')){
                $this->error('未完成缴费，请用户扫码');
            }
        }
        $this->success('操作成功');
    }

    //更新删除if内的判断
    #[Get('qrcode')]
    public function qrcode()
    {
        $records_id=$this->request->get('records_id');
        $plate_number=$this->request->get('plate_number');
        if($plate_number){
            $records=ParkingRecords::where(['parking_id'=>$this->parking_id,'plate_number'=>$plate_number])->whereIn('status',[0,1])->find();
            $records_id=$records->id;
        }
        $parkingqrcode=new ParkingQrcode();
        $parkingqrcode->name='records';
        $parkingqrcode->parking_id=$this->parking_id;
        $parkingqrcode->records_id=$records_id;
        $img=ParkingQrcode::getQrcode($parkingqrcode);
        Header("Content-type: image/png");
        echo $img;
        exit;
    }

    #[Get('list')]
    public function list()
    {
        $page=$this->request->get('page/d');
        $now=time();
        $list=ParkingRecords::where('parking_id',$this->parking_id)
            ->where(function($query){
                $starttime=$this->request->get('starttime');
                $endtime=$this->request->get('endtime');
                $plate_number=$this->request->get('plate_number');
                $status=$this->request->get('status');
                if($plate_number){
                    $query->where('plate_number','like','%'.$plate_number.'%');
                }
                if($starttime){
                    $starttime=strtotime($starttime.' 00:00:00');
                }
                if($endtime){
                    $endtime=strtotime($endtime.' 23:59:59');
                }
                if($starttime && $endtime){
                    $query->whereBetween('entry_time',[$starttime,$endtime]);
                }elseif($starttime){
                    $query->where('entry_time','>=',$starttime);
                }elseif($endtime){
                    $query->where('entry_time','<=',$endtime);
                }
                if($status){
                    $query->whereIn('status',$status);
                }
            })
            ->order('id desc')
            ->limit(($page-1)*15,15)
            ->select()
            ->each(function ($res) use ($now){
                $exit_time=$res->exit_time;
                if(!$exit_time){
                    $exit_time=$now;
                }
                $res['park_time']=$exit_time-$res['entry_time'];
            });
        $this->success('',$list);
    }

    #[Get('recovery')]
    public function recovery()
    {
        $page=$this->request->get('page/d');
        $list=ParkingRecords::where(function($query){
            $type=$this->request->get('type');
            $prefix=getDbPrefix();
            $raw='';
            if($type=='evading'){
                $raw="id not in (select records_id from {$prefix}parking_recovery where parking_id={$this->parking_id})";
            }
            if($type=='recovery'){
                $raw="id in (select records_id from {$prefix}parking_recovery where parking_id={$this->parking_id})";
            }
            $starttime=$this->request->get('starttime');
            $endtime=$this->request->get('endtime');
            $plate_number=$this->request->get('plate_number');
            if($plate_number){
                $query->where('plate_number','like','%'.$plate_number.'%');
            }
            if($starttime){
                $starttime=strtotime($starttime.' 00:00:00');
            }
            if($endtime){
                $endtime=strtotime($endtime.' 23:59:59');
            }
            if($starttime && $endtime){
                $query->whereBetween('entry_time',[$starttime,$endtime]);
            }elseif($starttime){
                $query->where('entry_time','>=',$starttime);
            }elseif($endtime){
                $query->where('entry_time','<=',$endtime);
            }
            $query->whereIn('status',[6,7]);
            $query->whereRaw($raw);
            $query->where('parking_id',$this->parking_id);
        })
        ->with(['recovery'])
        ->order('id desc')
        ->limit(($page-1)*15,15)
        ->select()
        ->each(function ($res){
            if($res['exit_time']){
                $res['park_time']=$res['exit_time']-$res['entry_time'];
            }
        });
        $this->success('',$list);
    }

    #[Post('free')]
    public function free()
    {
        $id=$this->request->post('id');
        ParkingRecords::where(['id'=>$id,'parking_id'=>$this->parking_id])
            ->update([
                'remark'=>date('Y-m-d H:i:s').'【'.$this->parkingAdmin['nickname'].'】设为免费出场',
                'status'=>ParkingRecords::STATUS('免费出场')
            ]);
        $this->success('操作成功');
    }

    #[Post('cancel-recovery')]
    public function cancelRecovery()
    {
        $id=$this->request->post('id');
        ParkingRecovery::where(['id'=>$id,'parking_id'=>$this->parking_id])->delete();
        $this->success('操作成功');
    }
    #[Post('set-recovery')]
    public function setRecovery()
    {
        $records_id=$this->request->post('records_id');
        $recovery_type=$this->request->post('recovery_type');
        if($recovery_type=='platform'){
            $this->error('请先加入《平台追缴计划》！');
        }
        $recovery=ParkingRecovery::where(['parking_id'=>$this->parking_id,'records_id'=>$records_id])->find();
        if($recovery){
            $this->error('【'.$recovery->records->plate_number.'-￥'.$recovery->records->total_fee.'】追缴记录已存在');
        }
        $records=ParkingRecords::where(['id'=>$records_id,'parking_id'=>$this->parking_id])->find();
        $search_parking=null;
        if($recovery_type==ParkingRecovery::RECOVERYTYPE('车场追缴')){
            $search_parking=$this->parking_id;
        }
        if($recovery_type==ParkingRecovery::RECOVERYTYPE('集团追缴')){
            $parking=Parking::find($this->parking_id);
            $search_parking=Parking::where('property_id',$parking->property_id)->column('id');
            $search_parking=implode(',',$search_parking);
        }
        $arr=[
            'parking_id'=>$this->parking_id,
            'records_id'=>$records_id,
            'plate_number'=>$records->plate_number,
            'total_fee'=>$records->total_fee,
            'search_parking'=>$search_parking,
            'recovery_type'=>$recovery_type,
            'entry_set'=>$this->request->post('entry_set'),
            'exit_set'=>$this->request->post('exit_set'),
            'msg'=>$this->request->post('msg'),
        ];
        $recovery=new ParkingRecovery();
        $recovery->save($arr);
        $this->success('操作成功');
    }

    #[Get('recovery-info')]
    public function recoveryInfo()
    {
        $id=$this->request->get('id');
        $records=Db::name('parking_records')->where(['id'=>$id,'parking_id'=>$this->parking_id])->field('id,plate_number,total_fee')->find();
        $property_id=Parking::where('id',$this->parking_id)->value('property_id');
        if($property_id){
            $parkings=Parking::where('property_id',$property_id)->field('id,title')->select();
        }else{
            $parkings=Parking::where('id',$this->parking_id)->field('id,title')->select();
        }
        $this->success('',compact('records','parkings'));
    }

    #[Get('detail')]
    public function detail()
    {
        $id=$this->request->get('id');
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'status'=>'normal'])->column('title','id');
        $records=ParkingRecords::with(['coupon'])->where(['parking_id'=>$this->parking_id,'id'=>$id])->find();
        if($records->status===0 || $records->status==6){
            $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
            $service=ParkingService::newInstance([
                'parking'=>$parking,
                'plate_number'=>$records->plate_number,
                'plate_type'=>$records->plate_type
            ]);
            $total_fee=$service->getTotalFee($records,time());
            [$activities_fee,$activities_time,$coupon_type,$couponlist,$coupont_title]=$service->getActivitiesFee($records,$total_fee);
            $records->total_fee=$total_fee;
            $records->activities_fee=$records->activities_fee+$activities_fee;
        }
        if(!empty($records->coupon)){
            $records->coupon=$records->getCouponTxt();
        }
        $records->entry_barrier=isset($barrier[$records->entry_barrier])?$barrier[$records->entry_barrier]:'';
        $records->exit_barrier=isset($barrier[$records->exit_barrier])?$barrier[$records->exit_barrier]:'';
        $this->success('',$records);
    }

    #[Get('get-info')]
    public function getInfo()
    {
        $plate_number=$this->request->get('plate_number');
        if($plate_number){
            $plate=ParkingPlate::where('plate_number',$plate_number)->order('id desc')->find();
            if($plate){
                $this->success('',[$plate->plate_type,ParkingMode::PLATETYPE[$plate->plate_type]]);
            }else{
                $this->success('',['blue','蓝牌']);
            }
        }else{
            $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'status'=>'normal'])->field('id,title,barrier_type')->select();
            $plate_type=[];
            foreach (ParkingMode::PLATETYPE as $k=>$v){
                $plate_type[]=[
                    'id'=>$k,
                    'title'=>$v
                ];
            }
            $this->success('',compact('barrier','plate_type'));
        }
    }

    #[Post('edit')]
    public function edit()
    {
        $id=$this->request->post('id');
        $type=$this->request->post('type');
        $value=$this->request->post('value');
        $records=ParkingRecords::where(['id'=>$id,'parking_id'=>$this->parking_id])->find();
        if($records->status===0 || $records->status==6){
            if($type=='plate_number'){
                $records->plate_number=$value;
            }
            if($type=='entry_time'){
                $records->entry_time=strtotime($value.':00');
            }
            $records->save();
            $this->success('修改成功');
        }
        $this->error('修改失败');
    }

    #[Post('entry')]
    public function entry()
    {
        $post=$this->request->post();
        $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'id'=>$post['barrier_id'],'status'=>'normal'])->find();
        if(!$barrier){
            $this->error('找不到对应的道闸');
        }
        try{
            $parkingService=ParkingService::newInstance([
                'parking'=>$parking,
                'barrier'=>$barrier,
                'plate_number'=>$post['plate_number'],
                'plate_type'=>$post['plate_type'],
                'records_type'=>ParkingRecords::RECORDSTYPE('手动操作'),
                'entry_time'=>strtotime($post['entry_time']),
                'photo'=>$post['photo'],
                'remark'=>$post['remark']
            ]);
            $parkingService->entry();
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('操作成功');
    }

    #[Post('exit')]
    public function exit()
    {
        $post=$this->request->post();
        $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking_id,'id'=>$post['barrier_id'],'status'=>'normal'])->find();
        if(!$barrier){
            $this->error('找不到对应的道闸');
        }
        try{
            $parkingService=ParkingService::newInstance([
                'parking'=>$parking,
                'barrier'=>$barrier,
                'plate_number'=>$post['plate_number'],
                'plate_type'=>$post['plate_type'],
                'records_type'=>ParkingRecords::RECORDSTYPE('手动操作'),
                'exit_time'=>strtotime($post['exit_time']),
                'photo'=>$post['photo'],
                'pay_status'=>$post['pay_status'],
                'remark'=>$post['remark']
            ]);
            $parkingService->exit();
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('操作成功');
    }

    #[Get('checkpay')]
    public function checkpay()
    {
        $plate_number=$this->request->get('plate_number');
        $records=ParkingRecords::where(['plate_number'=>$plate_number,'parking_id'=>$this->parking_id])->order('id desc')->find();
        if($records && $records->status==1){
            $this->success();
        }else{
            $this->error();
        }
    }
}
