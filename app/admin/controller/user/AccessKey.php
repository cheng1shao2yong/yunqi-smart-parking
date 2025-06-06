<?php
declare (strict_types = 1);

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\model\Admin;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingAdmin;
use app\common\model\parking\ParkingMerchant;
use app\common\model\User;
use think\annotation\route\Group;
use app\admin\traits\Actions;
use app\common\model\Accesskey as AccesskeyModel;
use think\annotation\route\Route;
use think\facade\Db;

#[Group("user/accesskey")]
class AccessKey extends Backend
{
    use Actions{
        add as _add;
        edit as _edit;
        del as _del;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model=new AccesskeyModel();
    }

    #[Route('JSON','parking')]
    public function parking()
    {
        $this->model=new Parking();
        return $this->selectpage();
    }

    #[Route('JSON','merchant')]
    public function merchant()
    {
        $this->model=new ParkingMerchant();
        return $this->selectpage();
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $username=str_rand(32);
            $password=md5($username.time());
            try{
                Db::startTrans();
                $user=User::createNewUser($username,'API用户','','','',$password);
                $data=[
                    'user_id'=>$user->id,
                    'access_key'=>$username,
                    'access_secret'=>$password
                ];
                $data=array_merge($data,$this->request->post('row/a'));
                if($data['access_type']=='parking'){
                    unset($data['merch_id']);
                }
                if($data['access_type']=='merchant'){
                    unset($data['parking_id']);
                }
                if($data['access_type']=='charge'){
                    unset($data['parking_id']);
                    unset($data['merch_id']);
                }
                AccesskeyModel::create($data);
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success();
        }
        return $this->_add();
    }

    #[Route('GET,POST','del')]
    public function del()
    {
        $ids=$this->request->param('ids');
        $list = $this->model->where('id', 'in', $ids)->select();
        foreach ($list as $value){
            User::destroy($value->user_id,true);
            $value->force()->delete();
        }
        $this->success();
    }
}