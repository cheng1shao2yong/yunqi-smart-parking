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
use swoole\websocket\Server;

class Socket extends Command
{
    protected $output;

    protected function configure()
    {
        $this->setName('Event')->setDescription('websocket服务');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output=$output;
        $ws = new \Swoole\WebSocket\Server("0.0.0.0", 8084);
        $ws->on('open', function ($ws, $request) {
            $ws->push($request->fd, "hello, welcome\n");
        });
        $ws->on('message', function ($ws, $frame) {
            $this->output("Message: {$frame->data}\n");
            $ws->push($frame->fd, "server: {$frame->data}");
        });
        $ws->on('close', function ($ws, $fd) {
            $this->output("client-{$fd} is closed\n");
        });

        $ws->start();

    }

    private function output($msg)
    {
        $this->output->info(date('Y-m-d H:i:s').'-'.$msg);
    }
}