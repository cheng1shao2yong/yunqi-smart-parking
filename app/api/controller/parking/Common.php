<?php
declare (strict_types = 1);

namespace app\api\controller\parking;

use app\common\model\Admin;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingAdmin;
use app\common\model\parking\ParkingLog;
use app\common\model\property\PropertyAdmin;
use app\common\model\Third;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\facade\Cache;

#[Group("parking/common")]
class Common extends Base
{
    protected $noNeedParkingLogin='*';

    #[Get('info')]
    public function info()
    {
        $app=site_config("basic");
        $app['logo']=formatImage($app['logo']);
        $app['logo_white']=formatImage($app['logo_white']);
        $this->success('',$app);
    }

    #[Get('list')]
    public function list()
    {
        $thirds=Third::where('user_id',$this->auth->id)->column('id');
        $r=[];
        $loginParkingAdmin=$this->auth->getParkingAdmin();
        $loginPropertyAdmin=$this->auth->getPropertyAdmin();
        $list1=Admin::where('groupids',2)->whereIn('third_id',$thirds)->select()->toArray();
        foreach ($list1 as $value){
            $propertyadmin=PropertyAdmin::withJoin(['property'],'left')->where('admin_id',$value['id'])->find();
            if(!$propertyadmin){
                continue;
            }
            $value['property']=$propertyadmin->property;
            unset($value['password']);
            unset($value['salt']);
            if($loginPropertyAdmin && time()<$loginPropertyAdmin['expire'] && $loginPropertyAdmin['property_id']==$value['property']->id){
                $value['active']=1;
            }
            $r[]=$value;
        }
        $list2=Admin::where('groupids',3)->whereIn('third_id',$thirds)->select()->toArray();
        foreach ($list2 as $value){
            $parkingadmin=ParkingAdmin::withJoin(['parking'],'left')->where('admin_id',$value['id'])->find();
            if(!$parkingadmin){
                continue;
            }
            $value['parking']=$parkingadmin->parking;
            unset($value['password']);
            unset($value['salt']);
            if($loginParkingAdmin && time()<$loginParkingAdmin['expire'] && $loginParkingAdmin['parking_id']==$value['parking']->id){
                $value['active']=1;
            }
            $r[]=$value;
        }
        $this->success('',$r);
    }

