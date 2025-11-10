<?php
declare (strict_types = 1);

namespace app\api\controller\parking;

use app\common\model\Admin;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingAdmin;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingCars;
use app\common\model\parking\ParkingCarsApply;
use app\common\model\parking\ParkingCarsLogs;
use app\common\model\parking\ParkingException;
use app\common\model\parking\ParkingInvoice;
use app\common\model\parking\ParkingMerchant;
use app\common\model\parking\ParkingMerchantCoupon;
use app\common\model\parking\ParkingMerchantCouponList;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRules;
use app\common\model\parking\ParkingSetting;
use app\common\model\PayRefund;
use app\common\model\PayUnion;
use app\common\model\Qrcode;
use app\common\model\QrcodeScan;
use app\common\model\Third;
use app\common\model\UserToken;
use app\common\service\barrier\Utils;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\annotation\route\Route;
use think\facade\Cache;
use think\facade\Db;

#[Group("parking/index")]
class Index extends Base
{
    
    protected $noNeedParkingRight=['info','parkingAdmin','menu','changePassword','qrcode','checkQrcode','getQrcodeInfo'];

    #[Get('parking-admin')]
    public function parkingAdmin()
    {
        $admin=$this->auth->getParkingAdmin();
        $this->success('',$admin);
    }

    #[Route('GET,POST','spacelast')]
    public function spacelast()
    {
        if($this->request->isPost()){
            $parking_space_last=$this->request->post('parking_space_last/d');
            $parking_space_total=ParkingSetting::where('parking_id',$this->parking_id)->value('parking_space_total');
            $barriers=ParkingBarrier::where(['parking_id'=>$this->parking_id])->select();
            foreach ($barriers as $barrier){
                if($barrier->show_last_space){
                    $show_last_space=json_decode($barrier->show_last_space,true);
                    $line=$show_last_space['line'];
                    $text=str_replace('{剩余车位}',(string)$parking_space_last,$show_last_space['text']);
                    Utils::setScreentextAd($barrier,$text,$line);
                }
            }
            Cache::set('parking_space_entry_'.$this->parking_id,$parking_space_total-$parking_space_last);
            $this->success('校准完成');
        }else{
            $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
            $parking_space_total=ParkingSetting::where('parking_id',$this->parking_id)->value('parking_space_total');
            $parking_space_entry=ParkingRecords::parkingSpaceEntry($parking);
            $parking_space_last=($parking_space_total-$parking_space_entry)>0?$parking_space_total-$parking_space_entry:0;
            $this->success('',[
                'parking_space_total'=>$parking_space_total,
                'parking_space_entry'=>$parking_space_entry,
                'parking_space_last'=>$parking_space_last
            ]);
        }
    }

    #[Get('info')]
    public function info()
    {
        $parking=Parking::cache('parking_'.$this->parkingAdmin['parking_id'],24*3600)->withJoin(['setting'])->find($this->parkingAdmin['parking_id']);
        $property=$this->auth->getPropertyAdmin();
        $parkings=[];
        if($property){
            $parkings=Parking::where('property_id',$property['property_id'])->select();
        }
        $this->success('',[
            'parking'=>$parking,
            'parkings'=>$parkings
        ]);
    }

