<?php
declare (strict_types = 1);

namespace app\parking\controller;

use app\common\controller\ParkingBase;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantLog;
use app\common\model\parking\ParkingMerchantSetting;
use app\common\model\parking\ParkingMerchantUser;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRules;
use app\common\model\PayUnion;
use think\annotation\route\Group;
use app\parking\traits\Actions;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("merchant")]
class Merchant extends ParkingBase
{

    use Actions{
        add as _add;
        edit as _edit;
        download as _download;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = new ParkingMerchant();
        $this->assign('settleType',ParkingMerchant::SETTLE_TYPE);
        $this->assign('payType',PayUnion::PAYTYPE);
        $this->assign('logType',ParkingMerchantLog::LOGTYPE);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            $this->assign('coupon',ParkingMerchantCoupon::where('parking_id',$this->parking->id)->column('title','id'));
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        if($this->request->post('selectpage')){
            return $this->selectpage($where);
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $third=[];
        $list = $this->model
            ->with(['user'])
            ->where($where)
            ->order($order)
            ->paginate($limit)
            ->each(function ($row) use (&$third){
                foreach ($row->user as $value){
                    $third[$value->third_id]=1;
                }
            });
        $third=array_keys($third);
        $thirdlist=Db::name('third')->whereIn('id',$third)->column('id,avatar,openname','id');
        $list->each(function ($row) use ($thirdlist){
            $third=[];
            foreach ($row->user as $value){
                if($value->third_id){
                    $third[]=$thirdlist[$value->third_id];
                }
            }
            $row->third=$third;
        });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if ($this->request->isPost()) {
            $this->postParams['parking_id']=$this->parking->id;
            $password=$this->request->post('row.password');
            $salt = str_rand(4);
            $this->postParams['salt']=$salt;
            $this->postParams['password']=md5(md5($password) . $salt);
            $this->postParams['username']=$this->parking->uniqid.'-'.$this->request->post('row.username');
            $this->callback=function ($row){
                //处理停车券设置
                $cinfo=ParkingMerchantCoupon::where(['parking_id'=>$this->parking->id])->column('title','id');
                $coupon=[];
                foreach (explode(',',$row->coupon) as $coupon_id){
                    $coupon[]=[
                        'parking_id'=>$row->parking_id,
                        'merch_id'=>$row->id,
                        'coupon_id'=>$coupon_id,
                        'coupon_title'=>$cinfo[$coupon_id]
                    ];
                }
                (new ParkingMerchantSetting())->saveAll($coupon);
                //处理登录微信
                $third_id=$this->request->post('third_id');
                if($third_id){
                    $third_id=explode(',',$third_id);
                    $insert=[];
                    foreach ($third_id as $value){
                        $insert[]=[
                            'merch_id'=>$row->id,
                            'parking_id'=>$this->parking->id,
                            'third_id'=>$value
                        ];
                    }
                    (new ParkingMerchantUser())->saveAll($insert);
                }
            };
        }else{
            $this->assign('coupon',ParkingMerchantCoupon::where('parking_id',$this->parking->id)->column('title','id'));
            $this->assign('uniqid',$this->parking->uniqid);
            $this->assign('day',ParkingRules::where(['parking_id'=>$this->parking->id,'rules_type'=>'day'])->column('title','id'));
        }
        return $this->_add();
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if ($this->request->isPost()) {
            $this->postParams['parking_id']=$this->parking->id;
            $password=$this->request->post('row.password');
            if($password){
                $salt = str_rand(4);
                $this->postParams['salt']=$salt;
                $this->postParams['password']=md5(md5($password) . $salt);
            }else{
                $password=ParkingMerchant::where('id',$this->request->get('ids'))->column('password');
                $this->postParams['password']=$password[0];
            }
            $this->postParams['username']=$this->parking->uniqid.'-'.$this->request->post('row.username');
            $this->callback=function ($row){
                //处理停车券设置
                $coupon=[];
                $cinfo=ParkingMerchantCoupon::where(['parking_id'=>$this->parking->id])->column('title','id');
                $setting=ParkingMerchantSetting::where(['parking_id'=>$this->parking->id,'merch_id'=>$row->id])->select();
                foreach (explode(',',$row->coupon) as $coupon_id){
                    foreach ($setting as $value){
                        if($value->coupon_id==$coupon_id){
                            $coupon[]=[
                                'parking_id'=>$row->parking_id,
                                'merch_id'=>$row->id,
                                'coupon_id'=>$coupon_id,
                                'coupon_title'=>$cinfo[$coupon_id],
                                'limit_send'=>$value->limit_send,
                                'limit_type'=>$value->limit_type,
                                'limit_number'=>$value->limit_number,
                                'limit_money'=>$value->limit_money,
                                'settle_type'=>$value->settle_type,
                                'settle_money'=>$value->settle_money,
                            ];
                            continue 2;
                        }
                    }
                    $coupon[]=[
                        'parking_id'=>$row->parking_id,
                        'merch_id'=>$row->id,
                        'coupon_id'=>$coupon_id,
                        'coupon_title'=>$cinfo[$coupon_id]
                    ];
                }
                ParkingMerchantSetting::where(['parking_id'=>$row->parking_id,'merch_id'=>$row->id])->delete();
                (new ParkingMerchantSetting())->saveAll($coupon);
                //处理登录微信
                ParkingMerchantUser::where(['merch_id'=>$row->id,'parking_id'=>$this->parking->id])->delete();
                $third_id=$this->request->post('third_id');
                if($third_id){
                    $third_id=explode(',',$third_id);
                    $insert=[];
                    foreach ($third_id as $value){
                        $insert[]=[
                            'merch_id'=>$row->id,
                            'parking_id'=>$this->parking->id,
                            'third_id'=>$value
                        ];
                    }
                    (new ParkingMerchantUser())->saveAll($insert);
                }
            };
            return $this->_edit();
        }else{
            $ids=$this->request->get('ids');
            $mer=ParkingMerchant::with(['user'])->where('id',$ids)->find();
            $third=[];
            if(count($mer['user'])>0){
                foreach ($mer['user'] as $v){
                    $third[]=$v['third_id'];
                }
            }
            $mer->third_id=implode(',',$third);
            $mer->username=str_replace($this->parking->uniqid.'-','',$mer->username);
            $this->assign('uniqid',$this->parking->uniqid);
            $this->assign('coupon',ParkingMerchantCoupon::where('parking_id',$this->parking->id)->column('title','id'));
            $this->assign('day',ParkingRules::where(['parking_id'=>$this->parking->id,'rules_type'=>'day'])->column('title','id'));
            return $this->_edit($mer);
        }
    }

    #[Route('POST','recharge')]
    public function recharge()
    {
        $merch_id=$this->request->post('merch_id');
        $money=$this->request->post('money/f');
        $time=$this->request->post('time/d');
        $change_type=$this->request->post('change_type');
        $remark=$this->request->post('remark');
        $merch=ParkingMerchant::where(['id'=>$merch_id,'parking_id'=>$this->parking->id])->find();
        if(!$merch || $merch->parking_id!=$this->parking->id){
            $this->error('商户不存在');
        }
        if($merch->status=='hidden'){
            $this->error('商户已被禁用');
        }
        Db::startTrans();
        try{
            $pay_id=null;
            if($change_type=='add'){
                $payunion=PayUnion::underline(
                    $money,
                    PayUnion::ORDER_TYPE('商户充值'),
                    ['parking_id'=>$merch->parking_id],
                    '商户【'.$merch->merch_name.'】后台充值'
                );
                $pay_id=$payunion->id;
            }
            $change=$money;
            if($merch->settle_type=='time'){
                $change=$time;
            }
            ParkingMerchantLog::addAdminLog($merch,$change_type,$change,$remark,$pay_id);
            Db::commit();
        }catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('充值成功');
    }

    #[Route('GET,JSON','setting')]
    public function setting()
    {
        if($this->request->isAjax()){
            $postdata=$this->request->post();
            foreach ($postdata as $key=>$value){
                if($value['parking_id']!=$this->parking->id){
                    unset($postdata[$key]);
                }
            }
            (new ParkingMerchantSetting())->saveAll($postdata);
            $this->success();
        }
        $ids=$this->request->get('ids');
        $coupon=ParkingMerchantSetting::where(['parking_id'=>$this->parking->id,'merch_id'=>$ids])->select();
        $merchant=ParkingMerchant::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
        $this->assign('coupon',$coupon);
        $this->assign('merchant',$merchant);
        return $this->fetch();
    }

    #[Route('GET,JSON','logview')]
    public function logview()
    {
        $ids=$this->request->get('ids');
        $merchant=ParkingMerchant::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
        if (false === $this->request->isAjax()) {
            $this->assign('merchant',$merchant);
            return $this->fetch();
        }
        $this->model=new ParkingMerchantLog();
        $type=$this->request->get('type');
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        $where[]=['merch_id','=',$ids];
        if($type=='bill'){
            $plate_number=$this->filter('records.plate_number');
            if($plate_number){
                $ids=ParkingRecords::where(['plate_number'=>$plate_number,'parking_id'=>$this->parking->id])->column('id');
                $where[]=["records_id","in",$ids];
            }
            $where[]=['log_type','=','records'];
        }
        if($type=='recharge'){
            $where[]=['pay_id','<>',null];
        }
        if($type=='balance'){

        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['payunion','records'])
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $sum = $this->model ->where($where)->sum("change");
        if($merchant->settle_type=='time'){
            $minits=$sum%60;
            $summary="商户扣款-".intval($sum/60)."小时".$minits."分";
        }else{
            $summary="商户扣款-￥".$sum;
        }
        $result = ['total' => $list->total(), 'rows' => $list->items(),'summary'=>$summary];
        return json($result);
    }

    #[Route('GET,JSON','downlog')]
    public function downlog()
    {
        $ids=$this->request->get('ids');
        $merchant=ParkingMerchant::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
        $this->callback=function ($row) use ($merchant){
            $type=$this->request->get('type');
            if($type=='balance' || $type=='recharge'){
                if($row['payunion']){
                    $row['payunion']['pay_type']=PayUnion::PAYTYPE[$row['payunion']['pay_type']];
                }
            }
            if($type=='bill'){
                $row['records']['parking_time']=formatTime($row['records']['exit_time']-$row['records']['entry_time']);
            }
            if($merchant->settle_type=='time'){
                if($row['before']){
                    $row['before']=$this->parseHoures($row['before']);
                }
                if($row['after']){
                    $row['after']=$this->parseHoures($row['after']);
                }
                if($row['change']){
                    $row['change']=$this->parseHoures($row['change']);
                }
            }
            return $row;
        };
        return $this->_download();
    }

    private function parseHoures($data)
    {
        if($data>=0){
            return $data.'分钟';
        }else{
            $data=-$data;
            return '欠'.$data.'分钟';
        }
    }

    #[Route('GET,POST,JSON','recyclebin')]
    public function recyclebin($action='list')
    {
        switch ($action){
            case 'list':
                if (false === $this->request->isAjax()) {
                    $this->assign('search', 'merch_name,phone');
                    $this->assign('columns', [
                        "merch_name"=>"商户名称",
                        "phone"=>"联系电话",
                    ]);
                    $this->assign('columnsType', [
                        "merch_name"=>"text",
                        "phone"=>"text"
                    ]);
                    return $this->fetch('common/recyclebin');
                }
                $where=[];
                $where[]=['parking_id','=',$this->parking->id];
                [$where, $order, $limit, $with] = $this->buildparams($where);
                $list = $this->model
                    ->onlyTrashed()
                    ->where($where)
                    ->order($order)
                    ->paginate($limit);
                $result = ['total' => $list->total(), 'rows' => $list->items()];
                return json($result);
            case 'restore':
                $ids=$this->request->param('ids');
                foreach ($ids as $id){
                    $row=$this->model->onlyTrashed()->find($id);
                    if($row && $row->parking_id==$this->parking->id){
                        $row->restore();
                    }
                }
                $this->success();
            case 'destroy':
                $ids=$this->request->param('ids');
                foreach ($ids as $id){
                    $row=$this->model->onlyTrashed()->find($id);
                    if($row && $row->parking_id==$this->parking->id){
                        $row->force()->delete();
                    }
                }
                ParkingMerchantUser::whereIn('merch_id',$ids)->where('parking_id',$this->parking->id)->delete();
                ParkingMerchantSetting::whereIn('merch_id',$ids)->where('parking_id',$this->parking->id)->delete();
                ParkingMerchantLog::whereIn('merch_id',$ids)->where('parking_id',$this->parking->id)->delete();
                $this->success();
            case 'restoreall':
                $this->model->onlyTrashed()->where('deletetime','<>',null)->where('parking_id','=',$this->parking->id)->update(['deletetime'=>null]);
                $this->success();
            case 'clear':
                $prefix=getDbPrefix();
                Db::execute('delete from '.$prefix.'parking_merchant_log where merch_id in (select id from '.$this->model->getTable().' where deletetime is not null and parking_id = '.$this->parking->id.')');
                Db::execute('delete from '.$prefix.'parking_merchant_setting where merch_id in (select id from '.$this->model->getTable().' where deletetime is not null and parking_id = '.$this->parking->id.')');
                Db::execute('delete from '.$prefix.'parking_merchant_user where merch_id in (select id from '.$this->model->getTable().' where deletetime is not null and parking_id = '.$this->parking->id.')');
                Db::execute('delete from '.$this->model->getTable().' where deletetime is not null and parking_id = '.$this->parking->id);
                $this->success();
        }
    }
}