<?php

interface SlackPlugin {
    public static function trigger($slack, $params);
}