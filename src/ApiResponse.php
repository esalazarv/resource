<?php

namespace Esalazarv\Resource;

class ApiResponse implements \IteratorAggregate
{
    public function __construct($attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __call($name, $params = [])
    {

        return empty($params) ? $this->attributes[$name] : $this->attributes[$name][$params[0]];
    }

    public function __toString()
    {
        return json_encode($this->attributes['body']);
    }


    public function getIterator() {
        return $this->attributes['body'];
    }
}