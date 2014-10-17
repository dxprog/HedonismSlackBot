<?php

namespace Plugin {
  
  class ReplyGif implements \SlackPlugin {

    public static function trigger($slack, $params) {

      if (count($params)) {
        $tags = urlencode(implode(' ', $params));
        $gif = self::_search($tags);
        if (!$gif) {
          $gif = self::_search($tags, 'reply');
        }

        if ($gif) {
          $slack->respond($gif);
        }

      }

    }

    private static function _search($query, $type = 'tag') {
      $data = file_get_contents('http://replygif.net/api/gifs?' . $type . '=' . $query . '&api-key=39YAprx5Yi');
      $retVal = null;
      if ($data && ($data = json_decode($data)) && count($data)) {
        $retVal = $data[rand() % count($data)]->file;
      }
      return $retVal;
    }

  }

}
