<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SuggestionService
{
    const CACHE_KEY = 'pf:services:suggestion:ids';

    public static function set($val)
    {
        return Redis::zadd(self::CACHE_KEY, 1, $val);
    }

    public static function del($val)
    {
        return Redis::zrem(self::CACHE_KEY, $val);
    }

    public static function add($val)
    {
        return self::set($val);
    }

    public static function rem($val)
    {
        return self::del($val);
    }
}
