<?php
declare(strict_types=1);

namespace app\common\model\parking;

use think\Model;

class ParkingException extends Model
{
    public static function addException(ParkingPlate $plate,ParkingBarrier $barrier,string $message)
    {
        $rules_type='临时车';
        if($plate->cars){
            $rules_type=ParkingCars::RULESTYPE[$plate->cars->rules_type];
            if($plate->cars->endtime<time()){
                $rules_type.='【过期】';
            }
        }
        $data=[
            'parking_id'=>$plate->parking_id,
            'rules_type'=>$rules_type,
            'plate_number'=>$plate->plate_number,
            'plate_type'=>$plate->plate_type,
            'barrier'=>$barrier->title,
            'message'=>$message,
            'createtime'=>date('Y-m-d H:i:s',time())
        ];
        self::create($data);
    }
}
