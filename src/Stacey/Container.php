<?php

namespace Stacey;

class Container
{
    protected $services = [];

    public function __set($k, $c)
    {
        $this->services[$k] = $c;
    }

    public function __get($k)
    {
        return $this->services[$k]($this);
    }
}
