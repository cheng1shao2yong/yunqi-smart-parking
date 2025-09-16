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
use app\common\library\ParkingAccount;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingException;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingQrcode;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsDetail;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRules;
use app\common\service\ParkingService;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("records")]
class Records extends ParkingBase
{
    use Actions{
        download as _download;
        import as _import;
    }

    protected $noNeedRight=['qrcode'];

    protected function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingRecords();
        $this->assign('barrier',ParkingBarrier::where(['parking_id'=>$this->parking->id,'pid'=>0])->column('title','id'));
        $this->assign('plate_type',ParkingMode::PLATETYPE);
        $this->assign('rules_type',ParkingRules::RULESTYPE);
        $this->assign('records_type',ParkingRecords::RECORDSTYPE);
        $this->assign('status',ParkingRecords::STATUS);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            $starttime=date('Y-m-d',time());
            $endtime=date('Y-m-d',time()-6*24*60*60);
            $this->assign('starttime',$starttime);
            $this->assign('endtime',$endtime);
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->where($where)
            ->order($order)
            ->paginate($limit)
            ->each(function($row){
                if($row['exit_time']){
                    $row['park_time']=$row['exit_time']-$row['entry_time'];
                }
                $row['coupon_txt']=$row->getCouponTxt();
            });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,JSON','download')]
    public function download()
    {
        if($this->request->isAjax()){
            $entry_time=$this->filter('entry_time');
            $exit_time=$this->filter('exit_time');
            if(!$entry_time && !$exit_time){
                $this->error('请选择入场或出场日期');
            }
            $this->callback=function ($res){
                $res->entry_time=date('Y-m-d H:i:s',$res->entry_time);
                $res->exit_time=date('Y-m-d H:i:s',$res->exit_time);
                $res->park_time=formatTime($res->park_time);
                return $res;
            };
        }
        return $this->_download();
    }

    #[Route('GET,POST','del')]
    public function del()
    {
        $this->success('删除成功');
    }

