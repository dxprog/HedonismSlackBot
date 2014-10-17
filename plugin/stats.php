<?php

namespace Plugin {

    use Lib;

    class Stats implements \SlackPlugin {

        public static function trigger($slack, $params) {
            $minDate = strtotime(date('Y/m/d'));
            $result = Lib\Db::Query('SELECT COUNT(1) AS total, message_user_name FROM messages WHERE message_date >= :minDate GROUP BY message_user_name', [ ':minDate' => $minDate ]);
            if ($result && $result->count) {
                $out = 'Today\'s stats: ' . PHP_EOL;
                $total = 0;
                $users = [];
                while ($row = Lib\Db::Fetch($result)) {
                    $total += (int) $row->total;
                    $users[$row->message_user_name] = (int)$row->total;
                }
                $out .= 'Messages sent: ' . $total . PHP_EOL;
                $breakdown = [];
                foreach ($users as $user => $count) {
                    $breakdown[] = '*' . $user . '*: ' . $count . ' (' . round($count / $total * 100) . '%)';
                }
                $out .= implode('; ', $breakdown);
                $slack->respond($out);
            }
        }

    }

}
