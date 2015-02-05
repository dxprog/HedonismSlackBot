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
                        $afterHours = [];
                        foreach ($data as $info) {
                            $emoji = (float) $info->c > 0 ? ':green_heart:' : ':broken_heart:';
                            $out[] = $emoji . ' *' . $info->t . '* - ' . $info->l_cur . ' (' . $info->c . ')';
                            if (isset($info->el_cur) && $info->el_cur) {
                                $afterHours[] = '*' . $info->t . '* - ' . $info->el_cur;
                            }
                        }

                        $out = implode(' | ', $out);
                        if (count($afterHours)) {
                            $out .= PHP_EOL . 'After hours: ' . implode(' | ', $afterHours);
                        }
                        $slack->respond($out);

                    }
                } else {
                    echo 'no data';
                }
            }

        }

    }

}