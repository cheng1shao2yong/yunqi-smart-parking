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

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class Test extends Command
{
    protected $output;

    protected function configure()
    {
        $this->setName('Test')->setDescription('测试');
    }

    protected function execute(Input $input, Output $output)
    {

    }
}