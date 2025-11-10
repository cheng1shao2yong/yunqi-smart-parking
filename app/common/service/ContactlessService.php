<?php
declare(strict_types=1);
namespace app\common\service;

use app\common\model\parking\ParkingContactless;
use app\common\model\parking\ParkingRecords;
use app\common\model\PayUnion;

abstract class ContactlessService
{
    private static $service=[];
    //获取无感支付的服务方法
    public static function getService(string $handle):ContactlessService
    {
        if(isset(self::$service[$handle])){
            return self::$service[$handle];
        }
        $class=$handle;
        $obj=new $class();
        self::$service[$handle]=$obj;
        return $obj;
    }

    //申请支付
    abstract public function applyPayment(ParkingContactless $contactless,ParkingRecords $records,PayUnion $union);

    //支付结果
    abstract public function payResult(array $result);
}