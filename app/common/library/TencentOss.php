<?php
namespace app\common\library;

use Qcloud\Cos\Client;
use Qcloud\Cos\ImageParamTemplate\ImageMogrTemplate;
use Qcloud\Cos\ImageParamTemplate\PicOperationsTransformation;

class TencentOss
{
     private $bucket;
     private $secretId;
     private $secretKey;
     private $region;
     private $domain;

     private $client;

     private static $instance=null;

     public static function instance()
     {
         if(self::$instance==null){
             self::$instance=new self();
         }
         return self::$instance;
     }

     private function __construct()
     {
         $this->bucket = site_config("tencent.bucket");
         $this->secretId = site_config("tencent.secret_id");
         $this->secretKey = site_config("tencent.secret_key");
         $this->region = site_config("tencent.region");
         $this->domain = site_config("tencent.domain");
         $this->client = new Client(
             array(
                 'region' => $this->region,
                 'scheme' => 'https',
                 'credentials'=> array(
                     'secretId'  => $this->secretId,
                     'secretKey' => $this->secretKey
                 )
             )
         );
     }

     public function getDomain()
     {
         return $this->domain;
     }

     public function upload($filename,$content,$scaleWidth=false)
     {
         $bucket = $this->bucket;
         if($scaleWidth){
             $imageRule = new ImageMogrTemplate();
             $imageRule->thumbnailByWidth($scaleWidth);
             $picOperations = new PicOperationsTransformation();
             $picOperations->setIsPicInfo(1);
             //获取文件名
             $picOperations->addRule($imageRule,basename($filename));
         }
         $this->client->putObject(
             array(
                 'Bucket' => $bucket,
                 'Key' => $filename,
                 'Body' => $content,
                 'PicOperations' => $scaleWidth?$picOperations->queryString():'',
             )
         );
         return  $this->domain.'/'.$filename;
     }

}
