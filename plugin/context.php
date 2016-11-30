<?php

namespace Plugin {

  use Model;

  class Context implements \SlackPlugin {

    const DEFAULT_BEFORE = 2;
    const DEFAULT_AFTER = 2;
    const MAX_BEFORE = 10;
    const MAX_AFTER = 10;

    public static function trigger($slack, $params) {

      $before = self::DEFAULT_BEFORE;
      $after = self::DEFAULT_AFTER;
      $id = null;

      foreach ($params as $param) {
        if (strpos($param, '-b') !== false) {
          $tmp = (int) str_replace('-b', '', $param);
          $before = $tmp > 0 && $tmp <= self::MAX_BEFORE ? $tmp : $before;
        } else if (strpos($param, '-a') !== false) {
          $tmp = (int) str_replace('-a', '', $param);
          $after = $tmp > 0 && $tmp > self::MAX_AFTER ? $tmp : $after;
        } else {
          $id = base_convert($param, 36, 10);
        }
      }

      if ($id && $id > 0) {
        $request = $slack->getRequest();
        $message = Model\Message::getById($id);

        if ($message && $message->channel === $request->channel_name && $message->team === $request->team_domain) {
          $beforeMessages = Model\Message::queryReturnAll([
            'id' => [ 'ne' => $id ],
            'date' => [ 'lt' => $message->date ],
            'team' => $request->team_domain,
            'channel' => $request->channel_name
          ], [
            'date' => 'desc',
            'id' => 'desc'
          ], $before);

          $afterMessages = Model\Message::queryReturnAll([
            'id' => [ 'ne' => $id ],
            'date' => [ 'gt' => $message->date ],
            'team' => $request->team_domain,
            'channel' => $request->channel_name
          ], [
            'date' => 'asc',
            'id' => 'asc'
          ], $after);

          $messages = array_merge(array_reverse($beforeMessages),  [ $message ], $afterMessages);
          $out = '';
          foreach ($messages as $message) {
            $b = $message->id === $id ? '*' : '';
            $out .= $b . '[' . date('Y-m-d h:i:sa', $message->date) . ']' . $b . ' *' . $message->userName . '*: ' . $message->body . PHP_EOL;
          }

          $slack->respond($out);
        }


      }

    }
  }
}