    #[Get('records')]
    public function records()
    {
        $parking=Parking::cache('parking_'.$this->parking_id,24*3600)->withJoin(['setting'])->find($this->parking_id);
        $parking_space_total=ParkingSetting::where('parking_id',$this->parking_id)->value('parking_space_total');
        $parking_space_active=ParkingRecords::parkingSpaceEntry($parking);
        $parking_space_last=($parking_space_total-$parking_space_active>0)?$parking_space_total-$parking_space_active:0;
        $provisional=ParkingRecords::where(['parking_id'=>$this->parking_id,'rules_type'=>'provisional'])->whereIn('status',[0,1])->count();
        $monthly=ParkingCars::where(['parking_id'=>$this->parking_id,'rules_type'=>'monthly','status'=>'normal'])->count();
        $stored=ParkingCars::where(['parking_id'=>$this->parking_id,'rules_type'=>'stored','status'=>'normal'])->count();
        $vip=ParkingCars::where(['parking_id'=>$this->parking_id,'rules_type'=>'vip','status'=>'normal'])->count();
        $member=ParkingCars::where(['parking_id'=>$this->parking_id,'rules_type'=>'member','status'=>'normal'])->count();
        $endtime=strtotime(date('Y-m-d 23:59:59'));
        $starttime=$endtime-24*3600*7+1;
        $prefix=getDbPrefix();
        $sql="
                SELECT entry_time,COUNT(1) as count FROM
                (
                SELECT id,LEFT(FROM_UNIXTIME(entry_time),10) as entry_time FROM {$prefix}parking_records where parking_id={$this->parking_id} and entry_time BETWEEN {$starttime} AND {$endtime}
                )t1 GROUP BY entry_time
            ";
        $entry=Db::query($sql);
        $sql="
                SELECT exit_time,COUNT(1) as count FROM
                (
                SELECT id,LEFT(FROM_UNIXTIME(exit_time),10) as exit_time FROM {$prefix}parking_records where parking_id={$this->parking_id} and exit_time BETWEEN {$starttime} AND {$endtime}
                )t1 GROUP BY exit_time
            ";
        $exit=Db::query($sql);
        $data=[
            'categories'=>[],
            'series'=>[
                [
                    'name'=>'入场数量',
                    'data'=>[]
                ],
                [
                    'name'=>'出场数量',
                    'data'=>[]
                ]
            ]
        ];
        $j=0;
        for($i=$starttime;$i<=$endtime;$i+=24*3600){
            $data['categories'][]=date('d号',$i);
            $data['series'][0]['data'][$j]=0;
            $data['series'][1]['data'][$j]=0;
            foreach ($entry as $item){
                if($item['entry_time']==date('Y-m-d',$i)){
                    $data['series'][0]['data'][$j]=$item['count'];
                }
            }
            foreach ($exit as $item){
                if($item['exit_time']==date('Y-m-d',$i)){
                    $data['series'][1]['data'][$j]=$item['count'];
                }
            }
            $j++;
        }
        $this->success('',[
            'records'=>[
                'parking_space_total'=>$parking_space_total,
                'parking_space_active'=>$parking_space_active,
                'parking_space_last'=>$parking_space_last,
                'provisional'=>$provisional,
            ],
            'cars'=>[
                'monthly'=>$monthly,
                'stored'=>$stored,
                'member'=>$member,
                'vip'=>$vip,
            ],
            'charts'=>$data
        ]);
    }

    #[Get('merchant')]
    public function merchant()
    {
        $sql="
            SELECT ROUND(SUM(activities_time/60)) AS activities_time,ROUND(SUM(activities_fee/60)) AS activities_fee FROM `yun_parking_records` 
            WHERE 
            parking_id = {$this->parking_id}
            and `status` in (3,4,5)
            and activities_time>0
            AND deletetime IS NULL;
        ";
        $list=Db::query($sql);
        $activities_fee=$list[0]['activities_fee'];
        $activities_time=$list[0]['activities_time'];
        $info=[
            'total'=>ParkingMerchant::where(['parking_id'=>$this->parking_id])->count(),
            'total_coupon_type'=>ParkingMerchantCoupon::where(['parking_id'=>$this->parking_id,'status'=>'normal'])->count(),
            'total_coupon'=>ParkingMerchantCouponList::where(['parking_id'=>$this->parking_id])->count(),
            'total_coupon_active'=>ParkingMerchantCouponList::where(['parking_id'=>$this->parking_id,'status'=>1])->count(),
            'total_activities_fee'=>$activities_fee*60,
            'total_activities_time'=>$activities_time*60,
            'balance_bill'=>ParkingMerchant::where(['parking_id'=>$this->parking_id,'settle_type'=>'after'])->sum('balance'),
            'balance_fee'=>ParkingMerchant::where(['parking_id'=>$this->parking_id,'settle_type'=>'before'])->sum('balance'),
            'balance_time'=>ParkingMerchant::where(['parking_id'=>$this->parking_id,'settle_type'=>'time'])->sum('balance'),
        ];
        $this->success('',$info);
    }
    
    #[Get('pay')]
    public function pay()
    {
        $time=$this->request->get('time');
        [$starttime,$endtime]=$this->getRangeTime($time);
        $pie=PayUnion::where(function ($query) use ($starttime,$endtime){
            $prefix=getDbPrefix();
            $query->where('pay_status',1);
            $query->where('parking_id',$this->parking_id);
            $query->whereRaw("id not in (select pay_id from {$prefix}parking_records_filter where pay_id is not null)");
            $query->whereBetween('pay_time',[$starttime,$endtime]);
        })->group('order_type,pay_type')
        ->field('pay_type,order_type,sum(pay_price) as value,sum(handling_fees) as fees')
        ->select();
        $data=[
            'total'=>0,
            'provisional'=>0,
            'parking_monthly'=>0,
            'parking_stored'=>0,
            'merch_recharge'=>0,
            'refund_price'=>0,
            'handling_fees'=>0,
            'online'=>0,
            'settlement'=>0,
            'underline'=>0,
        ];
        foreach ($pie as $item){
            $data['total']+=$item['value'];
            if($item['order_type']=='parking'){
                $data['provisional']+=$item['value'];
            }
            if($item['order_type']=='parking_monthly'){
                $data['parking_monthly']+=$item['value'];
            }
            if($item['order_type']=='parking_stored'){
                $data['parking_stored']+=$item['value'];
            }
            if($item['order_type']=='merch_recharge'){
                $data['merch_recharge']+=$item['value'];
            }
            if($item['fees']){
                $data['handling_fees']+=$item['fees']/100;
            }
            if($item['pay_type']=='underline'){
                $data['underline']=$item['value'];
            }else{
                $data['online']+=$item['value'];
            }
        }
        $data['refund_price']=PayRefund::where('parking_id',$this->parking_id)->whereBetween('refund_time',[$starttime,$endtime])->sum('refund_price');
        $data['settlement']=$data['online']-$data['refund_price']-$data['handling_fees'];
        $this->success('',$data);
    }

