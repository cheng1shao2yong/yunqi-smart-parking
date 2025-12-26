<?php
declare (strict_types = 1);

namespace app\admin\controller\manage;

use app\admin\command\Queue as QueueCommand;
use app\common\controller\Backend;
use app\common\model\parking\ParkingAdmin;
use app\common\model\PayUnion;
use app\common\service\pay\Guotong;
use app\common\service\pay\Yibao;
use app\common\service\PayService;
use think\annotation\route\Group;
use app\admin\traits\Actions;
use app\common\model\manage\Parking as ParkingModel;
use app\common\model\manage\Property;
use think\annotation\route\Route;
use think\facade\Cache;
use think\facade\Session;

#[Group("manage/parking")]
class Parking extends Backend
{
    protected $noNeedRight=['queue','property'];

    use Actions{
        add as _add;
        edit as _edit;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = new ParkingModel();
        $this->recyclebinColumns=[
            "title"=>"名称",
            "contact"=>"联系人",
            "phone"=>"联系电话",
        ];
        $this->recyclebinColumnsType=[
            "title"=>"text",
            "contact"=>"text",
            "phone"=>"text",
        ];
        $this->assign('pay_type_handle',PayUnion::PAY_TYPE_HANDLE);
        $this->assign('pay_type_params',PayUnion::PAY_TYPE_PARAMS);
    }

    #[Route('GET,JSON','index')]
    public function index()
    {
        if (false === $this->request->isAjax()) {
            return $this->fetch();
        }
        if($this->request->post('selectpage')){
            return $this->selectpage();
        }
        $where=[];
        $area_id=$this->filter('area_id');
        if($area_id){
            $where[]=['province_id|city_id|area_id','=',$area_id];
        }
        [$where, $order, $limit, $with] = $this->buildparams($where);
        $list = $this->model
            ->with(['area','property'])
            ->where($where)
            ->order($order)
            ->paginate($limit)
            ->each(function ($item){
                $domain=get_domain('parking');
                $item->link=$domain."/login?parking=".$item->uniqid;
            });
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    #[Route('GET,JSON','property')]
    public function property()
    {
        $this->model=new Property();
        return $this->selectpage();
    }

    #[Route('GET','queue')]
    public function queue()
    {
        $timetxt=QueueCommand::$timetxt;
        $timetxt1=intval(file_get_contents($timetxt));
        sleep(2);
        $timetxt2=intval(file_get_contents($timetxt));
        $status=1;
        if($timetxt1==$timetxt2) {
            $status=0;
        }
        $this->success('',$status);
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $area=$this->request->post('row.area');
            $this->postParams=[
                'province_id'=>$area[0],
                'city_id'=>$area[1],
                'area_id'=>$area[2],
            ];
            $admin=$this->request->post('admin/a');
            $use_property=$this->request->post('use_property');
            if($use_property){
                $property_id=$this->request->post('row.property_id');
                $property=Property::find($property_id);
                if(!$property){
                    $this->error('集团账户不存在');
                }
            }else{
                $this->postParams['property_id']=null;
                $this->callback=function ($row) use ($admin){
                    $parkadmin=new ParkingAdmin();
                    $parkadmin->parking_id=$row->id;
                    $parkadmin->role='admin';
                    $parkadmin->rules='*';
                    $parkadmin->auth_rules='*';
                    $parkadmin->mobile_rules='*';
                    ParkingAdmin::addAdmin($row,$parkadmin,$admin);
                };
            }
        }
        $this->get_sub_merch_config();
        return $this->_add();
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if($this->request->isPost()){
            $area=$this->request->post('row.area');
            $this->postParams=[
                'province_id'=>$area[0],
                'city_id'=>$area[1],
                'area_id'=>$area[2],
            ];
            $use_property=$this->request->post('use_property');
            if($use_property){
                $property_id=$this->request->post('row.property_id');
                $property=Property::find($property_id);
                if(!$property){
                    $this->error('集团账户不存在');
                }
                $this->callback=function ($row){
                    ParkingAdmin::where(['role'=>'admin','parking_id'=>$row->id])->delete();
                };
            }else{
                $this->postParams['property_id']=null;
                $this->callback=function ($row){
                    $admin=$this->request->post('admin/a');
                    $parkadmin=ParkingAdmin::where(['role'=>'admin','parking_id'=>$row->id])->find();
                    if($parkadmin){
                        ParkingAdmin::editAdmin($row,$parkadmin,$admin);
                    }else{
                        $parkadmin=new ParkingAdmin();
                        $parkadmin->parking_id=$row->id;
                        $parkadmin->role='admin';
                        $parkadmin->rules='*';
                        $parkadmin->auth_rules='*';
                        $parkadmin->mobile_rules='*';
                        ParkingAdmin::addAdmin($row,$parkadmin,$admin);
                    }
                };
            }
            return $this->_edit();
        }else{
            $ids = $this->request->get('ids');
            $row = $this->model->find($ids);
            $row->area=[$row->province_id,$row->city_id,$row->area_id];
            if($row->property_id){
                $row->use_property=1;
            }
            $parkingadmin=ParkingAdmin::with(['admin'])->where(['role'=>'admin','parking_id'=>$ids])->find();
            if($parkingadmin){
                $row->admin=$parkingadmin->admin;
                $row->admin->musername=$row->admin->username;
                $row->admin->username=str_replace($row->uniqid.'-', '', $row->admin->username);
            }else{
                $row->admin=[
                    'username'=>'test',
                    'musername'=>$row->uniqid.'-test',
                    'password'=>'',
                ];
            }
            $this->get_sub_merch_config();
            return $this->_edit($row);
        }
    }

    private function get_sub_merch_config()
    {
        $folder=root_path().'app/common/service/pay/custom';
        $sub_merch_config=[];
        $files = scandir($folder);
        foreach ($files as $file) {
            if (is_file($folder . '/' . $file)) {
                $sub_merch_config[$file]='/common/service/pay/custom/'.$file;
            }
        }
        $this->assign('sub_merch_config',$sub_merch_config);
    }

    #[Route('POST','bind')]
    public function bind()
    {
        $parking_id=$this->request->post('parking_id');
        $token=md5(rand(10000,99999).time().$this->auth->id.$parking_id);
        Cache::set($token,$parking_id,60);
        $domain=get_domain('parking');
        $url=$domain."/login-by-admin?token={$token}";
        $this->success('',$url);
    }
}