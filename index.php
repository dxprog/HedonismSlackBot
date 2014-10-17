<?php

// Does slack stuff for dbwt

date_default_timezone_set('America/Los_Angeles');

require('config.php');
require('slack.php');

Lib\Db::getConnection();

$slack->process();
