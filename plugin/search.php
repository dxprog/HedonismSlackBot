<?php

namespace Plugin {

    use Lib;
    use stdClass;

    class Search implements \SlackPlugin {

        const MAX_PHRASES = 10;
        const MAX_DATE_MESSAGES = 20;
        const DATE_REGEX = '/dates ([\w\s\/]+) to ([\w\s\/]+)/i';

        public static function trigger($slack, $params) {

            $user = false;

            if (is_array($params) && count($params)) {
                if ($params[0]{0} === '@') {
                    $user = str_replace('@', '', array_shift($params));
                }
            }

            // Check for a date range search
            $phrase = trim(implode(' ', $params));

            if (strlen($phrase)) {

                if (preg_match(self::DATE_REGEX, $phrase)) {
                    self::_doDateSearch($phrase, $slack);
                } else {
                    self::_doStandardSearch($phrase, $user, $slack);
                }


            }

        }

        private static function _doStandardSearch($phrase, $user, \Slack $slack) {
            $params = [ ':phrase' => '%' . $phrase . '%' ];
            $query = 'SELECT * FROM messages WHERE message_body LIKE :phrase AND message_body NOT LIKE ":search%" AND message_user_name != "slackbot"';
            if ($user) {
                $params[':user'] = $user;
                $query .= ' AND message_user_name = :user';
            }
            $query .= ' ORDER BY message_date DESC LIMIT 5';
            $result = Lib\Db::Query($query, $params);
            if ($result && $result->count) {
                while ($row = Lib\Db::Fetch($result)) {
                    $out[] = self::_formatMessage($row);
                }
                $slack->respond(implode(PHP_EOL, $out));
            } else {
                $slack->respond('No results found :(');
            }
        }

        private static function _doDateSearch($phrase, \Slack $slack) {
            preg_match(self::DATE_REGEX, $phrase, $matches);
            $startDate = strtotime($matches[1]);
            $endDate = strtotime($matches[2]);
            $query = 'SELECT * FROM messages WHERE message_date BETWEEN :startDate AND :endDate';

            $result = Lib\Db::Query($query, [ ':startDate' => $startDate, 'endDate' => $endDate ]);
            if ($result && $result->count) {
                $out = [ '**Found ' . $result->count . ' results:**' ];
                if ($result->count > self::MAX_DATE_MESSAGES) {
                    $out = [ '**There are ' . $result->count . ' results. Showing the longest ' . self::MAX_DATE_MESSAGES . ': **' ];
                }

                $messages = [];
                while ($row = Lib\Db::Fetch($result)) {
                    $messages[] = $row;
                }

                // Sort the messages by length
                usort($messages, function($a, $b) {
                    return strlen($a->message_body) > strlen($b->message_body) ? -1 : 1;
                });

                // Take the top 20
                $messages = array_slice($messages, 0, self::MAX_DATE_MESSAGES);

                // Re-sort by date
                usort($messages, function($a, $b) {
                    return $a->message_date > $b->message_date ? -1 : 1;
                });

                // Finally, compose the message
                for ($i = 0, $count = count($messages); $i < $count; $i++) {
                    $out[] = self::_formatMessage($messages[$i]);
                }
                $slack->respond(implode(PHP_EOL, $out));

            } else {
                $slack->respond('No results found :(');
            }
        }

        private static function _formatMessage($dbRow) {
            return '"' . $dbRow->message_body . '" - ' . $dbRow->message_user_name . ', ' . date('F j, Y g:ia', $dbRow->message_date);
        }

    }
}
