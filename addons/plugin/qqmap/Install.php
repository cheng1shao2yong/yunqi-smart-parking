<?php
declare(strict_types=1);
namespace addons\plugin\qqmap;

class Install{

    public static $files=[
        "public/assets/js/components/position/QqMap.js",
        "app/admin/controller/Qqmap.php",
    ];

    public static $unpack=[

    ];

    public static $menu=[

    ];

    public static $require=[

    ];

    public static $config=[
        ['name'=>'qq_map_key','title'=>'腾讯地图KEY','type'=>'text','tip'=>'','rules'=>'','extend'=>''],
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