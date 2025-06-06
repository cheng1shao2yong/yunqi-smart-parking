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

namespace app\common\model\parking;

use app\common\service\barrier\Utils;
use app\common\model\manage\Parking;
use TencentCloud\Common\Credential;
use TencentCloud\Tiia\V20190529\Models\CarTagItem;
use TencentCloud\Tiia\V20190529\Models\RecognizeCarProRequest;
use TencentCloud\Tiia\V20190529\TiiaClient;
use think\facade\Cache;
use think\Model;

class ParkingTemporary Extends Model
{
    const words=[
        '0','1','2','3','4','5','6','7','8','9',
        'a','b','c','d','e','f','g','h','i','j',
        'k','l','m','n','o','p','q','r','s','t',
        'u','v','w','x','y','z'
    ];

    public function parking()
    {
        return $this->hasOne(Parking::class,'id','parking_id');
    }

    public static function getTemporary($parking_id,$openid)
    {
        $temp=self::where(['openid'=>$openid,'parking_id'=>$parking_id])->order('id desc')->find();
        if($temp){
            return $temp;
        }
        $plate_number=self::getWords(1);
        $temp=new ParkingTemporary();
        $temp->openid=$openid;
        $temp->parking_id=$parking_id;
        $temp->plate_number=$plate_number;
        $temp->createtime=time();
        $temp->save();
        return $temp;
    }

    private static function getWords($total)
    {
        //上锁
        $et=5;
        $getlock=false;
        while ($et>0){
            if(!file_exists(root_path().'runtime/createplate.lock')){
                file_put_contents(root_path().'runtime/createplate.lock','1');
                $getlock=true;
                break;
            }
            $et--;
            sleep(1);
        }
        if(!$getlock){
            throw new \Exception('操作人数过多，请稍后重试！');
        }
        $words=[];
        $first='100000';
        $list=(new self())->limit(1)->order('id desc')->select();
        if(count($list)>0){
            $first=mb_substr($list[0]['plate_number'],1,6);
        }
        for($i=0;$i<$total;$i++){
            $first=self::plus($first,5);
            $words[]='临'.strtoupper($first);
        }
        unlink(root_path().'runtime/createplate.lock');
        if($total==1){
            return $words[0];
        }
        return $words;
    }

    private static function plus($last,$number)
    {
        $index=0;
        foreach (self::words as $k=>$v){
            if($v==strtolower($last[$number])){
                $index=$k;
            }
        }
        if(isset(self::words[$index+1])){
            $next=self::words[$index+1];
            $r='';
            for($i=0;$i<$number;$i++){
                $r.=$last[$i];
            }
            $r=$r.$next;
            $pd=5-$number;
            for($i=0;$i<$pd;$i++){
                $r.='0';
            }
            return $r;
        }else{
            $number--;
            return self::plus($last,$number);
        }
    }
}
