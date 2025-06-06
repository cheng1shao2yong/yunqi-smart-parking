<?php
declare(strict_types=1);
namespace addons\plugin\alisms;

class Install{

    public static $files=[
        "app/common/library/Alisms.php",
    ];

    public static $unpack=[

    ];

    public static $menu=[

    ];

    public static $require=[

    ];

    public static $config=[
        ['name'=>'alisms_secret','title'=>'短信secret','type'=>'text','tip'=>'','rules'=>'','extend'=>''],
        ['name'=>'alisms_key','title'=>'短信key','type'=>'text','tip'=>'','rules'=>'','extend'=>''],
        ['name'=>'alisms_sign','title'=>'短信签名','type'=>'text','tip'=>'','rules'=>'','extend'=>''],
    ];

    //安装扩展时的回调方法
    public static function install()
    {

    }

    //卸载扩展时的回调方法
    public static function uninstall()
    {

    }

}