    private function getRangeTime($time)
    {
        $now=time();
        if($time=='当日'){
            $start_str=date('Y-m-d 00:00:00',$now);
            $end_str=date('Y-m-d H:i:s',$now);
        }
        if($time=='昨日'){
            $start_str=date('Y-m-d 00:00:00',$now-24*3600);
            $end_str=date('Y-m-d 23:59:59',$now-24*3600);
        }
        if($time=='本周'){
            //$start_str,本周一的0点
            $start_str=date('Y-m-d 00:00:00',strtotime('this week monday midnight'));
            $end_str=date('Y-m-d H:i:s',$now);
        }
        if($time=='当月'){
            //$start_str,本月1号的0点
            $start_str=date('Y-m-01 00:00:00',$now);
            $end_str=date('Y-m-d H:i:s',$now);
        }
        if($time=='上月'){
            $start_time = strtotime('first day of last month', $now);
            $start_str = date('Y-m-d 00:00:00', $start_time);
            $end_time = strtotime('last day of last month 23:59:59', $now);
            $end_str = date('Y-m-d H:i:s', $end_time);
        }
        if($time=='今年'){
            $start_str=date('Y-01-01 00:00:00',$now);
            $end_str=date('Y-m-d H:i:s',$now);
        }
        return [$start_str,$end_str];
    }

    #[Post('change-password')]
    public function changePassword()
    {
        $password=$this->request->post('password');
        $salt = str_rand(4);
        $password=md5(md5($password) . $salt);
        $admin_id=$this->parkingAdmin['id'];
        Admin::where('id',$admin_id)->update(['salt'=>$salt,'password'=>$password]);
        UserToken::where('user_id','<>',$this->auth->id)->update(['parking_id'=>null,'parking_admin'=>null]);
        $this->success('修改成功');
    }

    #[Get('menu')]
    public function menu()
    {
        $menulist=ParkingAdmin::MENU;
        $admin=$this->auth->getParkingAdmin();
        foreach ($menulist as $key=>$meuns){
            foreach ($meuns as $index=>$menu){
                if(!ParkingAdmin::checkMenuAuth($menu,$admin['rules'])){
                    unset($menulist[$key][$index]);
                }else{
                    if($menu['page']=='parking/cars/apply'){
                        $apply=ParkingCarsApply::where(['parking_id'=>$this->parking_id,'status'=>0])->count();
                        if($apply>0){
                            $menulist[$key][$index]['tips']=$apply;
                        }
                    }
                    if($menu['page']=='parking/finance/invoice'){
                        $invoice=ParkingInvoice::where(['parking_id'=>$this->parking_id,'status'=>0])->count();
                        if($invoice>0){
                            $menulist[$key][$index]['tips']=$invoice;
                        }
                    }
                }
            }
        }
        $this->success('',$menulist);
    }

