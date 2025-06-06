<?php
declare (strict_types = 1);

namespace app\parking\controller;

use app\common\controller\ParkingBase;
use app\common\library\Email;
use app\common\model\PayUnion;
use app\common\service\msg\WechatMsg;
use think\annotation\route\Group;
use app\common\model\parking\ParkingInvoice;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("invoice")]
class Invoice extends ParkingBase
{

    protected function _initialize()
    {
        parent::_initialize();
        $this->assign('invoiceType',ParkingInvoice::INVOICE_TYPE);
        $this->model = new ParkingInvoice();
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        $where=[];
        $where[]=['parking_id', '=', $this->parking->id];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with($with)
            ->where($where)
            ->order('status asc,id desc')
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,JSON','orders')]
    public function orders()
    {
        if (false === $this->request->isAjax()) {
            $this->assign('orderType',PayUnion::ORDER_TYPE);
            return $this->fetch();
        }
        $ids=$this->request->get('ids');
        $invoice=ParkingInvoice::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
        [$where, $order, $limit, $with] = $this->buildparams();
        $list=PayUnion::whereIn('id',$invoice->pay_id)->order($order)->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        $ids=$this->request->get('ids');
        if (false === $this->request->isPost()) {
            $row=ParkingInvoice::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
            unset($row->file);
            $this->assign('row',$row);
            return $this->fetch();
        }
        $file=$this->request->post('row.file');
        if(!$file){
            $this->error('请上传发票');
        }
        $invoice=ParkingInvoice::where(['parking_id'=>$this->parking->id,'id'=>$ids])->find();
        try {
            Email::instance()->send($invoice->email,'云起停车-发票','发票下载链接：'.$file);
            Db::startTrans();
            $invoice->status=1;
            $invoice->file=$file;
            $invoice->save();
            PayUnion::whereIn('id',$invoice->pay_id)->update(['invoicing'=>2]);
            WechatMsg::successInvince($invoice);
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('操作成功');
    }
}