<?php
// 这是系统自动生成的middleware定义文件
return [
    //跨域请求
    app\api\middleware\AllowCrossDomain::class,
    //检测商户
    app\common\middleware\MerchantCheck::class
];
