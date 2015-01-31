<?php

namespace Plugin {

    use Lib;
    use stdClass;

    class Search implements \SlackPlugin {

        const MAX_PHRASES = 10;

        public static function trigger($slack, $params) {

            $user = false;

            if (is_array($params) && count($params)) {
                if ($params[0]{0} === '@') {
                    $user = str_replace('@', '', array_shift($params));
                }
            }

            $phrase = trim(implode(' ', $params));
            if (strlen($phrase)) {
                $params = [ ':phrase' => '%' . $phrase . '%' ];
                $query = 'SELECT * FROM messages WHERE message_body LIKE :phrase AND message_body NOT LIKE ":search%" AND message_user_name != "slackbot"';
                if ($user) {
                    $params[':user'] = $user;
                    $query .= ' AND message_user_name = :user';
                }
                $query .= ' ORDER BY message_date DESC LIMIT 5';
                $result = Lib\Db::Query($query, $params);
                if ($result && $result->count) {
                    $out = [];
                    while ($row = Lib\Db::Fetch($result)) {
                        $out[] = '"' . $row->message_body . '" - ' . $row->message_user_name . ', ' . date('F j, Y g:ia', $row->message_date);
                    }
                    $slack->respond(implode(PHP_EOL, $out));
                }
            }

        }
    }
}
