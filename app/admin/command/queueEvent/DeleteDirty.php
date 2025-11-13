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

use app\common\library\Http;
use think\facade\Cache;
use think\facade\Db;

//删除脏数据
class DeleteDirty implements EventInterFace
{
    public static $usetime=true;
    public static function handle($output)
    {
        $time1=time()-24*3600;
        $time7=time()-24*3600*7;
        $time30=time()-24*3600*10;
        $time200=time()-24*3600*200;
        $str="
            DELETE FROM yun_parking_cars where deletetime is NOT null and deletetime<{$time7};
            DELETE FROM yun_parking_cars where endtime<{$time200};
            DELETE FROM yun_parking_monthly_recharge where cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_plate where cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_cars_apply where cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_cars_logs where cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_cars_occupat where cars_id not in (SELECT id FROM yun_parking_cars);
            UPDATE yun_parking_records SET cars_id=null where cars_id is not null and cars_id not in (SELECT id FROM yun_parking_cars);
            DELETE FROM yun_parking_trigger where createtime<{$time30};
            DELETE FROM yun_parking_log where createtime<{$time30};
            DELETE FROM yun_parking_records_pay where pay_id is null and createtime<{$time1};
        ";
        $sqls=explode(';',$str);
        foreach ($sqls as $sql){
            $sql=trim($sql);
            if($sql){
                Db::execute($sql);
            }
        }
        //清除过期缓存
        $root=root_path().'runtime/cache';
        $cachedir=scandir($root);
        foreach ($cachedir as $dir){
            if(is_dir($root.'/'.$dir) && $dir!='.' && $dir!='..'){
                $files=scandir($root.'/'.$dir);
                foreach ($files as $file){
                    $realfile=$root.'/'.$dir.'/'.$file;
                    if(is_file($realfile) && str_ends_with($file,'.php')){
                        //文件最后一次修改的时间
                        $time=filemtime($realfile);
                        //读取$realfile第二行
                        $cachetime=(int)str_replace('//','',file($realfile)[1]);
                        //判断缓存是否超时
                        if($cachetime>0 && $time+$cachetime<time()){
                            //删除文件
                            unlink($realfile);
                        }
                    }
                }
            }
        }
        $apihost=get_domain('api');
        //域名授权标识，请勿删除，否则将无法使用
        $str="aHR0cHM6Ly93d3cuNTZxNy5jb20vYWRkb25zL2NvcHlyaWdodC9wYXJraW5nLw==";
        $url=base64_decode($str).$apihost;
        $response=Http::get($url);
        $basic=Cache::get('site_config_basic');
        if($response->isSuccess()){
            $basic['copyright']=$response->content['copyright'];
        }
        Cache::set('site_config_basic',$basic);
    }
}