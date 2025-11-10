<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'Queue' => 'app\admin\command\Queue',
        'Mqtt' => 'app\admin\command\Mqtt',
        'Hzcbparking' => 'app\admin\command\Hzcbparking',
    ],
];
