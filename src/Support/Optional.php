<?php

namespace PageAnalyzer\Support;

class Optional
{
    public function __call($name, $arguments)
    {
        return null;
    }
}