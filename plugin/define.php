<?php

namespace Plugin {

    use Lib;
    use stdClass;

    class Define implements \SlackPlugin {

        public static function trigger($slack, $params) {

            if (count($params)) {
              if (strtolower($params[0]) === 'define') {
                array_shift($params);
              }

             $response = self::curl_get_contents('https://ajax.googleapis.com/ajax/services/search/web?v=1.0&q=define%20' . urlencode(implode(' ', $params)));
             if ($response) {
                $data = json_decode($response);
                if (isset($data->responseData) && count($data->responseData->results)) {
                  $result = $data->responseData->results[0];
                  $out = '*' . $result->titleNoFormatting . '*' . PHP_EOL;
                  $out .= str_replace([ '<b>', '</b>' ], '_', trim($result->content));
                  $out .= PHP_EOL . 'View full definition: ' . $result->url . ')';
                  $slack->respond($out);
                }
              }

            }

        }

        /**
         * A drop in replacement for file_get_contents with some business logic attached
         * @param string $url Url to retrieve
         * @return string Data received
         */
        private static function curl_get_contents($url) {
            $c = curl_init($url);
            curl_setopt($c, CURLOPT_REFERER, 'http://dbwt.slack.com/');
            curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
            $retVal = curl_exec($c);
            curl_close($c);
            return $retVal;
        }

    }
}