    #[Route('GET,POST','pay')]
    public function pay()
    {
        $records_id=$this->request->get('records_id');
        $records=ParkingRecords::with(['cars'])->where(['parking_id'=>$this->parking->id,'id'=>$records_id])->find();
        if(!$records){
            $this->error('找不到停车记录');
        }
        if($this->request->isPost()){
            $pay_type=$this->request->post('row.pay_type');
            $remark=$this->request->post('row.remark');
            if($pay_type=='underline'){
                $service=ParkingService::newInstance([
                    'parking'=>$this->parking,
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
            $this->success('缴费成功');
        }
        if($records->status===0 || $records->status==1 || $records->status==6){
            $exit_time=time();
            $exit_time=strtotime('2025-09-13 10:14:00');
            $service=ParkingService::newInstance([
                'parking'=>$this->parking,
                'plate_number'=>$records->plate_number,
                'plate_type'=>$records->plate_type,
                'exit_time'=>$exit_time,
            ]);
            $total_fee=$service->getTotalFee($records,$exit_time);
            [$activities_fee,$activities_time,$coupon_type,$couponlist,$coupont_title]=$service->getActivitiesFee($records,$total_fee);
            $need_pay_fee=formatNumber($total_fee-$records->activities_fee-$activities_fee-$records->pay_fee);
            $this->assign('records',$records);
            $this->assign('total_fee',formatNumber($total_fee));
            $this->assign('pay_fee',formatNumber($records->pay_fee));
            $this->assign('need_pay_fee',$need_pay_fee);
            $this->assign('coupon',$coupont_title);
            return $this->fetch();
        }
        $this->error('停车已经缴费');
    }

    #[Route('GET','qrcode')]
    public function qrcode()
    {
        $records_id=$this->request->get('records_id');
        $parkingqrcode=new ParkingQrcode();
        $parkingqrcode->name='records';
        $parkingqrcode->records_id=$records_id;
        $parkingqrcode->parking=$this->parking;
        $img=ParkingQrcode::getQrcode($parkingqrcode);
        Header("Content-type: image/png");
        echo $img;
        exit;
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        $ids=$this->request->get('ids');
        $records=ParkingRecords::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
        if(!$records){
            $this->error('找不到停车记录');
        }
        if($records->status!==0 && $records->status!=6){
            $this->error('该记录禁止编辑');
        }
        if($this->request->isPost()){
            $entry_time=strtotime($this->request->post('row.entry_time'));
            $plate_number=strtoupper(trim($this->request->post('row.plate_number')));
            if($entry_time>time()){
                $this->error('入场时间不能大于当前时间');
            }
            if(!is_car_license($plate_number)){
                $this->error('车牌号格式错误');
            }
            $records->entry_time=$entry_time;
            $records->plate_number=$plate_number;
            $records->save();
            $this->success('修改成功');
        }
        $this->assign('row',$records);
        return $this->fetch();
    }

    #[Route('GET,POST','pl-exit')]
    public function plExit()
    {
        if($this->request->isPost()){
            $ids=$this->request->post('row.ids');
            $barrier_id=$this->request->post('row.barrier_id');
            $exit_time=$this->request->post('row.exit_time');
            $pay_status=$this->request->post('row.pay_status');
            $remark=$this->request->post('row.remark');
            $statusnum=[
                'x3'=>3,
                'x4'=>4,
                'x7'=>7
            ];
            ParkingRecords::whereIn('id',$ids)->where('parking_id',$this->parking->id)->update([
                'exit_time'=>strtotime($exit_time),
                'exit_barrier'=>$barrier_id,
                'status'=>$statusnum[$pay_status],
                'remark'=>$remark
            ]);
            $this->success();
        }
        $ids=explode(',',$this->request->get('ids'));
        $list=ParkingRecords::whereIn('id',$ids)->where('parking_id',$this->parking->id)->select();
        $plate_number=[];
        $list->each(function ($item) use(&$plate_number){
            $plate_number[]=$item->plate_number;
        });
        $row=[
            'ids'=>$ids,
            'plate_number'=>implode(',',$plate_number),
            'pay_status'=>'x4'
        ];
        $this->assign('row',$row);
        return $this->fetch();
    }
    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $act=$this->request->post('act');
            $post=$this->request->post('row/a');
            $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id,'id'=>$post['barrier_id'],'status'=>'normal'])->find();
            if(!$barrier){
                $this->error('找不到对应的道闸');
            }
            if($barrier->trigger_type=='inside'){
                $this->error('场内场禁止手动入场');
            }
            try{
                $statusnum=[
                    'x3'=>3,
                    'x4'=>4,
                    'x7'=>7
                ];
                $parkingService=ParkingService::newInstance([
                    'parking'=>$this->parking,
                    'barrier'=>$barrier,
                    'plate_number'=>$post['plate_number'],
                    'plate_type'=>$post['plate_type'],
                    'records_type'=>ParkingRecords::RECORDSTYPE('手动操作'),
                    'entry_time'=>isset($post['entry_time'])?strtotime($post['entry_time']):null,
                    'exit_time'=>isset($post['exit_time'])?strtotime($post['exit_time']):null,
                    'pay_status'=>$statusnum[$post['pay_status']],
                    'remark'=>$post['remark']
                ]);
                //外场入场
                if($act=='entry' && $barrier->trigger_type=='outfield'){
                    $parkingService->entry();
                }
                //外场出场
                if($act=='exit' && $barrier->trigger_type=='outfield'){
                    $parkingService->exit();
                }
                //内场入场
                if($act=='entry' && $barrier->trigger_type=='infield'){
                    $parkingService->infieldEntry();
                }
                //内场出场
                if($act=='exit' && $barrier->trigger_type=='infield'){
                    $parkingService->infieldExit();
                }
                if($act=='entryexit'){
                    if($post['entry_time']>$post['exit_time']){
                        $this->error('入场时间不能大于出场时间');
                    }
                    $parkingService->entry();
                    $parkingService->exit();
                }
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
            $this->success('操作成功');
        }
        $plate_number=$this->request->get('plate_number','');
        $this->assign('plate_number',$plate_number);
        return $this->fetch();
    }

