<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\base\BaseModel;
use app\common\model\manage\Parking;

class ParkingInvoice Extends BaseModel
{

    const INVOICE_TYPE=[
        'company'=>'公司发票',
        'personal'=>'个人发票'
    ];

    protected $append = ['invoice_type_txt'];

    public function setting()
    {
        return $this->hasOne(ParkingSetting::class,'parking_id','parking_id')->field('parking_id,phone,invoice_entity');
    }

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id')->field('id,title');
    }

    public function getInvoiceTypeTxtAttr($value,$data)
    {
        return self::INVOICE_TYPE[$data['invoice_type']];
    }
}
