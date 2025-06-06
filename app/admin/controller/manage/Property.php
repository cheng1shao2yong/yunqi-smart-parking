<?php
declare (strict_types = 1);

namespace app\admin\controller\manage;

use app\common\controller\Backend;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\property\PropertyAdmin;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use think\annotation\route\Group;
use app\admin\traits\Actions;
use app\common\model\manage\Property as PropertyModel;
use think\annotation\route\Route;

#[Group("manage/property")]
class Property extends Backend
{
    use Actions{
        add as _add;
        edit as _edit;
        import as _import;
    }

    protected function _initialize()
    {
        parent::_initialize();
        $this->model = new PropertyModel();
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
    }

    #[Route('GET,POST','add')]
    public function add()
    {
        if($this->request->isPost()){
            $admin=$this->request->post('admin/a');
            $this->callback=function ($row) use ($admin){
                $properadmin=new PropertyAdmin();
                $properadmin->property_id=$row->id;
                $properadmin->role='admin';
                $properadmin->rules='*';
                $properadmin->auth_rules='*';
                $properadmin->mobile_rules='*';
                PropertyAdmin::addAdmin($row,$properadmin,$admin);
            };
        }
        return $this->_add();
    }

    #[Route('GET,POST','import')]
    public function import()
    {
         $this->error('暂不支持导入');
         $this->importFields=[
            'plate_number'=>'车牌号码',
            'entry_time'=>'入场时间'
        ];
        $this->callback=function ($row,&$success,&$error){
            $row['entry_time']=Date::excelToDateTimeObject($row['entry_time']);
            $row['entry_time']=strtotime($row['entry_time']->format('Y-m-d H:i:s'));
            $row['parking_id']=73;
            $row['parking_title']='中民七号院停车场';
            $row['plate_number']=trim($row['plate_number']);
            $row['plate_type']='blue';
            $row['rules_type']='provisional';
            $row['rules_id']=318;
            $row['entry_type']='normal';
            $row['entry_barrier']=243;
            $row['status']=0;
            $row['createtime']=1734588888;
            $row['updatetime']=1734588888;
            ParkingRecords::create($row);
            return false;
       };
       return $this->_import();
    }

    #[Route('GET,POST','edit')]
    public function edit()
    {
        if($this->request->isPost()){
            $admin=$this->request->post('admin/a');
            $this->callback=function ($row) use ($admin){
                $propertyadmin=PropertyAdmin::where(['role'=>'admin','property_id'=>$row->id])->find();
                PropertyAdmin::editAdmin($row,$propertyadmin,$admin);
            };
            return $this->_edit();
        }else{
            $ids = $this->request->get('ids');
            $row = $this->model->find($ids);
            $properadmin=PropertyAdmin::with(['admin'])->where(['role'=>'admin','property_id'=>$ids])->find();
            if($properadmin){
                $row->admin=$properadmin->admin;
                $row->admin->musername=$row->admin->username;
                $row->admin->username=str_replace($row->uniqid.'-', '', $row->admin->username);
            }else{
                $row->admin=[];
            }
            return $this->_edit($row);
        }
    }
}