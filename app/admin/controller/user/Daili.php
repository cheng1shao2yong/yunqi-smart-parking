<?php
declare (strict_types = 1);

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\model\Daili as DailiModel;
use app\common\model\DailiLog;
use app\common\model\DailiParking;
use think\annotation\route\Group;
use app\admin\traits\Actions;
use think\annotation\route\Route;

#[Group("user/daili")]
class Daili extends Backend
{
    use Actions;
    public function _initialize()
    {
        parent::_initialize();
        $this->model = new DailiModel();
    }

    #[Route('GET,JSON','parkings')]
    public function parkings($ids)
    {
        if($this->request->isAjax()){
            $this->model=new DailiParking();
            $where=[];
            $where[]=['daili_id','=',$ids];
            [$where, $order, $limit, $with] = $this->buildparams($where);
            $list = $this->model
                ->withJoin(['parking'])
                ->where($where)
                ->order($order)
                ->paginate($limit);
            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }else{
            return $this->fetch();
        }
    }

    #[Route('GET,POST','add-parking')]
    public function addParking($ids)
    {
        if($this->request->isPost()){
            $row=$this->request->post('row/a');
            $row['daili_id']=$ids;
            $dp=DailiParking::where('parking_id',$row['parking_id'])->find();
            if($dp){
                $this->error('该停车场已经被添加过了');
            }
            (new DailiParking())->save($row);
            $count=DailiParking::where('daili_id',$ids)->count();
            DailiModel::where('id',$ids)->update(['parking'=>$count]);
            $this->success('添加成功');
        }else{
            return $this->fetch();
        }
    }

    #[Route('GET,POST','del-parking')]
    public function delParking()
    {
        $ids = $this->request->post("ids");
        $dp=DailiParking::find($ids);
        if(!$dp){
            $this->error('该记录不存在');
        }
        $daili_id=$dp->daili_id;
        $dp->delete();
        $count=DailiParking::where('daili_id',$daili_id)->count();
        DailiModel::where('id',$daili_id)->update(['parking'=>$count]);
        $this->success('删除成功');
    }

    #[Route('GET,JSON','detail')]
    public function detail($ids)
    {
        if($this->request->isAjax()){
            $this->model=new DailiLog();
            $where=[];
            $where[]=['daili_id','=',$ids];
            [$where, $order, $limit, $with] = $this->buildparams($where);
            $list = $this->model
                ->with(['parking'])
                ->where($where)
                ->order($order)
                ->paginate($limit);
            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }else{
            return $this->fetch();
        }
    }
}