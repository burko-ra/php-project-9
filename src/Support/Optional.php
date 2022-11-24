<?php

namespace PageAnalyzer\Support;

class Optional
{
    /**
     * @param mixed $name
     * @param mixed $arguments
     * @return null
     */
    public function __call($name, $arguments)
    {
        return null;
    }
}
