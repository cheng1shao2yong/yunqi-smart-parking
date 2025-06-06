<?php
declare (strict_types = 1);

use app\common\model\Addons;
use think\facade\Cache;
use app\common\model\Config;
use app\common\service\LangService;
use think\facade\Db;

if (!function_exists('site_config')) {

    /**
     * 获取/设置系统配置
     * @param string $name 属性名
     * @param mixed  $vars 属性值
     * @return mixed
     */
    function site_config(string $name,mixed $vars='')
    {
        if(strpos($name,'.')!==false){
            $name=explode('.',$name);
            $group=$name[0];
            $name=$name[1];
        }else{
            $group=$name;
            $name='';
        }
        if(!$vars){
            $groupval=Cache::get('site_config_'.$group);
            if(!$groupval){
                $groupval=Config::where('group',$group)->column('value','name');
                foreach ($groupval as $key=>$val){
                    if(is_string($val)){
                        if (str_starts_with($val, '{') &&  str_ends_with($val, '}')) {
                            $groupval[$key]=json_decode($val,true);
                            continue;
                        }
                        if(str_starts_with($val, '[') &&  str_ends_with($val, ']')){
                            $groupval[$key]=json_decode($val,true);
                            continue;
                        }
                    }
                    $groupval[$key]=$val;
                }
                Cache::set('site_config_'.$group,$groupval);
            }
            if($name) {
                return $groupval[$name];
            }else{
                return $groupval;
            }
        }else{
            if($name) {
                if(is_array($vars)){
                    $vars=json_encode($vars,JSON_UNESCAPED_UNICODE);
                }
                Config::where(['group'=>$group,'name'=>$name])->update(['value'=>$vars]);
            }else{
                foreach ($vars as $key=>$val){
                    if(is_array($val)){
                        $val=json_encode($val,JSON_UNESCAPED_UNICODE);
                    }
                    Config::where(['group'=>$group,'name'=>$key])->update(['value'=>$val]);
                }
            }
            Cache::delete('site_config_'.$group);
        }
    }
}

