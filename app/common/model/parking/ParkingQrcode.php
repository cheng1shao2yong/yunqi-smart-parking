<?php
declare(strict_types=1);

namespace app\common\model\parking;

use app\common\model\Attachment;
use app\common\model\manage\Parking;
use think\Model;
use app\common\library\QRcode;
use app\common\model\Qrcode as QrcodeModel;

class ParkingQrcode extends Model
{
    const QRCODE=array(
        ['title'=>'出口付费码','name'=>'exit'],
        ['title'=>'临牌车入场码','name'=>'entry'],
        ['title'=>'场内付费码','name'=>'stock'],
        ['title'=>'月租申请与续租码','name'=>'monthly'],
        ['title'=>'日租车（预约车）申请码','name'=>'day'],
        ['title'=>'储值卡申请与充值码','name'=>'stored'],
        ['title'=>'停车场小程序管理端入口码','name'=>'admin'],
        ['title'=>'停车场小程序商家端入口码','name'=>'merchant'],
        ['title'=>'云起停车小程序用户端入口码','name'=>'miniapp'],
        ['title'=>'云起停车关注公众号二维码','name'=>'mpapp']
    );

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id');
    }

    public function getTextAttr($data)
    {
        $data=json_decode($data,true);
        $data['size']=intval($data['size']);
        return $data;
    }

    public static function getQrcode(ParkingQrcode $qrcode,string $serialno='')
    {
        if($qrcode['name']=='entry'){
            $content=self::createMpAppQrcode($serialno,'parking-entry-qrcode');
        }else if($qrcode['name']=='mpapp'){
            $content=self::createMpAppQrcode($qrcode->parking_id,'parking-mpapp-index');
        }else if($qrcode['name']=='day'){
            $content=self::createMpAppQrcode($qrcode->parking_id,'parking-entry-apply');
        }else{
            $random=$qrcode->name.rand(100000,999999);
            $tempfile=root_path().'runtime/'.$random.'.png';
            $url=self::getQrcodeInfo($qrcode,$serialno);
            QRcode::png($url,$tempfile, QR_ECLEVEL_L, 10, 2);
            $content=file_get_contents($tempfile);
            unlink($tempfile);
        }
        return $content;
    }

    public static function createImage(ParkingQrcode $qrcode,string $serialno='',int $size=null)
    {
        $random=$qrcode->name.rand(100000,999999);
        $tempfile=root_path().'runtime/'.$random.'.png';
        if($qrcode['name']=='entry'){
            $content=self::createMpAppQrcode($serialno,'parking-entry-qrcode');
            file_put_contents($tempfile,$content);
        }else if($qrcode['name']=='mpapp'){
            $content=self::createMpAppQrcode($qrcode->parking_id,'parking-mpapp-index');
            file_put_contents($tempfile,$content);
        }else if($qrcode['name']=='day'){
            $content=self::createMpAppQrcode($qrcode->parking_id,'parking-entry-apply');
            file_put_contents($tempfile,$content);
        }else{
            $url=self::getQrcodeInfo($qrcode,$serialno);
            QRcode::png($url,$tempfile, QR_ECLEVEL_Q, 30, 2);
        }
        //图片添加logo
        $logo=Attachment::where(['fullurl'=>site_config("basic.logo")])->find();
        self::addlogo($tempfile,root_path().$logo->url);
        //图片添加背景
        if($qrcode->background){
            $background=Attachment::where(['fullurl'=>$qrcode->background])->find();
            self::addBackground($tempfile,root_path().$background->url,$qrcode->left,$qrcode->top,$qrcode->size,$qrcode->text);
        }
        if($size){
            self::changeImageSize($tempfile,$size);
        }
        $content=file_get_contents($tempfile);
        unlink($tempfile);
        return $content;
    }

    private static function createMpAppQrcode(mixed $foreign_key,string $type)
    {
        $config=[
            'appid'=>site_config("addons.uniapp_mpapp_id"),
            'appsecret'=>site_config("addons.uniapp_mpapp_secret"),
        ];
        $qrcode= QrcodeModel::createQrcode($type,$foreign_key,24*3600*365*80);
        $qrcode_id=(string)$qrcode->id;
        $tempfile=root_path().'public/qrcode/'.$qrcode_id.'.png';
        if(file_exists($tempfile)){
            $content=file_get_contents($tempfile);
            return $content;
        }
        $wechat=new \WeChat\Qrcode($config);
        $ticket = $wechat->create($qrcode_id)['ticket'];
        $url=$wechat->url($ticket);
        $content=file_get_contents($url);
        file_put_contents($tempfile,$content);
        return $content;
    }

    private static function getQrcodeInfo(ParkingQrcode $qrcode,string $serialno)
    {
        $url=request()->domain();
        $uniqid=$qrcode->parking->uniqid;
        $records_id=$qrcode->records_id;
        $name=$qrcode['name'];
        switch ($name){
            case 'exit':
                $url=$url.'/qrcode/exit?serialno='.$serialno;
                break;
            case 'stock':
                $url=$url.'/qrcode/stock?uniqid='.$uniqid;
                break;
            case 'records':
                $url=$url.'/qrcode/records?records_id='.$records_id;
                break;
            case 'monthly':
                $url=$url.'/qrcode/monthly?uniqid='.$uniqid;
                break;
            case 'day':
                $url=$url.'/qrcode/day?uniqid='.$uniqid;
                break;
            case 'stored':
                $url=$url.'/qrcode/stored?uniqid='.$uniqid;
                break;
            case 'admin':
                $url=$url.'/qrcode/admin?1=1';
                break;
            case 'merchant':
                $url=$url.'/qrcode/merchant?1=1';
                break;
            case 'miniapp':
                $url=$url.'/qrcode/miniapp?1=1';
                break;
        }
        return $url;
    }

    //按比例改变图片尺寸
    private static function changeImageSize(string $tempfile,int $size)
    {
        $qrcode_info = getimagesize($tempfile);
        $fun2   = 'imagecreatefrom' . image_type_to_extension($qrcode_info[2], false);
        $qrcode = $fun2($tempfile);
        $width=$size;
        $height=intval($qrcode_info[1]*$size/$qrcode_info[0]);
        $src = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($src, 255, 255, 255);
        imagefill($src, 0, 0, $color);
        imagecopyresampled(
            $src,
            $qrcode,
            0, 0, 0, 0,
            $width, $height,
            $qrcode_info[0], $qrcode_info[1]
        );
        imagejpeg($src,$tempfile);
        imagedestroy($src);
        imagedestroy($qrcode);
    }

    private static function addlogo(string $qrcodefile,string $logofile)
    {
        $qrcode_info = getimagesize($qrcodefile);
        $logo_info = getimagesize($logofile);
        $fun1   = 'imagecreatefrom' . image_type_to_extension($logo_info[2], false);
        $logo = $fun1($logofile);
        $fun2   = 'imagecreatefrom' . image_type_to_extension($qrcode_info[2], false);
        $qrcode = $fun2($qrcodefile);
        //logo占整个二维码的尺寸比例
        $size=intval($qrcode_info[0]*0.2);
        $positon = intval(($qrcode_info[0] - $size) / 2);
        //修改logo尺寸
        $src = imagecreatetruecolor($size, $size);
        $color = imagecolorallocate($src, 255, 255, 255);
        imagefill($src, 0, 0, $color);
        imagecopyresampled(
            $src,
            $logo,
            0, 0, 0, 0,
            $size, $size,
            $logo_info[0], $logo_info[1]
        );
        imagecopymerge($qrcode, $src, $positon, $positon, 0, 0, $size, $size, 100);
        imagejpeg($qrcode,$qrcodefile);
        imagedestroy($src);
        imagedestroy($logo);
        imagedestroy($qrcode);
    }

    private static function addBackground(string $qrcodefile,string $backgroundfile,int $left,int $top,int $size,array $text)
    {
        $background_info = getimagesize($backgroundfile);
        $fun1   = 'imagecreatefrom' . image_type_to_extension($background_info[2], false);
        $background = $fun1($backgroundfile);
        $qrcode_info = getimagesize($qrcodefile);
        $fun2   = 'imagecreatefrom' . image_type_to_extension($qrcode_info[2], false);
        $qrcode = $fun2($qrcodefile);
        //设置二维码的宽度
        $size=intval($size*$background_info[0]/400);
        $left=intval($left*$background_info[0]/400);
        $top=intval($top*$background_info[0]/400);
        //修改二维码长度
        $src = imagecreatetruecolor($size, $size);
        $color = imagecolorallocate($src, 255, 255, 255);
        imagefill($src, 0, 0, $color);
        imagecopyresampled(
            $src,
            $qrcode,
            0, 0, 0, 0,
            $size, $size,
            $qrcode_info[0], $qrcode_info[1]
        );
        imagecopymerge($background, $src, $left, $top, 0, 0, $size, $size, 100);
        //添加背景文字，不用换行
        /*
        if($text['title']){
            $font=root_path().'font.ttf';
            $fontSize=intval(intval($text['size'])*1.4);
            $color=$text['color'];
            $left=intval(intval($text['left'])*$background_info[0]/385);
            $top=intval(intval($text['top'])*$background_info[0]/335);
            imagettftext($background, $fontSize, 0, $left, $top, imagecolorallocate($background, hexdec(substr($color,1,2)), hexdec(substr($color,3,2)), hexdec(substr($color,5,2))), $font, $text['title']);
        }
        */
        imagejpeg($background,$qrcodefile);
        imagedestroy($src);
        imagedestroy($qrcode);
        imagedestroy($background);
    }
}