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
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingCarsLogs;
use app\common\model\parking\ParkingCarsOccupat;
use app\common\model\parking\ParkingCarsStop;
use app\common\model\parking\ParkingMode;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingMonthlyRecharge;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingStoredLog;
use app\common\model\PayUnion;
use app\common\model\Third;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("cars")]
class Cars extends ParkingBase
{
    protected $noNeedRight=['rulesRemark','changeRules'];

    use Actions{
        add as _add;
        edit as _edit;
        download as _download;
        import as _import;
        multi as _multi;
    }

    private $rules_type;

    public function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingCars();
        $this->rules_type=$this->request->get('rules_type',ParkingCars::getRulesDefaultType($this->parking));
        $this->assign('plate_type',ParkingMode::PLATETYPE);
        $this->assign('car_models',ParkingPlate::CARMODELS);
        $this->assign('rules_type',ParkingCars::getRulesType($this->parking));
        $this->assign('pay_type',PayUnion::PAYTYPE);
        $this->assign('rules_type_value',$this->rules_type);
        $this->assign('insufficient_balance',ParkingCars::INSUFFICIENT_BALANCE);
        $this->assign('logType',ParkingStoredLog::LOGTYPE);
    }

    #[Route('GET,POST','importplate')]
    public function importplate()
    {
        $this->model=new ParkingPlate();
        $this->importFields=[
            'plate_number'=>'车牌号',
            'plate_type'=>'车牌颜色',
            'car_models'=>'车辆类型'
        ];
        $cars_id=$this->request->get('cars_id');
        //导入回调
        $this->callback=function ($row,&$success,&$error) use ($cars_id) {
            if(!is_car_license($row['plate_number'])){
                $error[]='【'.$row['plate_number'].'】车牌号格式不正确';
                return;
            }
            $plate=ParkingPlate::where(['parking_id'=>$this->parking->id,'plate_number'=>$row['plate_number']])->find();
            if($plate){
                $error[]='【'.$row['plate_number'].'】车牌号已存在';
                return;
            }
            $row['plate_type']=ParkingMode::PLATETYPE($row['plate_type']);
            if(!$row['plate_type']){
                $row['plate_type']='blue';
            }
            $row['car_models']=ParkingPlate::CARMODELS($row['car_models']);
            if(!$row['car_models']){
                $row['car_models']='small';
            }
            $row['parking_id']=$this->parking->id;
            $row['cars_id']=$cars_id;
            return $row;
        };
        $this->importSuccess=function () use ($cars_id){
            $number=ParkingPlate::where(['parking_id'=>$this->parking->id,'cars_id'=>$cars_id])->count();
            ParkingCars::where(['parking_id'=>$this->parking->id,'id'=>$cars_id])->update(['plates_count'=>$number]);
        };
        return $this->_import();
    }
    #[Route('GET,JSON','listplate')]
    public function listplate()
    {
        $this->model=new ParkingPlate();
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        $where[]=['cars_id','=',$this->request->get('cars_id')];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,POST','delplate')]
    public function delplate()
    {
        $cars_id=$this->request->get('cars_id');
        $ids=$this->request->post('ids');
        $ids=explode(',',$ids);
        $number=ParkingPlate::where(['parking_id'=>$this->parking->id,'cars_id'=>$cars_id])->count();
        if($number-count($ids)<=0){
            $this->error('至少保留一个车牌');
        }
        ParkingPlate::where(['parking_id'=>$this->parking->id])->whereIn('id',$ids)->delete();
        $number=ParkingPlate::where(['parking_id'=>$this->parking->id,'cars_id'=>$cars_id])->count();
        ParkingCars::where(['parking_id'=>$this->parking->id,'id'=>$cars_id])->update(['plates_count'=>$number]);
        $this->success();
    }

    #[Route('GET,POST','addplate')]
    public function addplate()
    {
        if($this->request->isPost()){
            $postdata=$this->request->post('row/a');
            if(!is_car_license($postdata['plate_number'])){
                $this->error('车牌号格式不正确');
            }
            $postdata['parking_id']=$this->parking->id;
            $postdata['cars_id']=$this->request->get('cars_id');
            $havaplate=ParkingPlate::where(function($query) use ($postdata){
                $query->where('plate_number',$postdata['plate_number']);
                $query->where('parking_id',$this->parking->id);
                $query->where('cars_id','<>',null);
            })->find();
            if($havaplate){
                $this->error('车牌号已经存在');
            }
            (new ParkingPlate())->save($postdata);
            $number=ParkingPlate::where(['parking_id'=>$this->parking->id,'cars_id'=>$postdata['cars_id']])->count();
            ParkingCars::where(['parking_id'=>$this->parking->id,'id'=>$postdata['cars_id']])->update(['plates_count'=>$number]);
        }
        return $this->_add();
    }

    #[Route('POST,GET','multi')]
    public function multi()
    {
        $cars_id=$this->request->post('cars_id');
        $change_type=$this->request->post('change_type');
        $day=$this->request->post('day');
        $date=$this->request->post('date');
        $cars=ParkingCars::where(['parking_id'=>$this->parking->id,'id'=>$cars_id])->find();
        if(!$cars){
            $this->error('月卡不存在');
        }
        $status=$cars->status;
        try{
            if($status=='hidden'){
                $stop=ParkingCarsStop::where(['parking_id'=>$this->parking->id,'cars_id'=>$cars_id])->find();
                $cars->restart($stop,false);
                ParkingCarsLogs::addLog($cars,$this->auth->id,'月卡启用');
            }
            if($status=='normal'){
                $cars->stop($change_type,$day,$date);
                ParkingCarsLogs::addLog($cars,$this->auth->id,'月卡报停');
            }
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('操作成功');
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            $rules=ParkingRules::where(['parking_id'=>$this->parking->id,'rules_type'=>$this->rules_type])->column('title','id');
            $this->assign('rules',$rules);
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        $where[]=['rules_type','=',$this->rules_type];
        if($this->request->post('selectpage')){
            return $this->selectpage($where);
        }
        $plates=$this->filter('plates');
        $remark=$this->filter('remark');
        if($plates){
            $ids=ParkingPlate::where('parking_id',$this->parking->id)->whereLike('plate_number','%'.$plates.'%')->column('cars_id');
            $where[]=['id','in',$ids];
        }
        if($remark){
            $where[]=['remark|remark_line','like','%'.$remark.'%'];
        }
        $expire=$this->filter('expire');
        $now=time();
        if($expire=='0'){
            $where[]=['endtime','>',$now+7*24*3600];
            $where[]=['starttime','<',$now];
        }
        if($expire==1){
            $where[]=['status','=','hidden'];
        }
        if($expire==2){
            $where[]=['endtime','<=',$now+7*24*3600];
            $where[]=['endtime','>',$now];
            $where[]=['starttime','<',$now];
        }
        if($expire==3){
            $where[]=['endtime','<',$now];
        }
        if($expire==4){
            $where[]=['starttime','>',$now];
        }
        if($this->request->post('selectpage')){
            return $this->selectpage($where);
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['plates'=>function ($query) {
                $query->limit(1);
            },'rules','third'])
            ->where($where)
            ->order($order)
            ->paginate($limit)
            ->each(function($res) use ($now){
                if($now>$res['endtime']){
                    $res['expire']=3;
                }elseif($now<$res['starttime']){
                    $res['expire']=4;
                }elseif($now>$res['starttime'] && $now<($res['endtime']-7*24*3600)){
                    $res['expire']=0;
                } elseif($now>$res['starttime'] && $now>=($res['endtime']-7*24*3600) && $now<$res['endtime']){
                    $res['expire']=2;
                }
                if($res['status']=='hidden'){
                    $stop=ParkingCarsStop::where(['parking_id'=>$res['parking_id'],'cars_id'=>$res['id'],'status'=>0])->find();
                    if($stop){
                        $change_type=$stop->change_type;
                        switch ($change_type){
                            case 'day':
                            case 'date':
                                $res['stop']='报停-截至【'.$stop->date.'】';
                                break;
                            case 'hand':
                                $res['stop']='报停';
                                break;
                        }
                    }else{
                        $res['stop']='报停';
                    }
                }
            });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET','change-rules')]
    public function changeRules()
    {
        $id=$this->request->get('id');
        $rules=ParkingRules::where(['parking_id'=>$this->parking->id,'id'=>$id])->find();
        $this->success('',$rules);
    }

    #[Route('GET,JSON','recharge-log')]
    public function rechargeLog()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch('cars/recharge-log');
        }
        $type=$this->request->get('type');
        $this->relationField=['plate'];
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        if($type=='monthly'){
            $this->model=new ParkingMonthlyRecharge();
        }
        if($type=='stored'){
            $this->model=new ParkingStoredLog();
            $where[]=['pay_id','>',0];
        }
        $plate_number=$this->filter('plate');
        if($plate_number){
            $cars_id=ParkingPlate::where('plate_number','like','%'.$plate_number.'%')->column('cars_id');
            $where[]=['cars_id','in',$cars_id];
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['payunion','cars','plate'=>function($query){$query->limit(1);}])
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,JSON','download-recharge')]
    public function downloadRecharge()
    {
        //下载回调
        $this->callback=function ($row){
            $plates=[];
            foreach ($row['plate'] as $v){
                $plates[]=$v['plate_number'];
            }
            $row['plate']=implode(',',$plates);
            if(isset($row['starttime'])){
                $row['starttime']=date('Y-m-d',$row['starttime']);
            }
            if(isset($row['endtime'])){
                $row['endtime']=date('Y-m-d',$row['endtime']);
            }
            $row['payunion']['pay_type']=PayUnion::PAYTYPE[$row['payunion']['pay_type']];
            return $row;
        };
        return $this->_download();
    }

    #[Route('GET,JSON','logview')]
    public function logview()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $type=$this->request->get('type');
        $ids=$this->request->get('ids');
        if($type=='logs'){
            $this->model=new ParkingCarsLogs();
            $withlog=['admin','merch'];
        }
        if($type=='recharge'){
            $this->model=new ParkingMonthlyRecharge();
            $withlog=['payunion'];
        }
        if($type=='balance'){
            $this->model=new ParkingStoredLog();
            $withlog=['payunion'];
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        $where[]=['cars_id','=',$ids];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with($withlog)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }


    #[Route('POST','recharge')]
    public function recharge()
    {
        $cars_id=$this->request->post('cars_id');
        $money=$this->request->post('money/f');
        $starttime=$this->request->post('starttime');
        $endtime=$this->request->post('endtime');
        $change_type=$this->request->post('change_type');
        $remark=$this->request->post('remark');
        $cars=ParkingCars::with(['rules','plates'])->find($cars_id);
        if(!$cars || $cars->parking_id!=$this->parking->id){
            $this->error('车辆不存在');
        }
        if($cars->status=='hidden'){
            $this->error('车辆已被禁用');
        }
        if($money<=0){
            $this->error('充值金额不能小于0');
        }
        if($cars->rules_type=='monthly'){
            Db::startTrans();
            try{
                $payunion=PayUnion::underline(
                    $money,
                    PayUnion::ORDER_TYPE('停车月租缴费'),
                    ['parking_id'=>$this->parking->id],
                    $cars->plates[0]->plate_number.'停车月租缴费'
                );
                ParkingMonthlyRecharge::recharge($cars,$change_type,$payunion,$starttime,$endtime);
                $logremark="后台充值".$money.'元';
                if($remark){
                    $logremark.="，备注：".$remark;
                }
                ParkingCarsLogs::addLog($cars,$this->auth->id,$logremark);
                Db::commit();
            }catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        if($cars->rules_type=='stored'){
            Db::startTrans();
            try{
                ParkingStoredLog::addAdminLog($cars,$change_type,$money,$remark);
                ParkingCarsLogs::addLog($cars,$this->auth->id,$change_type=='add'?'充值'.$money.'元':'修改余额'.$money.'元');
                Db::commit();
            }catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        $this->success('充值成功');
    }

    #[Route('GET','rulesRemark')]
    public function rulesRemark()
    {
        $rules_id=$this->request->get('rules_id');
        $rules=ParkingRules::where(['parking_id'=>$this->parking->id,'id'=>$rules_id])->find();
        $this->success('',$rules->remark_list);
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $contact=$this->request->post('row.contact');
            $mobile=$this->request->post('row.mobile');
            $remark_line=$this->request->post('row.remark_line');
            $starttime=$this->request->post('row.starttime');
            $endtime=$this->request->post('row.endtime');
            $remark=$this->request->post('row.remark');
            $status=$this->request->post('row.status');
            $rules_id=$this->request->post('row.rules_id');
            $third_id=$this->request->post('row.third_id');
            $plates=$this->request->post('plates');
            $pay=$this->request->post('pay');
            $options=[
                'starttime'=>strtotime($starttime.' 00:00:00'),
                'endtime'=>strtotime($endtime.' 23:59:59'),
                'remark'=>$remark,
                'status'=>$status,
                'third_id'=>$third_id,
                'remark_line'=>$remark_line?json_encode($remark_line,JSON_UNESCAPED_UNICODE):null
            ];
            $rules=ParkingRules::where(['parking_id'=>$this->parking->id,'id'=>$rules_id])->find();
            if($third_id){
                $third=Third::find($third_id);
            }
            try {
                Db::startTrans();
                $cars=ParkingCars::addCars($rules,$contact,$mobile,$third_id?$third->user_id:null,$plates,$options);
                if($rules->rules_type==ParkingRules::RULESTYPE('月租车') && $pay){
                    $payunion=PayUnion::underline(
                        $pay,
                        PayUnion::ORDER_TYPE('停车月租缴费'),
                        ['parking_id'=>$cars->parking_id],
                        $plates[0]['plate_number'].'停车月租缴费'
                    );
                    (new ParkingMonthlyRecharge())->save([
                        'parking_id'=>$cars->parking_id,
                        'cars_id'=>$cars->id,
                        'rules_id'=>$cars->rules_id,
                        'pay_id'=>$payunion->id,
                        'money'=>$pay,
                        'starttime'=>$options['starttime'],
                        'endtime'=>$options['endtime']
                    ]);
                }
                if($rules->rules_type==ParkingRules::RULESTYPE('储值车') && $pay){
                    ParkingStoredLog::addAdminLog($cars,'add',$pay,$options['remark']);
                }
                $log='后台添加';
                if($pay){
                    $log.="，充值￥".$pay;
                }
                ParkingCarsLogs::addLog($cars,$this->auth->id,$log);
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('添加成功');
        }
        $this->assign('rules',ParkingRules::where(['parking_id'=>$this->parking->id,'rules_type'=>$this->rules_type,'status'=>'normal'])->column('title','id'));
        return $this->_add();
    }

    #[Route('GET,JSON','download')]
    public function download()
    {
        //下载回调
        $this->callback=function ($row){
            $plates=[];
            foreach ($row['plates'] as $v){
                $plates[]=$v['plate_number'];
            }
            $row['plates']=implode(',',$plates);
            $row['starttime']=date('Y-m-d',$row['starttime']);
            $row['endtime']=date('Y-m-d',$row['endtime']);
            return $row;
        };
        return $this->_download();
    }

    #[Route('GET,POST','import')]
    public function import()
    {
        $this->importFields=[
            'plates'=>'车辆',
            'monthly'=>'月卡类型',
            'day'=>'日租卡类型',
            'stored'=>'储值卡类型',
            'member'=>'会员卡类型',
            'occupat_number'=>'占位数',
            'vip'=>'VIP卡类型',
            'code'=>'车位编号',
            'contact'=>'车主姓名',
            'mobile'=>'手机号',
            'starttime'=>'开始日期',
            'endtime'=>'结束日期',
            'balance'=>'余额',
            'remark'=>'备注'
        ];
        $ruleslist=ParkingRules::where(['parking_id'=>$this->parking->id,'status'=>'normal'])->where('rules_type','<>','provisional')->select();
        if(empty($ruleslist)){
            $this->error('请先添加车辆规则');
        }
        //导入回调
        $this->callback=function ($row,&$success,&$error) use ($ruleslist){
            $row['monthly']=$row['monthly']??null;
            $row['day']=$row['day']??null;
            $row['stored']=$row['stored']??null;
            $row['member']=$row['member']??null;
            $row['vip']=$row['vip']??null;
            $rules=false;
            foreach ($ruleslist as $v){
                if(
                    $v['title']==$row['monthly'] ||
                    $v['title']==$row['day'] ||
                    $v['title']==$row['stored'] ||
                    $v['title']==$row['member'] ||
                    $v['title']==$row['vip']
                ){
                    $rules=$v;
                }
            }
            if(!$rules){
                return;
            }
            if(is_numeric($row['starttime'])){
                $row['starttime']=Date::excelToDateTimeObject($row['starttime']);
                $row['starttime']=strtotime($row['starttime']->format('Y-m-d 00:00:00'));
            }else{
                $row['starttime']=strtotime(date('Y-m-d 00:00:00',strtotime($row['starttime'])));
            }
            if(is_numeric($row['endtime'])){
                $row['endtime']=Date::excelToDateTimeObject($row['endtime']);
                $row['endtime']=strtotime($row['endtime']->format('Y-m-d 23:59:59'));
            }else{
                $row['endtime']=strtotime(date('Y-m-d 23:59:59',strtotime($row['endtime'])));
            }
            $plates=[];
            $options=[
                'code'=>isset($row['code'])?$row['code']:null,
                'starttime'=>$row['starttime'],
                'endtime'=>$row['endtime'],
                'balance'=>isset($row['balance'])?floatval($row['balance']):null,
                'remark'=>$row['remark'],
                'status'=>'normal',
            ];
            $plate_number_arr=explode(',',str_replace('，',',',$row['plates']));
            foreach ($plate_number_arr as $plate_number){
                $plate_number=strtoupper(trim($plate_number));
                $plate=[
                    'plate_number'=>$plate_number,
                    'plate_type'=>'blue',
                    'car_models'=>'small'
                ];
                $plates[]=$plate;
            }
            if($rules->rules_type==ParkingRules::RULESTYPE('月租车')){
                 if(isset($row['occupat_number']) && $row['occupat_number']>0){
                     $options['occupat_number']=$row['occupat_number'];
                 }else{
                     $options['occupat_number']=count($plates);
                 }
            }
            try{
                $cars=ParkingCars::addCars($rules,(string)$row['contact'],(string)$row['mobile'],null,$plates,$options);
                if($rules->rules_type==ParkingRules::RULESTYPE('储值车') && $options['balance']>0){
                    ParkingStoredLog::addAdminLog($cars,'add',$options['balance'],'导入储值卡并充值');
                }
                $success++;
            }catch (\Exception $e){
                $error[]=$e->getMessage();
            }
        };
        return $this->_import();
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if($this->request->isPost()){
            $ids = $this->request->get('ids');
            $plates=$this->request->post('plates');
            $row=$this->request->post("row/a");
            $row['starttime']=strtotime($row['starttime'].' 00:00:00');
            $row['endtime']=strtotime($row['endtime'].' 23:59:59');
            $row['remark_line']=isset($row['remark_line']) && $row['remark_line']?json_encode($row['remark_line'],JSON_UNESCAPED_UNICODE):null;
            $cars=ParkingCars::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
            try{
                Db::startTrans();
                ParkingCars::editCars($cars,$plates,$row);
                ParkingCarsLogs::addLog($cars,$this->auth->id,'后台编辑');
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('操作成功');
        }else{
            $ids = $this->request->get('ids');
            $cars=ParkingCars::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
            if($cars->plates_count<=10){
                $list=ParkingPlate::where(['parking_id'=>$this->parking->id,'cars_id'=>$cars->id])->select();
                $plates=[];
                foreach ($list as $plate){
                    $plates[]=[
                        'plate_number'=>$plate['plate_number'],
                        'plate_type'=>$plate['plate_type'],
                        'car_models'=>$plate['car_models'],
                    ];
                }
                $cars->plates=$plates;
            }
            $this->assign('rules',ParkingRules::where(['parking_id'=>$this->parking->id,'rules_type'=>$this->rules_type,'status'=>'normal'])->column('title','id'));
            return $this->_edit($cars);
        }
    }

    #[Route('GET,POST','occupat')]
    public function occupat()
    {
        $ids=$this->request->get('ids');
        $list=ParkingCarsOccupat::with(['records'])->where(['parking_id'=>$this->parking->id,'cars_id'=>$ids])->select();
        foreach ($list as $k=>$v){
            if(!$v['entry_time']){
                continue;
            }
            $v['provisional']=$v['entry_time']-$v['records']['entry_time'];
            $list[$k]['entry_time']=date('Y-m-d H:i',$v['entry_time']);
        }
        $this->assign('list',$list);
        return $this->fetch();
    }

    /**
     * 回收站
     */
    #[Route('GET,POST,JSON','recyclebin')]
    public function recyclebin($action='list')
    {
        switch ($action){
            case 'list':
                if (false === $this->request->isAjax()) {
                    $this->assign('search', 'contact,mobile');
                    $this->assign('columns', ['plates'=>'车牌号','rules_type_txt'=>'停车类型','contact'=>'车主姓名','mobile'=>'手机号']);
                    $this->assign('columnsType', ['plates'=>'text','rules_type_txt'=>'text','contact'=>'text','mobile'=>'text']);
                    return $this->fetch('common/recyclebin');
                }
                $searchValue=$this->request->post('searchValue');
                $cars_id=false;
                if($searchValue){
                    $cars_id=ParkingPlate::where(function ($query) use ($searchValue){
                        $query->where('plate_number','like','%'.$searchValue.'%');
                        $query->where('parking_id','=',$this->parking->id);
                    })->column('cars_id');
                }
                $where=function ($query) use ($cars_id,$searchValue){
                    $query->where('parking_id','=',$this->parking->id);
                    if($searchValue){
                        $str='contact like :contact or mobile like :mobile';
                        $param=['contact'=>'%'.$searchValue.'%','mobile'=>'%'.$searchValue.'%'];
                        if($cars_id){
                            $str.=' or id in (:cars_id)';
                            $param['cars_id']=implode(',',$cars_id);
                        }
                        $query->whereRaw($str,$param);
                    }
                };
                $limit=[
                    'page'  => $this->request->post('page/d',1),
                    'list_rows' => $this->request->post('limit/d',10)
                ];
                $list = $this->model
                    ->with(['plates'])
                    ->onlyTrashed()
                    ->where($where)
                    ->order('id desc')
                    ->paginate($limit);
                $rows=$list->items();
                foreach ($rows as $k=>$v){
                    $plate=array_map(function($item){
                        return $item['plate_number'];
                    },$v->plates->toArray());
                    $rows[$k]['plates']=implode(',',$plate);
                }
                $result = ['total' => $list->total(), 'rows' => $rows];
                return json($result);
            case 'restore':
                $ids=$this->request->param('ids');
                foreach ($ids as $id){
                    $row=$this->model->onlyTrashed()->find($id);
                    $plate_number=[];
                    foreach ($row->plates as $value){
                        $plate_number[]=$value['plate_number'];
                    }
                    $plate=ParkingPlate::where(function ($query) use ($id,$plate_number){
                        $prefix=getDbPrefix();
                        $query->whereIn('plate_number',$plate_number);
                        $query->where('cars_id','<>',$id);
                        $query->where('parking_id','=',$this->parking->id);
                        $query->whereRaw("cars_id in (select id from {$prefix}parking_cars where deletetime is null and parking_id={$this->parking->id})");
                    })->find();
                    if($plate){
                        $this->error('车牌号【'.$plate['plate_number'].'】已存在');
                    }
                    if($row && $row['parking_id']==$this->parking->id){
                        $row->restore();
                    }
                }
                $this->success();
            case 'destroy':
                $ids=$this->request->param('ids');
                foreach ($ids as $id){
                    $row=$this->model->onlyTrashed()->find($id);
                    if($row && $row['parking_id']==$this->parking->id){
                        $row->force()->delete();
                    }
                    ParkingPlate::where(['parking_id'=>$this->parking->id,'cars_id'=>$id])->delete();
                }
                $this->success();
            case 'restoreall':
                $this->model->onlyTrashed()->where(function ($query){
                    $prefix=getDbPrefix();
                    $query->where('deletetime','<>',null);
                    $query->where('parking_id','=',$this->parking->id);
                    $query->whereRaw("id not in (select cars_id FROM {$prefix}parking_plate where plate_number in(SELECT plate_number FROM (SELECT plate_number,count(1) as count FROM {$prefix}parking_plate where parking_id={$this->parking->id} GROUP BY plate_number HAVING count>1) t))");
                })->update(['deletetime'=>null]);
                $this->success();
            case 'clear':
                $prefix=getDbPrefix();
                Db::execute("delete from {$prefix}parking_cars where deletetime is not null and parking_id = {$this->parking->id}");
                Db::execute("delete from {$prefix}parking_plate where parking_id = {$this->parking->id} and cars_id not in (select id from {$prefix}parking_cars)");
                $this->success();
        }
    }
}
