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

namespace app\admin\command\queueEvent;


//每日发票通知管理员
use app\common\model\manage\Parking;
use app\common\model\parking\ParkingInvoice;
use app\common\service\msg\WechatMsg;
use think\facade\Db;

class Invoice implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        //处理自动开票
        $invoicelist=ParkingInvoice::where(['status'=>0,'invoice_send'=>'auto'])->select();
        foreach ($invoicelist as $invoice){
            $parking=Parking::cache('parking_'.$invoice->parking_id,24*3600)->withJoin(['setting'])->find($invoice->parking_id);
            try{
                $result= \app\common\library\Invoice::doInvoice($parking,$invoice);
                $content='发票下载链接：'.$result['qrCodePath'];
                $html='<p>发票下载链接：'.$result['qrCodePath'].'</p><br><img src="data:image/png;base64,'.$result['qrCode'].'"/>';
                $invoice->successInvoice($content,$html,$result['qrCodePath']);
            }catch (\Exception $e){
                $invoice->error=$e->getMessage();
                $invoice->save();
            }
        }
        $houre=date('H');
        //13点通知管理员开票
        if($houre==13){
            $prefix=getDbPrefix();
            $sql="select parking_id,sum(total_price) as price,count(1) as count from {$prefix}parking_invoice where status=0 and invoice_send='backend' group by parking_id";
            $list=Db::query($sql);
            foreach ($list as $item){
                WechatMsg::applyInvince($item['parking_id'],(int)$item['count'],(float)$item['price']);
            }
        }
    }
}