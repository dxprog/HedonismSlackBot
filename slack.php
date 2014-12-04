<?php

define('TRIGGER_CHARACTER', ':');

class Slack {

    private $responded = false;

    public static function getSlack() {
        static $instance;
        if (!$instance) {
            $instance = new Slack();
        }
        return $instance;
    }

    private function __construct() {
        spl_autoload_register('Slack::autoloader');
    }

    public static function autoloader($library) {
        
        global $_apiPath;
        
        $library = explode('\\', $library);
        $filePath = '.';
        foreach ($library as $piece) {
            $filePath .= '/' . strtolower($piece);
        }
        $filePath .= '.php';
        
        if (is_readable($filePath)) {
            require_once($filePath);
        }
        
    }

    public function process() {

        $request = $this->_getRequest();

        if ($request->text && $request->text{0} === TRIGGER_CHARACTER) {
            $tokens = explode(' ', $request->text);
            $plugin = $this->_getPluginName(array_shift($tokens));
            if ($plugin && class_exists('Plugin\\' . $plugin, true)) {
                call_user_func([ 'Plugin\\' . $plugin, 'trigger' ], $this, $tokens);
            }
        }

        if (isset($request->user_id)) {
            $message = new Model\Message;
            $message->date = time();
            $message->userId = $request->user_id;
            $message->userName = $request->user_name;
            $message->body = $request->text;
            $message->sync();
        }
    }

    private function _getRequest() {
        $retVal = new stdClass;
        
        if ($_POST) {
            foreach ($_POST as $key => $val) {
                $retVal->$key = $val;
            }
        }

        return $retVal;
    }

    public function respond($text, stdClass $options = null) {

        if (!$this->responded) {
            header('Content-Type: application/json; charset=utf-8');
            $out = new stdClass;
            $out->text = $text;
            $out->mrkdown = true;

            echo json_encode($out);
            $this->responded = true;
        }

    }

    /**
     * Returns the plugin name from a message token and does some validation
     */
    private function _getPluginName($token) {
        $token = substr($token, 1);
        if (!preg_match('/[\w]+/', $token)) {
            $token = null;
        }
        return $token;
    }

}

$slack = Slack::getSlack();
