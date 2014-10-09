<?php

namespace Plugin {
    
    class Pizza implements \SlackPlugin {

        public static function trigger($slack, $params) {

            $person = array_shift($params);
            $slack->respond('Sending a Papa John\'s® *' . implode(' ', $params) . '* pizza to @' . $person . '\'s house');

        }

    }

}