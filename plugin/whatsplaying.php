<?php

namespace Plugin {

    class WhatsPlaying implements \SlackPlugin {

        public static function trigger($slack, $params) {
            if (count($params)) {
                $user = urlencode(array_shift($params));
                $response = $user . ' is listening to you';

                $data = @file_get_contents('http://dxmp.us/api/?type=json&method=stats.getUserStatus&user=' . $user);
                if ($data) {
                    $data = json_decode($data);
                    if ($data && isset($data->body->content_id)) {
                        $response = ':musical_note: ' . $user . ' is listening to ' . $data->body->content_title;
                        if (isset($data->body->album)) {
                            $response .= ' from ' . $data->body->album->title;
                        }
                    }
                }

                $slack->respond($response);
            }
        }

    }

}
