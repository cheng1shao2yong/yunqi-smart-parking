<?php
declare(strict_types=1);
namespace app\common\service;

use app\common\model\PayUnion;
use app\common\service\pay\Yibao;
use app\common\service\pay\Guotong;
use app\common\middleware\MerchantCheck;

/**
 * 支付服务
 */
abstract class PayService extends BaseService{
    protected $pay_type_handle;
    //收款商户号
    protected $sub_merch_no;
    //分账商户号
    protected $split_merch_no;
    //费率
    protected $persent;
    //用户
    protected $user_id;
    protected $property_id;
    protected $parking_id;

    protected $pay_price;
    protected $order_type;
    protected $order_body;
    protected $attach;
    //条形码
    protected $mediumNo;
    //终端号
    protected $terminalId;
    //退款相关
    protected $pay_union;
    protected $refund_price;
    protected $refund_cause;

    private static $hanleClass=[
        'yibao'=>Yibao::class,
        'guotong'=>Guotong::class,
    ];

    protected function getSubMerchNo()
    {
        MerchantCheck::checkSubMerchNo($this);
        return $this->sub_merch_no;
    }

    protected function getSplitMerchNo()
    {
        MerchantCheck::checkSplitMerchNo($this);
        return $this->split_merch_no;
    }

    /**
     * @param array $arr 参数
     * @param string $key 为创建线程安全对象的唯一识别key
     * @return mixed|static
     */
    public static function newInstance(array $arr=[],mixed $safekey=null)
    {
        if(!isset($arr['pay_type_handle']) || !key_exists($arr['pay_type_handle'],PayUnion::PAY_TYPE_HANDLE)){
            throw new \Exception('找不到实现接口');
        }
        $classname=self::$hanleClass[$arr['pay_type_handle']];
        $safekey=$safekey??$classname;
        if(!isset(self::$service[$safekey])){
            $service = new $classname();
            $service->safekey=$safekey;
            if(count($arr)>0){
                $service->setParam($arr);
            }
            self::$service[$safekey]=$service;
            self::$obj[$safekey]=[];
            $service->init();
        }
        return self::$service[$safekey];
    }

    abstract public function wechatMiniappPay();

    abstract public function alipayPcPay();
    abstract public function mpAlipay();
    abstract public function wechatPcPay();
    abstract public function wechatMpappPay();
    abstract public function qrcodePay();
    abstract public function notify();

}