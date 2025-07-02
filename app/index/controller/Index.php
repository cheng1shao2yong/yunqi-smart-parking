<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\controller\BaseController;
use app\common\library\Invoice;
use app\common\library\ParkingTestAccount;
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingInvoice;
use think\annotation\route\Get;

class Index extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize();
    }

    #[Get('/')]
    public function index()
    {

    }

    #[Get('/test')]
    public function test()
    {
        $invoce=ParkingInvoice::find(280);
        $parking=Parking::find(1);
        Invoice::doInvoice($parking,$invoce,'贵阳云起信息科技有限公司','91520102MAAK4KQEXA');
    }
}