    #[Get('xieyi')]
    public function xieyi()
    {
        $yonghu=<<<EOF
欢迎使用重庆泊联智行科技有限公司（以下简称"乙方"）提供的智慧停车服务。请您仔细阅读本协议，点击"同意"即表示您已充分理解并接受以下条款：

一、服务内容
1.1 乙方通过车牌识别系统为甲方停车场提供车辆进出管理、停车费计算、线上支付等服务，具体功能以APP实际展示为准。
1.2 系统自动识别准确率承诺≥99.9%，因环境因素（如极端天气、遮挡等）导致的识别误差不视为违约。

二、用户义务
2.1 您需确保登记的车牌信息真实有效，使用虚假信息导致的损失由您自行承担。
2.2 请勿故意遮挡、污损车牌或干扰识别设备运行，否则乙方有权终止服务并追究责任。
2.3 车辆通过跟车，遥控设备控制开闸等非系统原因导致车辆没有支付，产生车辆欠费可以通过追逃功能对车主进行追缴，但本系统不会对追缴结果负责。

三、费用结算
3.1 停车费标准由停车场管理方设定，APP实时显示计费信息。
3.2 支付方式：通过第三方支付平台（微信/支付宝/银联）完成，手续费0.6%（具体以合同为准）由系统自动扣除。
3.3 电子发票可在"订单记录"中申领，数据保留6个月供查询。

四、隐私保护
4.1 乙方仅收集实现服务必需的车牌、进出场时间等数据，未经授权不得向第三方披露。
4.2 为提升服务质量，系统可能匿名化使用行车轨迹等数据，您可通过【设置】-【隐私管理】选择关闭。

五、免责条款
5.1 因以下情况导致服务中断，乙方不承担责任：
- 停电、网络故障等不可抗力
- 用户设备不符合最低系统要求
- 用户未及时更新APP版本
5.2 本服务不包含车辆保管责任，请勿在车内留置贵重物品。

六、违约责任
6.1 如因乙方系统故障导致多收费，经核实后将在3个工作日内退还差额。
6.2 用户恶意逃费或破坏设备，乙方有权追偿损失并暂停账户功能。

七、协议变更与终止
7.1 乙方将通过APP公告或短信通知协议变更，继续使用视为接受修改。
7.2 您可随时注销账户，但需结清所有费用。

八、争议解决
本协议解释权归乙方所有，争议应协商解决，协商不成向乙方所在地人民法院提起诉讼。
EOF;
        $yinsi=<<<EOF
本指引是泊联智行停车小程序开发者 重庆泊联智行科技有限公司（以下简称“开发者”）为处理你的个人信息而制定。

一、开发者处理的信息
根据法律规定，开发者仅处理实现小程序功能所必要的信息。
1.1 开发者将在获取你的明示同意后，访问你的摄像头，用途是【停车场管理员识别区分不同的出口二维码】
1.2 开发者将在获取你的明示同意后，收集你的微信昵称、头像，用途是【给车主微信绑定月卡，储值卡】

二、你的权益
2.1 关于访问你的摄像头，你可以通过以下路径：小程序主页右上角“…”—“设置”—点击特定信息—点击“不允许”，撤回对开发者的授权。
2.2 关于你的个人信息，你可以通过以下方式与开发者联系，行使查阅、复制、更正、删除等法定权利。
若你在小程序中注册了账号，你可以通过邮箱85556713@qq.com与开发者联系，申请注销你在小程序中使用的账号。在受理你的申请后，开发者承诺在十五个工作日内完成核查和处理，并按照法律法规要求处理你的相关信息。

三、开发者对信息的存储
开发者承诺，除法律法规另有规定外，开发者对你的信息的保存期限应当为实现处理目的所必要的最短时间。

四、信息的使用规则
开发者将会在本指引所明示的用途内使用收集的信息
如开发者使用你的信息超出本指引目的或合理范围，开发者必须在变更使用目的或范围前，再次以电话方式告知并征得你的明示同意。

五、信息对外提供
开发者承诺，不会主动共享或转让你的信息至任何第三方，如存在确需共享或转让时，开发者应当直接征得或确认第三方征得你的单独同意。
开发者承诺，不会对外公开披露你的信息，如必须公开披露时，开发者应当向你告知公开披露的目的、披露信息的类型及可能涉及的信息，并征得你的单独同意。

你认为开发者未遵守上述约定，或有其他的投诉建议、或未成年人个人信息保护相关问题，可通过邮箱85556713@qq.com与开发者联系；或者向微信进行投诉。
EOF;
        $this->success('',[$yonghu,$yinsi]);
    }

    #[Post('login')]
    public function login()
    {
        $username=$this->request->post('username');
        $password=$this->request->post('password');
        $type=$this->request->post('type');
        $groupids=[
            'property'=>2,
            'parking'=>3
        ];
        $loginnumber=Cache::get('parking-admin-'.$this->auth->id);
        if($loginnumber>5){
            $this->error('登录次数过多，请稍后再试');
        }
        $admin=Admin::where(['username'=>$username,'groupids'=>$groupids[$type]])->find();
        if(!$admin){
            $loginnumber++;
            Cache::set('parking-admin-'.$this->auth->id,$loginnumber,10*60);
            $this->error('管理员不存在');
        }
        if(md5(md5($password).$admin->salt)!=$admin->password){
            $loginnumber++;
            Cache::set('parking-admin-'.$this->auth->id,$loginnumber,10*60);
            $this->error('账号或密码不正确');
        }
        if($admin->status!='normal'){
            $this->error('管理员已被禁用');
        }
        if($type=='parking'){
            $parkingadmin=ParkingAdmin::where('admin_id',$admin->id)->find();
            $this->auth->setParkingAdmin($admin,$parkingadmin->parking_id,$parkingadmin->mobile_rules);
        }
        if($type=='property'){
            $propertyadmin=PropertyAdmin::where('admin_id',$admin->id)->find();
            $this->auth->setPropertyAdmin($admin,$propertyadmin);
            $parking=Parking::whereIn('property_id',$propertyadmin->property_id)->find();
            if(!$parking){
                $this->error('你所在物业没有绑定任何停车场');
            }
            $this->auth->setParkingAdmin($admin,$parking->id,'*');
        }
        $this->success('登录成功');
    }