    #[Get('search')]
    public function search()
    {
        $plate_number=$this->request->get('plate_number');
        $prefix=getDbPrefix();
        $plate=ParkingPlate::withJoin(['cars'=>function ($query) {
            $query->where('deletetime',null);
        }])
        ->where([
            'parking_plate.parking_id'=>$this->parking_id,
            'parking_plate.plate_number'=>$plate_number
        ])->find();
        $logs=null;
        $exception=ParkingException::where(['parking_id'=>$this->parking_id,'plate_number'=>$plate_number])->limit(5)->order('id desc')->select();
        $records=ParkingRecords::where(['parking_id'=>$this->parking_id,'plate_number'=>$plate_number])->limit(5)->order('id desc')->select();
        if($plate && $plate['cars']){
            $cars=$plate['cars'];
            $cars->plate_number=$plate['plate_number'];
            $cars->plate_type=$plate['plate_type'];
            $cars->car_models=ParkingPlate::CARMODELS[$plate['car_models']];
            $cars->car_owners=$cars->contact.'【'.$cars->mobile.'】';
            $cars->rules=ParkingRules::find($cars['rules_id']);
            $logs=ParkingCarsLogs::where('cars_id',$cars['id'])->limit(5)->order('id desc')->select();
            $paySql="SELECT * FROM {$prefix}pay_union where pay_status=1 and parking_id={$this->parking_id} and id in (
                SELECT pay_id FROM {$prefix}parking_records_pay where pay_id is not null and parking_id={$this->parking_id} and records_id in (SELECT id from yun_parking_records where plate_number='{$plate_number}' and parking_id={$this->parking_id})
                UNION ALL
                SELECT pay_id FROM {$prefix}parking_monthly_recharge where cars_id={$cars->id}
                UNION ALL
                SELECT pay_id FROM {$prefix}parking_stored_log where pay_id is not null and cars_id={$cars->id}
            ) order by id desc limit 5";
        }else{
            $cars=new ParkingCars();
            $cars->rules_type='';
            $cars->plate_number=$plate_number;
            $sql="select plate_type,car_models from {$prefix}parking_plate where plate_number='{$plate_number}'";
            $r=Db::query($sql);
            if(!empty($r)){
                $cars['plate_type']=$r[0]['plate_type'];
                $cars['car_models']=ParkingPlate::CARMODELS[$r[0]['car_models']];
            }
            $sql="select mobile,contact from {$prefix}parking_cars where id in (select cars_id from {$prefix}parking_plate where plate_number='{$plate_number}') limit 1";
            $r=Db::query($sql);
            if(!empty($r)){
                $cars->car_owners=$r[0]['contact'].'【'.$r[0]['mobile'].'】';
            }
            $paySql="SELECT * FROM {$prefix}pay_union where pay_status=1 and parking_id={$this->parking_id} and id in (
                SELECT pay_id FROM {$prefix}parking_records_pay where pay_id is not null and parking_id={$this->parking_id} and records_id in (SELECT id from yun_parking_records where plate_number='{$plate_number}' and parking_id={$this->parking_id})
            ) order by id desc limit 5";
        }
        $pay=Db::query($paySql);
        foreach ($pay as $k=>$v){
            $pay[$k]['pay_time']=substr($v['pay_time'],0,16);
            $pay[$k]['pay_type']=PayUnion::PAYTYPE[$v['pay_type']];
            $pay[$k]['order_type']=PayUnion::ORDER_TYPE[$v['order_type']];
        }
        $this->success('',compact('cars','logs','records','pay','exception'));
    }

    #[Get('qrcode')]
    public function qrcode()
    {
        $foreign_key=$this->request->get('foreign_key');
        $config=[
            'appid'=>site_config("addons.uniapp_mpapp_id"),
            'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
        ];
        $qrcode=Qrcode::createQrcode(Qrcode::TYPE('绑定第三方账号'),$foreign_key,5*60);
        $wechat=new \WeChat\Qrcode($config);
        $ticket = $wechat->create($qrcode->id,5*60)['ticket'];
        $url=$wechat->url($ticket);
        $content=file_get_contents($url);
        header('Content-Type: image/png');
        echo $content;
        exit;
    }

    #[Get('check-qrcode')]
    public function checkQrcode()
    {
        $foreign_key=$this->request->get('foreign_key');
        $scan=QrcodeScan::where(['type'=>'bind-third-user','foreign_key'=>$foreign_key])->order('id desc')->find();
        if($scan){
            $third=Third::where(['platform'=>Third::PLATFORM('微信公众号'),'openid'=>$scan->openid])->field('id,user_id,openname,avatar')->find();
            if($third){
                $this->success('',$third);
            }
        }
        $this->error();
    }

    #[Get('get-qrcode-info')]
    public function getQrcodeInfo()
    {
        $type=$this->request->get('type');
        if($type=='barrier'){
            $serialno=$this->request->get('serialno');
            $barrier=ParkingBarrier::findBarrierBySerialno($serialno,['parking_id'=>$this->parking_id]);
            if(!$barrier){
                $this->error('二维码有误，请检查');
            }
            $this->success('',$barrier);
        }
        if($type=='stock' || $type=='monthly' || $type=='stored'){
            $uniqid=$this->request->get('uniqid');
            $parking=Parking::where(['id'=>$this->parking_id])->find();
            if($parking->uniqid!=$uniqid){
                $this->error('二维码有误，请检查');
            }
            $this->success();
        }
    }
}
