<?php
use app\admin\controller\Ajax;
use app\admin\controller\Index;
return [
    //全局语言包
    'default'=>[
        '添加'=>'Add',
        '编辑'=>'Edit',
        '删除'=>'Delete',
        '更多'=>'More',
        '正常'=>'Normal',
        '隐藏'=>'Hidden',
        '是'=>'Yes',
        '否'=>'No',
    ],
    //控制器语言包
    'controller'=>[
        Index::class=>[

        ],
        Ajax::class=>[

        ]
        //...英语不好,自己加
    ]
];