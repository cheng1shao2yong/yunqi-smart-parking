<?php
/**
 * ----------------------------------------------------------------------------
 * 行到水穷处，坐看云起时
 * 开发软件，找贵阳云起信息科技，官网地址:https://www.56q7.com/
 * ----------------------------------------------------------------------------
 * Author: 老成
 * email：85556713@qq.com
 */
declare(strict_types=1);
namespace app\parking\service;

use app\common\library\Tree;
use app\common\model\Admin;
use app\common\model\AuthRule;
use app\common\model\manage\Parking;
use app\common\model\manage\Property;
use app\common\model\parking\ParkingAdmin;
use app\common\model\property\PropertyAdmin;
use app\common\model\QrcodeScan;
use app\common\model\Third;
use app\common\service\AuthService;
use think\facade\Config;
use think\facade\Session;
use think\facade\Cache;

class ParkingAuthService extends AuthService{
    protected $allowFields = ['id', 'username', 'nickname', 'mobile', 'avatar', 'status', 'groupids', 'depart_id', 'third_id'];
    protected $userRuleList = [];
    protected $userMenuList = [];
    private $platformList=[];
    private $modulename;
    private $controllername;
    private $actionname;


    protected function init()
    {
        parent::init();
        $this->setUserRuleAndMenu();
    }

    public function getUserRuleList()
    {
        return $this->userRuleList;
    }

    public function getUserMenuList()
    {
        return $this->userMenuList;
    }

    public function getParking()
    {
        $r=Session::get('parking.parkingModel');
        return $r;
    }

    public function userinfo(bool $allinfo=false)
    {
        $r=Session::get('parking');
        if(!$r){
            return null;
        }
        if (Config::get('yunqi.login_unique')) {
            $my = Admin::get($this->id);
            if (!$my || $my['token'] != $r['token']) {
                Session::delete("parking");
                Session::save();
                return null;
            }
        }
        if(Config::get('yunqi.loginip_check')){
            if (request()->ip()!=$r['loginip']) {
                Session::delete("parking");
                Session::save();
                return null;
            }
        }
        if($allinfo){
            return $r;
        }
        return array_intersect_key($r,array_flip($this->allowFields));
    }

    public function getElementUi($elementUi)
    {
        if($this->element_ui){
            $data=json_decode($this->element_ui,true);
            foreach ($data as $k=>$v){
                $elementUi[$k]=$v;
            }
        }
        return $elementUi;
    }

    public function getRoute(string $type):string
    {
        switch ($type){
            case 'modulename':
                return $this->modulename;
            case 'controllername':
                return $this->controllername;
            case 'actionname':
                return $this->actionname;
            case 'title':
                $rulelist=$this->getRuleList();
                $actiontitle=['未定义'];
                $rulepid=0;
                foreach ($rulelist as $rule){
                    if($rule['controller']==$this->controllername && !$rule['ismenu']){
                        $action=json_decode($rule['action'],true);
                        $title=json_decode($rule['title'],true);
                        foreach ($action as $key=>$item){
                            if($item==$this->actionname){
                                $actiontitle=[$title[$key]];
                                $rulepid=$rule['pid'];
                            }
                        }
                    }
                }
                if($rulepid){
                    $this->getRuleTitles($rulelist,$rulepid,$actiontitle);
                    return implode('/',array_reverse($actiontitle));
                }
                return '未定义';
            default:
                return '';
        }
    }

    public function logout()
    {
        $admin = Admin::find(intval($this->id));
        if ($admin) {
            $admin->token = '';
            $admin->save();
        }
        Session::delete("parking");
        Session::save();
        return true;
    }

