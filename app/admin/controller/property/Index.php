<?php
/**
 * ----------------------------------------------------------------------------
 * 行到水穷处，坐看云起时
 * 开发软件，找贵阳云起信息科技，官网地址:https://www.56q7.com/
 * ----------------------------------------------------------------------------
 * Author: 老成
 * email：85556713@qq.com
 */
declare (strict_types = 1);

namespace app\admin\controller\property;

use app\common\controller\Backend;
use think\annotation\route\Group;
use think\annotation\route\Route;

#[Group("parking/index")]
class Index extends Backend
{

    #[Route('GET','index')]
    public function index()
    {
        return $this->fetch();
    }
}
