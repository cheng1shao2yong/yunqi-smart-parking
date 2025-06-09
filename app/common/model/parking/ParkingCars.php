<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\base\ConstTraits;
use app\common\model\manage\Parking;
use app\common\model\PlateBinding;
use app\common\model\Third;
use app\common\model\UserNotice;
use think\facade\Db;

class ParkingCars extends BaseModel
{
    use ConstTraits;

    protected $append=['rules_type_txt'];

    const RULESTYPE=[
        'monthly'=>'月租车',
        'day'=>'日租车',
        'member'=>'会员车',
        'stored'=>'储值车',
        'vip'=>'VIP车'
    ];

    const INSUFFICIENT_BALANCE=[
        'prohibit'=>'禁止出场',
        'provisional'=>'改为临停收费',
        'cutbalance'=>'扣完余额，剩下的钱正常付费',
    ];

    public function getRulesTypeTxtAttr($data,$row)
    {
        if(!isset($row['rules_type'])){
            return '';
        }
        return isset(self::RULESTYPE[$row['rules_type']])?self::RULESTYPE[$row['rules_type']]:'';
    }

    public function plates()
    {
        return $this->hasMany(ParkingPlate::class,'cars_id','id');
    }

    public function rules()
    {
        return $this->hasOne(ParkingRules::class,'id','rules_id');
    }

