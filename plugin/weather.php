<?php

namespace Plugin {

  use Lib;
  use stdClass;

  $WINDS = [
    'S' => 'south',
    'N' => 'north',
    'E' => 'east',
    'W' => 'west',
    'NE' => 'northeast',
    'NNE' => 'north-northeast',
    'NNW' => 'north-northwest',
    'NW' => 'northwest',
    'SE' => 'southeast',
    'SSE' => 'south-southeast',
    'SSW' => 'south-southwest',
    'SW' => 'southwest',
    'WNW' => 'west-northwest',
    'SWS' => 'south-southwest',
    'ENE' => 'east-northeast',
    'ESE' => 'east-southeast',
    'V' => 'variable'
  ];

  class Weather implements \SlackPlugin {

    public static function trigger($slack, $params) {

      $attributes = '@attributes';

      $zip = array_shift($params);
      if (is_numeric($zip) && strlen($zip) === 5) {
        $data = self::_loadWeatherData($zip);
        $out = '';
        if (!isset($data->current->$attributes->fallback)) {
          $out = '*Weather Conditions and Forecast for ' . $data->current->location . '*' . PHP_EOL;
          $out .= self::_currentConditions($data->current);
        } else if ($data->tomorrow) {
          $out = '*Weather Forecast for ' . $zip . '*' . PHP_EOL;
        }

        if (count($data->hourly) > 0) {
          $out .= ' ' . self::_todayForecast($data->hourly);
        }

        if ($data->tomorrow) {
          $out .= self::_tomorrowForecast($data->tomorrow);
        }

        $slack->respond($out);

      }

    }

    private static function _loadWeatherData($zip) {
      $retVal = false;
      $xml = simplexml_load_file('http://kotv.com/api/GetForecast.ashx?site=2&action=WxForecast2012&target=data&zip=' . $zip);
      if ($xml) {
        $json = json_decode(json_encode($xml));
        $retVal = (object)[
          'current' => $json->conditions->sfc_ob,
          'tomorrow' => array_shift($json->forecast->forecast->daily_summary),
          'hourly' => $json->hourly->locations->location->forecasts->hourly
        ];
      }
      return $retVal;
    }

    private static function _currentConditions($data) {

      $retVal = '';
      if (preg_match('/(cloudy|sunny|partly|mostly|clear)/i', $data->wx)) {
        $retVal = 'The skies over ' . $data->location . ' are ' . strtolower($data->wx) . ', ' . self::_timeOfDay();
      } else {
        $retVal = $data->location . ' is experiencing some ' . strtolower($data->wx) . ', ' . self::_timeOfDay();
      }

      $retVal .= ', with a current temperature of ' . $data->temp . ' degrees ';
      if ($data->apparent_temp != $data->temp) {
        $retVal .= ', though it feels like ' . $data->apparent_temp;
      }
      $retVal .= '. ' . self::_generateWindStatement($data->wnd_spd, $data->wnd_dir);
      $retVal .= ' and the relative humidity is sitting at about ' . $data->rh . '%.';

      return $retVal;
    }

    private static function _todayForecast($data) {

      $retVal = '';
      $pop = 0;

      $hour = date('H');
      $today = date('Y-m-d');
      $tomorrow = date('Y-m-d', strtotime('+24 hours'));
      if ($hour < 15) {
        $retVal = 'This afternoon, expect a high of about ';
        $high = 0;
        foreach ($data as $hourly) {
          if (strpos($hourly->time_local, $today) !== false) {
            $high = (int) $hourly->temp_F > $high ? (int) $hourly->temp_F : $high;
            $pop = (int) $hourly->pop > $pop ? (int) $hourly->pop : $pop;
          }
        }
        $retVal .= $high . ' degrees';
      } else {
        $retVal = 'Expect temperatures to dip to around ';
        $low = 200;
        foreach ($data as $hourly) {
          $timeHour = date('H', strtotime($hourly->time_local));
          if (strpos($hourly->time_local, $tomorrow) !== false && $timeHour < 12) {
            $low = (int) $hourly->temp_F < $low ? (int) $hourly->temp_F : $low;
            $pop = (int) $hourly->pop > $pop ? (int) $hourly->pop : $pop;
          }
        }
        $retVal .= $low . ' degrees during the overnight and early morning hours';
      }

      if ($pop > 0) {
        $retVal .= ', with a ' . $pop . '% chance of precipitation';
      }

      $retVal .= '.';

      return $retVal;

    }

    private static function _tomorrowForecast($data) {

    }

    private static function _timeOfDay() {
      $hour = date('H');
      $retVal = '';
      if ($hour > 3 && $hour < 12) {
        $retVal = 'this morning';
      } else if ($hour >= 12 && $hour < 18) {
        $retVal = 'this afternoon';
      } else if ($hour >= 18 && $hour < 21) {
        $retVal = 'this evening';
      } else {
        $retVal = 'tonight';
      }
      return $retVal;
    }

    private static function _generateWindStatement($speed, $direction) {
      $wind = 'Winds are light and variable';
      $windDir = isset($WINDS[strtoupper($direction)]) ? $WINDS[strtoupper($direction)] : 'variable';
      if ($speed > 2 && $speed < 10) {
        if ($windDir !== 'variable') {
          $wind = 'There\'s a light breeze out of the ' . $windDir;
        }
      } else if ($speed >= 10 && $speed < 20) {
        if ($windDir !== 'variable') {
          $wind = 'Winds are blowing in from the ' . $windDir;
        } else {
          $wind = 'Winds are gusting up to ' . $speed . 'mph';
        }
      } else if ($speed >= 20) {
        $wind = 'Strong winds are gusting in from the ' . $windDir . ' at around ' . $speed . 'mph';
      }
      return $wind;
    }

  }

}

//