    //更新删除
    #[Post('change')]
    public function change()
    {
        $id=$this->request->post('id');
        $thirds=Third::where('user_id',$this->auth->id)->column('id');
        $admin=Admin::find($id);
        if($admin->status!='normal'){
            $this->error('账号已禁用');
        }
        if(!$admin || !in_array($admin->third_id,$thirds)){
            $this->error('没有权限');
        }
        //集团账户
        if($admin->groupids==2){
            $propertyadmin=PropertyAdmin::where('admin_id',$admin->id)->find();
            $this->auth->setPropertyAdmin($admin,$propertyadmin);
            $parking=Parking::where('property_id',$propertyadmin->property_id)->find();
            $this->auth->setParkingAdmin($admin,$parking->id,'*');
        }
        //停车场账户
        if($admin->groupids==3){
            $parkingadmin=ParkingAdmin::where('admin_id',$admin->id)->find();
            $this->auth->setParkingAdmin($admin,$parkingadmin->parking_id,$parkingadmin->mobile_rules);
        }
        $this->success('操作成功');
    }

    #[Post('change-parking')]
    public function changeParking()
    {
        $parking_id=$this->request->post('parking_id');
        $propertyadmin=$this->auth->getPropertyAdmin();
        if(!$propertyadmin){
            $this->error('没有权限');
        }
        $parking=Parking::find($parking_id);
        if(!$parking || $parking->property_id!=$propertyadmin['property_id']){
            $this->error('没有权限');
        }
        $admin=Admin::find($propertyadmin['id']);
        $this->auth->setParkingAdmin($admin,$parking->id,'*');
        $this->success('操作成功');
    }

    #[Get('log')]
    public function log()
    {
        $page=$this->request->get('page/d');
        $list=ParkingLog::where(function ($query){
            $loginParkingAdmin=$this->auth->getParkingAdmin();
            $query->where('parking_id',$loginParkingAdmin['parking_id']);
            $starttime=$this->request->get('starttime');
            $endtime=$this->request->get('endtime');
            $radio=$this->request->get('radio');
            $plate_number=$this->request->get('plate_number');
            if($plate_number && is_car_license($plate_number)){
                $plate_number=strtoupper($plate_number);
                $query->whereLike('message','%'.$plate_number.'%');
            }
            if($starttime){
                $starttime=strtotime($starttime.' 00:00:00');
            }
            if($endtime){
                $endtime=strtotime($endtime.' 23:59:59');
            }
            if($starttime && $endtime){
                $query->whereBetween('createtime',[$starttime,$endtime]);
            }elseif($starttime){
                $query->where('createtime','>=',$starttime);
            }elseif($endtime){
                $query->where('createtime','<=',$endtime);
            }
            if($radio){
                $query->where('manual',1);
            }
        })
        ->order('id desc')
        ->limit(($page-1)*15,15)
        ->select();
        $this->success('',$list);
    }

    #[Get('logout')]
    public function logout()
    {
        $this->auth->updateToken(['parking_admin'=>null,'property_admin'=>null]);
        $this->success('操作成功');
    }
}
