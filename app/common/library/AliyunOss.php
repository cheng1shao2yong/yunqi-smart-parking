<?php
namespace app\common\library;

use OSS\OssClient;

class AliyunOss
{
     private $bucket;
     private $accessKeyId;
     private $accessKeySecret;
     private $domain;
     private $endpoint;
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
         $this->bucket = site_config("aliyun.oss_bucket");
         $this->accessKeyId = site_config("aliyun.access_key");
         $this->accessKeySecret = site_config("aliyun.access_secret");
         $this->domain = site_config("aliyun.oss_domain");
         $this->endpoint = $this->bucket.'.'.$this->domain;
         $this->client = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->domain);
     }

     public function getDomain()
     {
         return $this->domain;
     }

     public function upload($filename,$content)
     {
         $this->client->putObject($this->bucket,$filename,$content);
         return  'https://'.$this->endpoint.'/'.$filename;
     }

     public function compress($filename,$width,$height)
     {
         $style = "image/resize,m_fixed,w_{$width},h_{$height}";
         $process = $style. '|sys/saveas'. ',o_'.base64_encode($filename). ',b_'.base64_encode($this->bucket);
         $this->client->processObject($this->bucket, $filename, $process);
     }

     public function thumb($filename,$thumburl,$width,$height)
     {
         $style = "image/resize,m_fixed,w_{$width},h_{$height}";
         $process = $style. '|sys/saveas'. ',o_'.base64_encode($thumburl). ',b_'.base64_encode($this->bucket);
         $this->client->processObject($this->bucket,$filename, $process);
     }


     public function filesize($filename)
     {
         $result=$this->client->getObjectMeta($this->bucket,$filename);
         $fileSize = $result['content-length'];
         return $fileSize;
     }

     public function deleteFile($filename)
     {
         $this->client->deleteObject($this->bucket,$filename);
     }

}