    public function third()
    {
        return $this->hasOne(Third::class,'id','third_id');
    }

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id');
    }

    public function getRemarkLineAttr($data)
    {
        if($data){
            return json_decode($data,true);
        }
        return null;
    }

    public function stop(string $change_type,mixed $day=null,mixed $date=null)
    {
        if($change_type=='date' && !$date){
            throw new \Exception('报停截至日期不能为空');
        }
        if($change_type=='day'){
            if(!is_numeric($day) || $day<=0){
                throw new \Exception('报停天数必须大于0');
            }
            $date=date('Y-m-d',time()+24*3600*($day+1));
        }
        $now=time();
        if($now<$this->starttime || $now>$this->endtime){
            throw new \Exception('未启用或者已经过期的月卡不能报停');
        }
        Db::startTrans();
        try{
            ParkingCarsStop::where(['parking_id'=>$this->parking_id,'cars_id'=>$this->id])->delete();
            (new ParkingCarsStop())->insert([
                'parking_id'=>$this->parking_id,
                'cars_id'=>$this->id,
                'change_type'=>$change_type,
                'date'=>$date,
                'createtime'=>$now
            ]);
            $this->status='hidden';
            $this->save();
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            throw new \Exception($e->getMessage());
        }
    }

    public function restart(ParkingCarsStop $stop,bool $auto=true)
    {
        if(!$stop){
            $this->status='normal';
            $this->save();
        }else{
            $date=date('Y-m-d',time());
            $stoptime=$stop->createtime;
            if($auto){
                $date=$stop->date;
            }
            $day=intval((strtotime($date .'23:59:59')-$stoptime)/(24*3600));
            Db::startTrans();
            try{
                $stop->date=$date;
                $stop->delete();
                $this->starttime=$this->starttime+$day*24*3600;
                $this->endtime=$this->endtime+$day*24*3600;
                $this->status='normal';
                $this->save();
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                throw new \Exception($e->getMessage());
            }
        }
    }

    public static function getRulesType(Parking $parking)
    {
        $r=[];
        $setting=$parking->setting;
        foreach (self::RULESTYPE as $k=>$v){
            if($setting[$k]){
                $r[$k]=$v;
            }
        }
        if(count($r)>0){
            return $r;
        }
        return self::RULESTYPE;
    }

    public static function getRulesDefaultType(Parking $parking)
    {
        $setting=$parking->setting;
        foreach (self::RULESTYPE as $k=>$v){
            if($setting[$k]){
                return $k;
            }
        }
        return 'provisional';
    }

    public static function addCars(ParkingRules $rules,string $contact,string $mobile,mixed $user_id,array $plates,array $options=[]):self
    {
        if(!$plates || count($plates)===0){
            throw new \Exception('车牌号不能为空');
        }
        $contact=trim($contact);
        $mobile=trim($mobile);
        if(!$contact || !$mobile){
            throw new \Exception('联系人和手机号不能为空');
        }
        foreach ($plates as &$plate){
            $plate['plate_number']=strtoupper(trim($plate['plate_number']));
            if(!is_car_license($plate['plate_number'])){
                throw new \Exception($plate['plate_number'].'不是合法的车牌号');
            }
            $havaplate=ParkingPlate::where(function($query) use ($plate,$rules){
                $query->where('plate_number',$plate['plate_number']);
                $query->where('parking_id',$rules->parking_id);
                $prefix=getDbPrefix();
                $query->whereRaw("cars_id in (select id from {$prefix}parking_cars where deletetime is null and parking_id={$rules->parking_id})");
            })->find();
            if($havaplate){
                throw new \Exception($havaplate['plate_number'].'车牌号已经存在');
            }
            if($user_id){
                $platebinding=PlateBinding::where(['plate_number'=>$plate['plate_number'],'user_id'=>$user_id])->find();
                if(!$platebinding){
                    $platebinding=new PlateBinding();
                    $platebinding->plate_number=$plate['plate_number'];
                    $platebinding->user_id=$user_id;
                    $platebinding->status=1;
                    $platebinding->save();
                }
                $notice=UserNotice::where(['user_id'=>$user_id])->find();
                if(!$notice){
                    UserNotice::create([
                        'user_id'=>$user_id,
                        'records'=>1,
                        'monthly'=>1,
                        'stored'=>1,
                        'invoice'=>0,
                        'coupon'=>0,
                    ]);
                }
            }
        }
        $time=strtotime(date('Y-m-d 00:00:00',time()));
        if(isset($options['third_id']) && !$options['third_id']){
            unset($options['third_id']);
        }
        $isnert=array_merge([
            'parking_id'=>$rules->parking_id,
            'rules_type'=>$rules->rules_type,
            'rules_id'=>$rules->id,
            'contact'=>$contact,
            'mobile'=>$mobile,
            'starttime'=>$time,
            'endtime'=>$time,
            'plates_count'=>count($plates)
        ],$options);
        $cars=new self();
        $cars->save($isnert);
        foreach ($plates as $k=>$v){
            if(!$plates[$k]['plate_type']){
                $plates[$k]['plate_type']='blue';
            }
            if(!$plates[$k]['car_models']){
                $plates[$k]['car_models']='small';
            }
            $plates[$k]['parking_id']=$rules->parking_id;
            $plates[$k]['cars_id']=$cars->id;
        }
        (new ParkingPlate())->saveAll($plates);
        //处理多位多车
        self::occupat($cars);
        return $cars;
    }

    public static function editCars(ParkingCars $cars,mixed $plates,array $options=[]):self
    {
        $third=false;
        if(isset($options['third_id']) && $options['third_id']){
            $third=Third::find($options['third_id']);
        }
        if(isset($options['third_id']) && !$options['third_id']){
            unset($options['third_id']);
        }
        if($cars->plates_count>10){
            $cars->save($options);
            self::occupat($cars);
            return $cars;
        }
        if(empty($plates)){
            throw new \Exception('车牌号不能为空');
        }
        foreach ($plates as &$plate){
            $plate['plate_number']=strtoupper(trim($plate['plate_number']));
            if(!is_car_license($plate['plate_number'])){
                throw new \Exception($plate['plate_number'].'不是合法的车牌号');
            }
            $havaplate=ParkingPlate::where(function($query) use ($cars,$plate){
                $query->where('plate_number',$plate['plate_number']);
                $query->where('parking_id',$cars->parking_id);
                $prefix=getDbPrefix();
                $query->whereRaw("cars_id in (select id from {$prefix}parking_cars where deletetime is null and parking_id={$cars->parking_id})");
                $query->where('cars_id','<>',$cars->id);
            })->find();
            if($havaplate){
                throw new \Exception($havaplate['plate_number'].'车牌号已经存在');
            }
        }
        $options['plates_count']=count($plates);
        $cars->save($options);
        ParkingPlate::where(['cars_id'=>$cars->id,'parking_id'=>$cars->parking_id])->delete();
        foreach ($plates as $k=>$v){
            if(!$plates[$k]['plate_type']){
                $plates[$k]['plate_type']='blue';
            }
            if(!$plates[$k]['car_models']){
                $plates[$k]['car_models']='small';
            }
            $plates[$k]['parking_id']=$cars->parking_id;
            $plates[$k]['cars_id']=$cars->id;
            if($third){
                $platebinding=PlateBinding::where(['plate_number'=>$plates[$k]['plate_number'],'user_id'=>$third->user_id])->find();
                if(!$platebinding){
                    $platebinding=new PlateBinding();
                    $platebinding->plate_number=$plates[$k]['plate_number'];
                    $platebinding->user_id=$third->user_id;
                    $platebinding->status=1;
                    $platebinding->save();
                }
                $notice=UserNotice::where(['user_id'=>$third->user_id])->find();
                if(!$notice){
                    UserNotice::create([
                        'user_id'=>$third->user_id,
                        'records'=>1,
                        'monthly'=>1,
                        'stored'=>1,
                        'invoice'=>0,
                        'coupon'=>0,
                    ]);
                }
            }
        }
        (new ParkingPlate())->saveAll($plates);
        self::occupat($cars);
        return $cars;
    }

    //处理多位多车
    private static function occupat(ParkingCars $cars)
    {
        if($cars->plates_count>$cars->occupat_number){
            $count=ParkingCarsOccupat::where(['parking_id'=>$cars->parking_id,'cars_id'=>$cars->id])->count();
            if($count==$cars->occupat_number){
                return;
            }
            ParkingCarsOccupat::where(['parking_id'=>$cars->parking_id,'cars_id'=>$cars->id])->delete();
            $occupat=[];
            for($i=0;$i<$cars->occupat_number;$i++){
                $occupat[]=[
                    'parking_id'=>$cars->parking_id,
                    'cars_id'=>$cars->id,
                    'code'=>$i+1
                ];
            }
            (new ParkingCarsOccupat())->saveAll($occupat);
        }
    }
}