if (!function_exists('__')) {

    /**
     * 获取语言变量值
     * @param string $name 语言变量名
     * @param array  $vars 动态变量值
     * @param string $lang 语言
     * @return mixed
     */
    function __(string $name,array $vars = [])
    {
        return LangService::newInstance()->get($name,$vars);
    }
}
if (!function_exists('str_rand')) {
    /**
     * 获取随机字符串
     * @return string
     */
    function str_rand(int $num,string $str=''):string
    {
        if(!$str){
            $str='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        }
        $len=strlen($str)-1;
        $rand='';
        for($i=0;$i<$num;$i++){
            $rand.=$str[mt_rand(0,$len)];
        }
        return $rand;
    }
}

if (!function_exists('uuid')) {
    /**
     * 获取全球唯一标识
     * @return string
     */
    function uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('build_url')) {
    /**
     * 生成url地址
     * @return string
     */
    function build_url(string $url):string
    {
        $arr=parse_url($url);
        $url_html_suffix='.'.config('route.url_html_suffix');
        if(strpos($arr['path'],$url_html_suffix)===false){
            $arr['path'].=$url_html_suffix;
        }
        $r='';
        if(isset($arr['scheme'])){
            $r.=$arr['scheme'].'://';
        }
        if(isset($arr['host'])){
            $r.=$arr['host'];
        }
        if(isset($arr['path'])){
            if(!str_starts_with($arr['path'],'/')) {
                $r .= '/';
            }
            $r.=$arr['path'];
        }
        if(isset($arr['query'])){
            $r.='?'.$arr['query'];
        }
        return $r;
    }
}

if (!function_exists('rmdirs')) {

    /**
     * 删除文件夹
     * @param string $dirname  目录
     * @param bool   $withself 是否删除自身
     * @return boolean
     */
    function rmdirs(string $dirname, bool $withself = true)
    {
        if (!is_dir($dirname)) {
            return false;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        if ($withself) {
            @rmdir($dirname);
        }
        return true;
    }
}

if (!function_exists('create_file')) {
    /**
     * 创建文件并写入内容，如果所在文件夹不存在，则创建
     */
    function create_file(string $filepath, string $content = ''){
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($filepath, $content);
    }
}

if (!function_exists('get_addons')) {
    /**
     * 获取插件信息
     * @param string $pack 插件标识
     * @return array|bool
     */
    function get_addons(string $pack='')
    {
        $addons=Cache::get('download-addons');
        if(!$addons){
            $addons=Addons::field('id,key,type,name,install,open')->select();
            Cache::set('download-addons',$addons);
        }
        if(!$pack){
            return $addons;
        }
        foreach ($addons as $addon){
            if($addon['pack']==$pack){
                return $addon;
            }
        }
        return false;
    }
}

if (!function_exists('addons_installed')) {
    /**
     * 判断是否安装插件
     * @param string $pack 插件标识
     * @return array|bool
     */
    function addons_installed(string $pack)
    {
        $addons=Cache::get('download-addons');
        if(!$addons){
            $addons=Addons::field('id,key,pack,type,name,install,open')->select();
            Cache::set('download-addons',$addons);
        }
        foreach ($addons as $addon){
            if($addon['pack']==$pack && $addon['install']==1){
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('create_out_trade_no')) {
    function create_out_trade_no()
    {
        return date('YmdHis',time()).rand(10000,99999);
    }
}

//格式化数字，如果为小数则保留两位有效数字，如果小数末尾是0，则去掉0，如果为整数则不变，返回字符串
if (!function_exists('formatNumber')) {
    function formatNumber($number){
        $number=(string)$number;
        if(strpos($number,'.')){
            $arr=explode('.',$number);
            if(end($arr)==0){
                return (int)$arr[0];
            }
            return round((float)$number,2);
        }else{
            return (int)$number;
        }
    }
}

if (!function_exists('formatImage')) {
    function formatImage($img){
        if(str_starts_with($img,'http')){
            return $img;
        }
        $apidoman=get_domain('api');
        return $apidoman.$img;
    }
}

if (!function_exists('formatTime')) {
    function formatTime($number,$unit='minuts'){
        if($number<60){
            return $number.'秒';
        }else if($number>60 && $number<3600){
            $min=floor($number/60);
            return $min.'分钟';
        }else if($number>3600 && $number<86400){
            $hour=floor($number/3600);
            $min=floor(($number%3600)/60);
            return $hour.'时'.$min.'分';
        }else if($number>86400){
            $day=floor($number/86400);
            $hour=floor(($number%86400)/3600);
            $min=floor(($number%86400%3600)/60);
            $sec=($number%86400%3600)%60;
            if($unit=='minuts'){
                return $day.'天'.$hour.'时'.$min.'分';
            }
            if($unit=='seconds'){
                return $day.'天'.$hour.'时'.$min.'分'.$sec.'秒';
            }
        }
    }
}

if(!function_exists('getDbPrefix')){
    function getDbPrefix()
    {
        $config = Db::getConfig();
        $default=$config['default'];
        $prefix=$config['connections'][$default]['prefix'];
        return $prefix;
    }
}

if (!function_exists('is_car_license')) {
    function is_car_license($license){
        $license=strtoupper($license);
        $pattern = "/^[A-Z]{2}/";
        //军警车
        if (preg_match($pattern, $license)) {
            return true;
        }
        if(str_starts_with($license,'临') && mb_strlen($license)==7){
            return true;
        }
        $strt=['京','津','沪','渝','冀','豫','云','辽','黑','湘','皖','鲁','新','苏','浙','赣','鄂','桂','甘','晋','蒙','陕','吉','闽','贵','粤','粵','青','藏','川','宁','琼','使','领'];
        $index=-1;
        foreach ($strt as $k=>$v){
            if(str_starts_with($license,$v)){
                $index=$k;
                break;
            }
        }
        if($index==-1){
            return false;
        }
        $startlen=mb_strlen($strt[$index]);
        //将车牌的start去掉
        $license=mb_substr($license,$startlen);
        $pattern = "/^[A-Z]{1}/";
        if (!preg_match($pattern, $license)) {
            return false;
        }
        $license=mb_substr($license,1);
        $end=['挂','学','警','港','澳','应急'];
        $last=-1;
        foreach ($end as $k=>$v){
            if(str_ends_with($license,$v)){
                $last=$k;
                break;
            }
        }
        //将车牌的end去掉
        if($last!=-1){
            $lastlen=mb_strlen($end[$last]);
            $license=mb_substr($license,0,-$lastlen);
        }
        $pattern = "/^[A-Z0-9]{4,6}$/i";
        if(preg_match($pattern, $license)){
            return true;
        }
        return false;
    }
}

if (!function_exists('get_domain')) {
    function get_domain($model){
        $domain_bind=config('app.domain_bind');
        $host='';
        foreach ($domain_bind as $key=>$item){
            if($item==$model){
                $host=$key;
                break;
            }
        }
        if($model=='screen'){
            return 'http://'. $host;
        }else{
            return 'https://'. $host;
        }
    }
}