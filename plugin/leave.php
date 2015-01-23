<?php

namespace Plugin {

    class Leave implements \SlackPlugin {

        private static $_phrases = [
          '*{name} has left the room*',
          '*{name} slams the door in @mak\'s face*'
        ];

        public static function trigger($slack, $params) {
            $request = $slack->getRequest();
            if ($request->user_name) {
              $phrase = self::$_phrases[rand() % count(self::$_phrases)];
              $slack->respond(str_replace('{name}', $request->user_name, $phrase));
            }
        }

    }

}