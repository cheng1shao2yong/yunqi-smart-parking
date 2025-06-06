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

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\model\Attachment;
use app\common\model\PlateBinding;
use think\annotation\route\Group;
use app\admin\traits\Actions;
use think\annotation\route\Route;

#[Group("plate")]
class Plate extends Backend
{
    use Actions;

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = new PlateBinding();
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        [$where, $order, $limit, $with] = $this->buildparams();
        $list = $this->model
            ->with(['user'])
            ->where($where)
            ->order('status asc,id desc')
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('POST,GET','auth')]
    public function auth()
    {
        if (false === $this->request->isPost()) {
            $ids = $this->request->get('ids');
            $row = $this->model->with(['user'])->find($ids);
            if(!$row){
                $this->error('找不到记录');
            }
            if($row->status==1){
                $this->error('该认证已经审核通过了');
            }
            $this->assign('row',$row);
            return $this->fetch();
        }
        $ids = $this->request->post('ids');
        $auth = $this->request->post('auth');
        $bind=PlateBinding::where('id',$ids)->find();
        $sha1=substr($bind->licence,strpos($bind->licence,'sha1=')+5);
        $list=Attachment::where('sha1',$sha1)->select();
        foreach ($list as $item) {
            $classname=config('filesystem.disks')[$item->storage]['class'];
            $classname::deleteFile($item);
            $item->delete();
        }
        if($auth){
            $bind->status=1;
            $bind->save();
        }else{
            $bind->delete();
        }
        $this->success();
    }
}

