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

use app\common\model\Qrcode as QrcodeModel;

//处理过期二维码
class Qrcode implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $files=glob(root_path().'public/qrcode/*.jpg');
        $qrcode_id=[];
        foreach ($files as $file){
            $filename=basename($file);
            $filename=str_replace('.jpg','',$filename);
            $qrcode_id[]=intval($filename);
        }
        $list= QrcodeModel::whereIn('id',$qrcode_id)->where('expiretime','<',time())->select();
        foreach ($list as $item){
            $qfile=root_path().'public/qrcode/'.$item->id.'.jpg';
            unlink($qfile);
        }
    }
}