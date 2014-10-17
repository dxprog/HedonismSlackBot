<?php

// Does slack stuff for dbwt

require('config.php');
require('slack.php');

Lib\Db::getConnection();

$slack->process();
