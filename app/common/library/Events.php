<?php
namespace app\common\library;

class Events
{
    private string $class;
    private string $action;
    private array $params;

    public function __construct(string $class,string $action,array $params)
    {
        $this->class = $class;
        $this->action = $action;
        $this->params = $params;
    }


}