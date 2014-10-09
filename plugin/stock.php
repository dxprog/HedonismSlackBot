<?php

namespace Plugin {

    class Stock implements \SlackPlugin {

        private static $_defaultStocks = [
            'AAPL',
            'F',
            'FB',
            'GOOG',
            'LNKD',
            'TSLA',
            'TWTR'
        ];

        public static function trigger($slack, $params) {

            $stocks = count($params) ? $params : self::$_defaultStocks;
            $stocks = !is_array($stocks) ? [ $stocks ] : $stocks;

            // Sanitize the array
            $safeStocks = [];
            foreach($stocks as $stock) {
                if (preg_match('/^[A-Za-z]+$/', $stock)) {
                    $safeStocks[] = $stock;
                }
            }

            if (count($safeStocks) > 0) {
                $url = 'http://finance.google.com/finance/info?client=ig&q=' . implode(',', $safeStocks);
                $data = @file_get_contents($url);
                if ($data) {
                    $data = json_decode(substr(trim($data), 2));
                    if ($data) {

                        $out = [];
                        foreach ($data as $info) {
                            $out[] = '*' . $info->t . '* - ' . $info->l_cur . ' (' . $info->c . ')';
                        }

                        $slack->respond(implode(' | ', $out));

                    }
                } else {
                    echo 'no data';
                }
            }

        }

    }

}