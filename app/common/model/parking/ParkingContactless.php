<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\admin\command\Hzcbparking;
use app\common\model\manage\Parking;
use app\common\model\PayUnion;
use app\common\service\ContactlessService;
use think\facade\Log;
use think\Model;

class ParkingContactless Extends Model
{
    public function records()
    {
        return $this->hasOne(ParkingRecords::class, 'id', 'records_id');
    }

    public function parking()
    {
        return $this->hasOne(Parking::class, 'id', 'parking_id')->field('id,title');
    }

    //无感支付
    public function pay(ParkingRecords $records,PayUnion $union)
    {
        $service=ContactlessService::getService($this->handle);
        try{
            $service->applyPayment($this,$records,$union);
            return true;
        }catch (\Exception $e)
        {
            Log::record("无感支付失败:".$e->getMessage());
        }
        return false;
    }
}