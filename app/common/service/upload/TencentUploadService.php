<?php
declare(strict_types=1);
namespace app\common\service\upload;

use app\common\library\Imgcompress;
use app\common\library\TencentCos;
use app\common\model\Attachment;
use app\common\service\UploadService;

class TencentUploadService extends UploadService{

    protected $config=[];

    protected $disks='tencent_cos';

    private $imagetype=null;
    private $imagesize=null;
    private $imagewidth=null;
    private $imageheight=null;

    protected function putFile():array
    {
        $fileName = date('Ymd'). DIRECTORY_SEPARATOR .md5(microtime(true) . rand(10000,99999)). '.' .strtolower($this->file->extension());
        $oss=TencentCos::instance();
        $stream = fopen($this->file->getRealPath(), 'r');
        $percent=1;
        if($this->isImage() && $this->config['compress']){
            $filepath=$this->file->getRealPath();
            $imageinfo=getimagesize($filepath);
            $this->imagesize=filesize($filepath);
            $this->imagewidth=$imageinfo[0];
            $this->imageheight=$imageinfo[1];
            $this->imagetype=$imageinfo['mime'];
            if($this->imagesize>1024*1024*5){
                $percent = 0.4;
            }else if($this->imagesize>1024*1024**4){
                $percent = 0.5;
            }else if($this->imagesize>1024*1024**3){
                $percent = 0.6;
            }else if($this->imagesize>1024*1024**2){
                $percent = 0.7;
            }else if($this->imagesize>1024*1024**1){
                $percent = 0.8;
            }else if($this->imagesize>1024*1024**0.8){
                $percent = 0.9;
            }
            if($percent!=1){
                $this->imagewidth=intval($this->imagewidth*$percent);
                $this->imageheight=intval($this->imageheight*$percent);
            }
        }
        $oss->upload($fileName,$stream,$this->isImage(),$this->imagewidth??false);
        if($percent!=1){
            $this->imagesize=$oss->filesize($fileName);
        }
        if (is_resource($stream)) {
            fclose($stream);
        }
        return [$fileName,$oss->getDomain().'/'.$fileName];
    }

    protected function thumb(string $url,string $fullurl): string
    {
        if($this->imagewidth<=200 && $this->imageheight<=200){
            return $fullurl;
        }
        $percent=min(200/$this->imagewidth,200/$this->imageheight);
        $width=intval($this->imagewidth*$percent);
        $height=intval($this->imageheight*$percent);
        $ext=$this->file->extension();
        $thumburl=substr($url,0,strrpos($url,'.'.$ext)).'_thumb.'.$ext;
        $oss=TencentCos::instance();
        $oss->thumb($url,$thumburl,$width,$height);
        return $oss->getDomain().'/'.$thumburl;;
    }

    protected function imageFileinfo($url,$fullurl): array
    {
        return [$this->imagesize,$this->imagetype,$this->imagewidth,$this->imageheight];
    }

    protected function compress(string $url,string $fullurl)
    {
        //上传的时候已经压缩了，不需要再压缩
    }

    protected function watermark(string $url,string $fullurl)
    {

    }

    public static function deleteFile(Attachment $attachment)
    {
        $oss=TencentCos::instance();
        $oss->deleteFile($attachment->url);
        if($attachment->is_image){
            $exten=strtolower(pathinfo($attachment->url,PATHINFO_EXTENSION));
            $thumbpath=str_replace('.'.$exten,'_thumb.'.$exten,$attachment->url);
            $oss->deleteFile($thumbpath);
        }
    }
}