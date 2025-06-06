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

namespace app\common\service\barrier;

use app\common\library\TencentOrc;
use app\common\model\parking\ParkingBarrier;
use think\facade\Cache;

class Utils
{
    /* @var \Redis $redis*/
    private static $redis;

    public static function send($barrier,$name,$param=[],$callback='',$timeout=5)
    {
        $body=[
            'name'=>$name,
            'topic'=>self::getTopic($barrier,$name),
            'message'=>self::getMessage($barrier,$name,$param)
        ];
        $id=$body['message'][self::getUniqidName($barrier)];
        $redis=self::connectRedis();
        $redis->rpush('mqtt_publish_queue',json_encode($body));
        if($callback){
            $i=0;
            while($i<$timeout*10){
                $result=$redis->get($id);
                if($result){
                    $callback(json_decode($result,true));
                    return;
                }
                usleep(100000);
                $i++;
            }
            $callback(false);
        }
    }

    public static function makePhoto(ParkingBarrier $barrier)
    {
        Cache::set('barrier-photo-'.$barrier->serialno,'');
        Utils::send($barrier,'主动拍照');
        $i=0;
        $photo=false;
        while($i<50){
            $photo=Cache::get('barrier-photo-'.$barrier->serialno);
            if($photo){
                break;
            }
            usleep(100000);
            $i++;
        }
        if(!$photo){
            throw new \Exception('主动拍照失败');
        }
        return $photo;
    }

    public static function checkPlate(string $photo)
    {
        return TencentOrc::getInstance()->setPhoto($photo);
    }

    public static function getTopic(ParkingBarrier $barrier,string $name)
    {
        $classname='\\app\\common\\service\\barrier\\'.$barrier->camera;
        return $classname::getTopic($barrier,$name);
    }

    public static function getMessage(ParkingBarrier $barrier,string $name,array $param=[])
    {
        $classname='\\app\\common\\service\\barrier\\'.$barrier->camera;
        return $classname::getMessage($barrier,$name,$param);
    }

    public static function getUniqidName(ParkingBarrier $barrier)
    {
        $classname='\\app\\common\\service\\barrier\\'.$barrier->camera;
        return $classname::getUniqidName($barrier);
    }

    private static function connectRedis()
    {
        if(self::$redis && self::$redis->isConnected()){
            return self::$redis;
        }
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        self::$redis=$redis;
        return self::$redis;
    }
}