    public function loginByThird(string $__token__,$admin_id,array &$adminlist):bool
    {
        $scan=QrcodeScan::where(['type'=>'parking-login','foreign_key'=>$__token__])->order('id desc')->find();
        if($scan){
            if($scan->scantime+300<time()){
                throw new \Exception('二维码已过期');
            }
            $third=Third::where(['platform'=>Third::PLATFORM('微信公众号'),'openid'=>$scan->openid])->find();
            if($third){
                $list=Admin::where(['third_id'=>$third->id])->whereRaw('groupids=2 or groupids=3')->select();
                $adminlist=[];
                foreach ($list as $item){
                    unset($item['password']);
                    unset($item['salt']);
                    if($item['status']!='normal'){
                        continue;
                    }
                    $adminlist[]=$item;
                }
                if(count($adminlist)==1){
                    $admin=$adminlist[0];
                    $admin->loginfailure = 0;
                    $admin->logintime = time();
                    $admin->loginip = request()->ip();
                    $admin->token = uuid();
                    $admin->save();
                    $uniqid=explode('-',$admin->username)[0];
                    //看是否为集团账号
                    $propertyModel=Property::where('uniqid',$uniqid)->find();
                    if($propertyModel){
                        $propertyadmin=PropertyAdmin::where(['admin_id'=>$admin->id,'property_id'=>$propertyModel->id])->find();
                        $parkingModel=Parking::withJoin(['setting'])->where(['property_id'=>$propertyModel->id])->find();
                        if($propertyadmin && $parkingModel){
                            Session::set('parking',$admin->toArray());
                            Session::set('parking.parkingModel',$parkingModel);
                            Session::set('parking.parkingAdmin',null);
                            Session::set('parking.propertyModel',$propertyModel);
                            Session::set('parking.propertyAdmin',$propertyadmin);
                            Session::save();
                            return true;
                        }
                    }
                    $parkingModel=Parking::withJoin(['setting'])->where('uniqid',$uniqid)->find();
                    if($parkingModel){
                        $parkadmin=ParkingAdmin::where(['admin_id'=>$admin->id,'parking_id'=>$parkingModel->id])->find();
                        if($parkadmin){
                            Session::set('parking',$admin->toArray());
                            Session::set('parking.parkingModel',$parkingModel);
                            Session::set('parking.parkingAdmin',$parkadmin);
                            Session::set('parking.propertyModel',null);
                            Session::set('parking.propertyAdmin',null);
                            Session::save();
                            return true;
                        }
                    }
                    Session::save();
                    return true;
                }
                if(count($adminlist)>1 && $admin_id){
                    foreach ($adminlist as $xitem){
                        if($xitem['id']==$admin_id){
                            $admin=$xitem;
                            $admin->loginfailure = 0;
                            $admin->logintime = time();
                            $admin->loginip = request()->ip();
                            $admin->token = uuid();
                            $admin->save();
                            $uniqid=explode('-',$admin->username)[0];
                            //看是否为集团账号
                            $propertyModel=Property::where('uniqid',$uniqid)->find();
                            if($propertyModel){
                                $propertyadmin=PropertyAdmin::where(['admin_id'=>$admin->id,'property_id'=>$propertyModel->id])->find();
                                $parkingModel=Parking::withJoin(['setting'])->where(['property_id'=>$propertyModel->id])->find();
                                if($propertyadmin && $parkingModel){
                                    Session::set('parking',$admin->toArray());
                                    Session::set('parking.parkingModel',$parkingModel);
                                    Session::set('parking.parkingAdmin',null);
                                    Session::set('parking.propertyModel',$propertyModel);
                                    Session::set('parking.propertyAdmin',$propertyadmin);
                                    Session::save();
                                    return true;
                                }
                            }
                            $parkingModel=Parking::withJoin(['setting'])->where('uniqid',$uniqid)->find();
                            if($parkingModel){
                                $parkadmin=ParkingAdmin::where(['admin_id'=>$admin->id,'parking_id'=>$parkingModel->id])->find();
                                if($parkadmin){
                                    Session::set('parking',$admin->toArray());
                                    Session::set('parking.parkingModel',$parkingModel);
                                    Session::set('parking.parkingAdmin',$parkadmin);
                                    Session::set('parking.propertyModel',null);
                                    Session::set('parking.propertyAdmin',null);
                                    Session::save();
                                    return true;
                                }
                            }
                            Session::save();
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function login(string $username, string $password)
    {
        $admin = Admin::where(['username' => $username])->find();
        if (!$admin) {
            throw new \Exception('用户名或密码错误！');
        }
        if ($admin['status'] == 'hidden') {
            throw new \Exception('用户已被禁止使用！');
        }
        if (Config::get('yunqi.login_failure_retry') && $admin->loginfailure >= 10 && time() - $admin->updatetime < 86400) {
            throw new \Exception('登陆失败次数过多，请一天后重试！');
        }
        if ($admin->password != md5(md5($password) . $admin->salt)) {
            $admin->loginfailure++;
            $admin->save();
            throw new \Exception('用户名或密码错误！');
        }
        $admin->loginfailure = 0;
        $admin->logintime = time();
        $admin->loginip = request()->ip();
        $admin->token = uuid();
        $admin->save();
        Session::set('parking',$admin->toArray());
        Session::save();
        return $admin;
    }

    public function getRuleList()
    {
        $rule = AuthRule::field('id,pid,status,controller,action,title,icon,menutype,ismenu,isplatform,extend')
            ->order('weigh', 'desc')
            ->cache('admin_rule_list')
            ->select()
            ->toArray();
        foreach ($rule as $k=>$v) {
            $rule[$k]['url'] = $this->getPath($v['controller'], $v['action']);
        }
        return $rule;
    }

    /**
     * 根据控制器注解获取到菜单栏的path
     * @param string $controller
     * @param string $action
     * @return string
     */
    public function getPath(mixed $controller,mixed $action):string
    {
        $url='';
        if(!$controller || !$action){
            return build_url('404');
        }
        if(!class_exists($controller) || !method_exists($controller,$action)){
            return build_url('404');
        }
        $class=new \ReflectionClass($controller);
        $attributes=$class->getAttributes();
        foreach ($attributes as $attribute)
        {
            $name=$attribute->getName();
            if($name=='think\annotation\route\Group'){
                $url=$attribute->getArguments()[0].'/';
                break;
            }
        }
        $method=new \ReflectionMethod($controller, $action);
        $attributes=$method->getAttributes();;
        foreach ($attributes as $attribute)
        {
            $name=$attribute->getName();
            if($name=='think\annotation\route\Get' || $name=='think\annotation\route\Post'){
                $url=$url.$attribute->getArguments()[0];
                break;
            }
            if($name=='think\annotation\route\Route'){
                $url=$url.$attribute->getArguments()[1];
                break;
            }
        }
        return build_url($url);
    }

    private function getRuleTitles(array $rulelist,int $ruleid,array &$actiontitle)
    {
        foreach ($rulelist as $rule){
            if($rule['id']==$ruleid){
                $actiontitle[]=$rule['title'];
                if($rule['pid']){
                    $this->getRuleTitles($rulelist,$rule['pid'],$actiontitle);
                }
            }
        }
    }
    /**
     * 为用户权限列表ruleList赋值
     */
    private function setUserRuleAndMenu()
    {
        if($this->id){
            $adminRuleList= Cache::get('admin_rule_list_'.$this->id);
            $adminMenuList= Cache::get('admin_menu_list_'.$this->id);
            $platformList=Cache::get('admin_platform_list_'.$this->id);
            if(!$adminRuleList || !$adminMenuList || !$platformList || Config::get('app.app_debug')){
                $adminRuleList=[];
                $adminMenuList=[];
                $platformList=[];
                //集团账户
                if($this->groupids==2 && $this->propertyAdmin){
                    $adminRuleList='*';
                    $adminMenuList='*';
                    $property_id=$this->propertyModel->id;
                    $platformList=Parking::where('property_id',$property_id)->field('id,title')->select();
                    foreach ($platformList as $key=>$platform){
                        if($platform->id==$this->parkingModel->id){
                            $platformList[$key]->active=true;
                        }
                    }
                }
                //停车场账户
                if($this->groupids==3 && $this->parkingAdmin){
                    $rulelist=$this->getRuleList();
                    if($this->parkingAdmin->auth_rules=='*'){
                        $adminRuleList='*';
                        $adminMenuList='*';
                    }else{
                        $auth_rules=explode(',',$this->parkingAdmin->auth_rules);
                        foreach ($rulelist as $rule){
                            if(in_array($rule['id'],$auth_rules)){
                                if($rule['ismenu']){
                                    $adminMenuList[]=$rule;
                                }else{
                                    $adminRuleList[]=$rule;
                                }
                            }
                        }
                    }
                }
                Cache::set('admin_rule_list_'.$this->id,$adminRuleList);
                Cache::set('admin_menu_list_'.$this->id,$adminMenuList);
                Cache::set('admin_platform_list_'.$this->id,$platformList);
            }
            $this->userRuleList=$adminRuleList;
            $this->userMenuList=$adminMenuList;
            $this->platformList=$platformList;
        }
    }

    /**
     * 检测权限
     * @param string $controller
     * @param string $action
     * @return mixed
     */
    public function check(string $controller,string $action):int
    {
        if ($this->userRuleList=='*') {
            return 1;
        }
        foreach ($this->userRuleList as $value){
            if($value['controller']==$controller){
                $actions=json_decode($value['action'],true);
                foreach ($actions as $v){
                    if($v==$action){
                        return 1;
                    }
                }
            }
        }
        return 0;
    }

    /**
     * 获取左侧和顶部菜单栏
     * @return array
     */
    public function getSidebar(mixed $refererUrl=''):array
    {
        $ruleList=$this->getRuleList();
        foreach ($ruleList as $k => &$v) {
            unset($v['controller']);
            unset($v['action']);
            if($this->userMenuList!='*' && !in_array($v['id'],array_column($this->userMenuList,'id'))){
                unset($ruleList[$k]);
                continue;
            }
            if (!$v['ismenu']) {
                unset($ruleList[$k]);
                continue;
            }
            if ($v['isplatform']) {
                unset($ruleList[$k]);
                continue;
            }
            $v['title'] = __($v['title']);
            if($v['extend']){
                $v['extend']=json_decode($v['extend'],true);
            }
        }
        $ruleList=array_values($ruleList);
        $treeRuleList=Tree::instance()->init($ruleList)->getTreeArray(31);
        $selected=[];
        $referer=[];
        $this->getSelectAndReferer($treeRuleList,$refererUrl,$selected,$referer);
        if($selected==$referer){
            $referer=[];
        }
        $platformList=$this->platformList;
        return [$platformList,$treeRuleList,$selected,$referer];
    }

    public function getBackendAuth()
    {
        $userlist=$this->userRuleList;
        //如果$userlist是数组
        if(is_array($userlist)){
            foreach ($userlist as $key=>$value){
                $userlist[$key]['action']=json_decode($value['action'],true);
                $userlist[$key]['title']=json_decode($value['title'],true);
            }
        }
        return [
            'admin'=>$this->userinfo(),
            'rules_list'=>$userlist
        ];
    }

    private function getSelectAndReferer($treeRuleList,$refererUrl,&$selected,&$referer)
    {
        foreach ($treeRuleList as $value){
            if(count($value['childlist'])===0 && !isset($selected['url'])){
                $selected=$value;
            }
            if($refererUrl){
                if(parse_url($refererUrl,PHP_URL_PATH)==parse_url($value['url'],PHP_URL_PATH)){
                    $value['url']=$refererUrl;
                    $referer=$value;
                }
            }
            if(count($value['childlist'])>0){
                $this->getSelectAndReferer($value['childlist'],$refererUrl,$selected,$referer);
            }
        }
    }

    public function loginByMobile(string $mobile, string $code)
    {

    }

    public function loginByThirdPlatform(string $platform, Third $third)
    {

    }
}