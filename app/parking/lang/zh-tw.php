<?php
use app\admin\controller\Ajax;
use app\admin\controller\Index;
return [
    //全局语言包
    'default'=>[
        '添加'=>'添加',
        '编辑'=>'編輯',
        '删除'=>'刪除',
        '更多'=>'更多',
        '正常'=>'正常',
        '隐藏'=>'隱藏',
        '是'=>'是',
        '否'=>'否',
    ],
    //控制器语言包
    'controller'=>[
        Index::class=>[

        ],
        Ajax::class=>[

        ]
        //...自己加
    ]
];