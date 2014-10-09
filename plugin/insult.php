<?php

namespace Plugin {
    
    class Insult implements \SlackPlugin {

        private static $_first = [
          'lazy',
          'stupid',
          'insecure',
          'idiotic',
          'slimy',
          'slutty',
          'pompous',
          'communist',
          'dicknose',
          'pie-eating',
          'racist',
          'elitist',
          'white trash',
          'drug-loving',
          'butterface',
          'tone deaf',
          'ugly',
          'creepy'
        ];

        private static $_second = [
          'douche',
          'ass',
          'turd',
          'rectum',
          'butt',
          'cock',
          'shit',
          'crotch',
          'bitch',
          'turd',
          'prick',
          'slut',
          'taint',
          'fuck',
          'dick',
          'boner',
          'shart',
          'nut',
          'sphincter',
          'oompa loompa'
        ];

        private static $_third = [
          'pilot',
          'canoe',
          'captain',
          'pirate',
          'hammer',
          'knob',
          'box',
          'jockey',
          'nazi',
          'waffle',
          'goblin',
          'blossum',
          'biscuit',
          'clown',
          'socket',
          'monster',
          'hound',
          'dragon',
          'balloon',
          'donkey fucker',
          'monkey problem'
        ];

        public static function trigger($slack, $params) {
            if (count($params)) {
                $slack->respond($params[0] . ' is a ' . self::_getRandom(self::$_first) . ' ' . self::_getRandom(self::$_second) . ' ' . self::_getRandom(self::$_third));
            }
        }

        private static function _getRandom($arr) {
            return $arr[rand() % (count($arr) - 1)];
        }

    }

}