    #[Route('GET,JSON','instock')]
    public function instock()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        $where[]=['status','in',[0,1,6]];
        if($this->request->post('selectpage')){
            return $this->selectpage($where);
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $time=time();
        $account=new ParkingAccount($this->parking);
        $list = $this->model
            ->where($where)
            ->with(['rules','cars'])
            ->order($order)
            ->paginate($limit)
            ->each(function($row) use ($time,$account){
                $fee=$account->setRecords($row->plate_type,$row->special,$row->entry_time,$time,$row->rules)->fee();
                $row['park_time']=$time-$row['entry_time'];
                $row['fee']=formatNumber($fee->getTotal());
                return $row;
            });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,JSON','detail')]
    public function detail()
    {
        $ids=$this->request->get('ids');
        $records=ParkingRecords::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
        $detail=ParkingRecordsDetail::where(['records_id'=>$ids,'parking_id'=>$this->parking->id])->select();
        $pay=ParkingRecordsPay::with(['unionpay'])->whereNotNull('pay_id')->where(['records_id'=>$ids,'parking_id'=>$this->parking->id])->select();
        $this->assign('records',$records);
        $this->assign('parking_detail',$detail);
        $this->assign('pay_detail',$pay);
        return $this->fetch();
    }

    #[Route('GET,POST','import-instock')]
    public function importInstock()
    {
        $this->importFields=[
            'plate_number'=>'车牌号',
            'entry_time'=>'入场时间',
            'plate_type'=>'车牌颜色',
            'rules_type'=>'停车规则'
        ];
        $barrier=ParkingBarrier::where(['parking_id'=>$this->parking->id,'barrier_type'=>'entry'])->find();
        if(!$barrier){
            $this->error('没有入场通道');
        }
        $rules=ParkingRules::with(['provisionalmode'])->where('parking_id',$this->parking->id)->select();
        $now=time();
        $this->callback=function ($row,&$success,&$error) use ($barrier,$rules,$now){
            if(is_numeric($row['entry_time'])){
                $row['entry_time']=Date::excelToDateTimeObject($row['entry_time']);
                $row['entry_time']=strtotime($row['entry_time']->format('Y-m-d H:i:s'));
            }else{
                $row['entry_time']=strtotime($row['entry_time']);
            }
            $row['parking_id']=$this->parking->id;
            $row['parking_title']=$this->parking->title;
            $platenumber=trim($row['plate_number']);
            if(!is_car_license($platenumber)){
                $error[]=$platenumber.'车牌号错误';
                return false;
            }
            $row['plate_number']=$platenumber;
            $plate_type=ParkingMode::PLATETYPE($row['plate_type']);
            if(!$plate_type){
                $error[]=$row['plate_type'].'车牌颜色错误';
                return false;
            }else{
                $row['plate_type']=$plate_type;
            }
            foreach ($rules as $value){
                if($value->title==$row['rules_type']){
                    $row['rules_id']=$value->id;
                    $row['rules_type']=$value->rules_type;
                    break;
                }
                if($value->rules_type=='provisional' && $value->provisionalmode->title==$row['rules_type']){
                    $row['rules_id']=$value->id;
                    $row['rules_type']='provisional';
                    break;
                }
            }
            if(!isset($row['rules_id'])){
                $error[]=$row['rules_type'].'停车规则不存在';
                return false;
            }
            $row['entry_type']='normal';
            $row['entry_barrier']=$barrier->id;
            $row['status']=0;
            $row['createtime']=$now;
            $row['updatetime']=$now;
            ParkingRecords::create($row);
            $success++;
            return false;
        };
        return $this->_import();
    }

    #[Route('GET,JSON','exceplogs')]
    public function exceplogs()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $this->model=new ParkingException();
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
}
