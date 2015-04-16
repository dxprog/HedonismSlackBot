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

            $mh = curl_multi_init();

            // Sanitize the array and start building up requests
            $requests = [];
            foreach($stocks as $stock) {
                if (preg_match('/^[A-Za-z]+$/', $stock)) {
                    $request = self::_createStockRequest($stock);
                    curl_multi_add_handle($mh, $request->dataRequest);
                    $requests[] = $request;
                }
            }

            // Execute everything
            $active = null;
            do {
                $mrc = curl_multi_exec($mh, $active);
            }  while ($mrc == CURLM_CALL_MULTI_PERFORM);
            while ($active && $mrc == CURLM_OK) {
                if (curl_multi_select($mh) != -1) {
                    do {
                        $mrc = curl_multi_exec($mh, $active);
                    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
                }
            }

            $out = [];
            foreach ($requests as $request) {
                $out[] = self::_processStockRequest($mh, $request);
            }

            curl_multi_close($mh);

            if (count($out) > 0) {
                $slack->respond(implode(' | ', $out));
            }

        }

        private static function _processStockRequest($mh, $request) {
            $dataContent = json_decode(curl_multi_getcontent($request->dataRequest));
            curl_multi_remove_handle($mh, $request->dataRequest);

            return ($dataContent->Change > 0 ? ':green_heart:' : ':broken_heart:') . ' *' . $request->symbol . '* - ' . $dataContent->LastPrice . ' (' . round($dataContent->Change, 2) . ')';
        }

        private static function _createRequest($url) {
            $c = curl_init();
            curl_setopt($c, CURLOPT_URL, $url);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
            return $c;
        }

        /**
         * Creates two requests. One to get current data, one to get historical data with which to determine
         * the amount the stock is up/down currently
         */
        private static function _createStockRequest($symbol) {
            return (object)[
                'symbol' => $symbol,
                'dataRequest' => self::_createRequest('http://dev.markitondemand.com/Api/v2/Quote/json?symbol=' . $symbol),
            ];
        }

    }

}
