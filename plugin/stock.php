<?php

namespace Plugin {

    class Stock implements \SlackPlugin {

        const STOCK_FILE_LOCATION = './stocks.txt';

        private static $_stocks;

        public static function trigger($slack, $params) {

            $stocks = self::_readStockPreferences();
            $stocksToRetrieve = null;

            if (count($params)) {
                $first = strtolower($params[0]);
                if ($first === 'add') {
                    array_shift($params);
                    return self::_addUserStocks($slack, $params);
                } else if ($first === 'remove') {
                    array_shift($params);
                    return self::_removeUserStocks($slack, $params);
                } else if ($first === 'help') {
                    return;
                } else {
                    $stocksToRetrieve = is_array($params) ? $params : [ $params ];
                }
            }

            if (!$stocksToRetrieve) {
                $stocksToRetrieve = self::_getUserStocks($slack, $stocks);
            }

            $mh = curl_multi_init();

            // Sanitize the array and start building up requests
            $requests = [];
            foreach($stocksToRetrieve as $stock) {
                if (preg_match('/^[A-Za-z\.]+$/', $stock)) {
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
                $slack->respond(implode(PHP_EOL, $out));
            }

        }

        private static function _processStockRequest($mh, $request) {
            $dataContent = json_decode(curl_multi_getcontent($request->dataRequest));
            curl_multi_remove_handle($mh, $request->dataRequest);
            $retVal = $request->symbol . ' - Invalid symbol';
            if (isset($dataContent->Change)) {
                $retVal = ($dataContent->Change > 0 ? ':green_heart:' : ':broken_heart:') . ' *' . $request->symbol . '* - ' . $dataContent->LastPrice . ' (' . round($dataContent->Change, 2) . ')';
            }
            return $retVal;
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

        /**
         * Reads in the stock preferences file
         */
        private static function _readStockPreferences() {
            $retVal = [];
            $stocks = file(STOCK_FILE_LOCATION);
            if ($stocks) {
                foreach ($stocks as $user) {
                    $tokens = explode('|', $user);
                    $key = array_shift($tokens);
                    $retVal[$key] = $tokens;
                }
            }
            self::_$stocks = $retVal;
            return $retVal;
        }

        /**
         * Returns the stocks for a user. If the user doesn't have any preferences, copies default
         */
        private static function _getUserStocks($slack) {
            $request = $slack->getRequest();
            return isset($request->user_id) && isset(self::$_stocks[$request->user_id]) ? $stocks[$request->user_id] : array_slice(self::$_stocks['default'], 0);
        }

        /**
         * Updates a user's stock preferences and saves them
         */
        private static function _setUserStocks($slack, $stocks) {
            $request = $slack->getRequest();
            if (isset($request->user_id)) {
                self::$_stocks[$request->user_id] = $stocks;
            }

            $out = '';
            foreach (self::$_stocks as $user => $stocks) {
                $out .= $user . '|' . implode('|', $stocks) . PHP_EOL;
            }

            return file_put_contents(STOCK_FILE_LOCATION, $out);
        }

        /**
         * Adds stocks to a user's preferences
         */
        private static function _addUserStocks($slack, $stocks) {
            $userStocks = self::_getUserStocks($slack, self::$_stocks);
            $userStocks = array_unique(array_merge($userStocks, $stocks));
            if (self::_setUserStocks($slack, $userStocks)) {
                $slack->respond('Stocks have been added to your preferences');
            } else {
                $slack->respond('Dammit, something went wrong...');
            }
        }

        /**
         * Removes stocks from a user's preferences
         */
        private static function _removeUserStocks($slack, $stocks) {
            $userStocks = self::_getUserStocks($slack, self::$_stocks);
            $userStocks = array_unique(array_merge($userStocks, $stocks));
            if (self::_setUserStocks($slack, $userStocks)) {
                $slack->respond('Stocks have been added to your portfolio');
            } else {
                $slack->respond('Dammit, something went wrong...');
            }
        }

    }

}
