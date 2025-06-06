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
use app\common\library\TrafficManagement;
use app\common\model\AuthGroup;
use app\common\model\AuthRule;
use app\common\model\parking\ParkingAdmin;
use app\common\model\Third;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("admin")]
class Admin extends ParkingBase
{
    use Actions{
        add as _add;
        edit as _edit;
        del as _del;
    }

    public function _initialize()
    {
        parent::_initialize();
        $this->model=new ParkingAdmin();
        $auth=[];
        foreach (ParkingAdmin::AUTH as $value){
            $auth[$value['id']]=$value['name'];
        }
        $this->assign('xauth',$auth);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            $this->assign('usertype',ParkingAdmin::USER_TYPE);
            $this->assign('uniqid',$this->parking->uniqid);
            return $this->fetch();
        }
        if($this->request->post('selectpage')){
            return $this->selectpage();
        }
        $where=[];
        $where[]=['parking_id','=',$this->parking->id];
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $third=[];
        $list = $this->model
            ->with(['admin'])
            ->where($where)
            ->order($order)
            ->paginate($limit)
            ->each(function ($row) use (&$third){
                $third[]=$row['admin']['third_id'];
            });
        $r=[];
        $thirdlist=Third::whereIn('id',$third)->select();
        foreach ($list->items() as $value){
            $value['admin']['username']=str_replace($this->parking->uniqid.'-','',$value['admin']['username']);
            foreach ($thirdlist as $tx){
                if($tx->id==$value['admin']['third_id']){
                    $value['admin']['openname']=$tx->openname;
                    $value['admin']['avatar']=$tx->avatar;
                }
            }
            $r[]=$value;
        }
        $result = ['total' => $list->total(), 'rows' => $r];
        return json($result);
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if (false === $this->request->isAjax()) {
            $usertype=ParkingAdmin::USER_TYPE;
            unset($usertype['admin']);
            $this->assign('usertype',$usertype);
            $this->assign('treedata',$this->getGroupData());
            return $this->fetch();
        }
        $row=$this->request->post('row/a');
        $admin=$this->request->post('admin/a');
        if($row['role']=='admin'){
            $this->error('不能添加管理员');
        }
        if(!$row['auth_rules']){
            $this->error('请选择电脑端权限');
        }
        Db::startTrans();
        try{
            $parkadmin=new ParkingAdmin();
            $parkadmin->parking_id=$this->parking->id;
            $parkadmin->role=$row['role'];
            $parkadmin->rules=$row['rules'];
            $parkadmin->auth_rules=$row['auth_rules'];
            $parkadmin->mobile_rules=empty($row['mobile_rules'])?'':implode(',',$row['mobile_rules']);
            ParkingAdmin::addAdmin($this->parking,$parkadmin,$admin);
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

    private function parseRules($groupdata,$rules)
    {
        $r=[];
        $rules=explode(',',$rules);
        foreach ($groupdata as $v1){
            foreach ($v1['childlist'] as $v2){
                if(!empty($v2['childlist'])){
                    foreach ($v2['childlist'] as $v3){
                        if(in_array($v3['id'],$rules)){
                            $r[]=$v3['id'];
                        }
                    }
                }else{
                    if(in_array($v2['id'],$rules)){
                        $r[]=$v2['id'];
                    }
                }
            }
        }
        return implode(',',$r);
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if (false === $this->request->isAjax()) {
            $usertype=ParkingAdmin::USER_TYPE;
            unset($usertype['admin']);
            $groupdata=$this->getGroupData();
            $this->assign('usertype',$usertype);
            $this->assign('treedata',$groupdata);
            $ids=$this->request->get('ids');
            $parkingadmin=ParkingAdmin::with(['admin'])->where('id',$ids)->find();
            $parkingadmin->admin->username=str_replace($this->parking->uniqid.'-','',$parkingadmin->admin->username);
            $parkingadmin->mobile_rules=explode(',',$parkingadmin->mobile_rules);
            $parkingadmin->rules=$this->parseRules($groupdata,$parkingadmin->auth_rules);
            return $this->_edit($parkingadmin);
        }
        $row=$this->request->post('row/a');
        $admin=$this->request->post('admin/a');
        if($row['role']=='admin'){
            $this->error('不能添加管理员');
        }
        if(!$row['auth_rules']){
            $this->error('请选择电脑端权限');
        }
        Db::startTrans();
        try{
            $parkadmin=ParkingAdmin::find($row['id']);
            $parkadmin->role=$row['role'];
            $parkadmin->rules=$row['rules'];
            $parkadmin->auth_rules=$row['auth_rules'];
            $parkadmin->mobile_rules=empty($row['mobile_rules'])?'':implode(',',$row['mobile_rules']);
            ParkingAdmin::editAdmin($this->parking,$parkadmin,$admin);
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

    #[Route('GET,POST','del')]
    public function del()
    {
        $ids=$this->request->post('ids');
        $parkingadmin=ParkingAdmin::whereIn('id',$ids)->select();
        foreach ($parkingadmin as $value){
            \app\common\model\Admin::where('id',$value->admin_id)->delete();
            $value->delete();
        }
        $this->success();
    }

    private function getGroupData()
    {
        $ruleids=explode(',',AuthGroup::find(3)->auth_rules);
        $list=AuthRule::getRuleList($ruleids,31);
        return $list;
    }
}
