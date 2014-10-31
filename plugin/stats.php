<?php

namespace Plugin {

    use Lib;
    use stdClass;

    class Stats implements \SlackPlugin {

        const MAX_PHRASES = 10;

        public static function trigger($slack, $params) {

            if (is_array($params) && count($params)) {
                switch ($params[0]) {
                    case 'phrases':
                        self::getKeywordRanks($slack, count($params) > 1 ? $params[1] : null);
                        break;
                    case 'find':
                        array_shift($params);
                        self::findPhrase($slack, $params);
                        break;
                }
            }

            $minDate = strtotime(date('Y/m/d'));
            $result = Lib\Db::Query('SELECT COUNT(1) AS total, message_user_name, SUM(LENGTH(message_body)) AS characters FROM messages WHERE message_date >= :minDate AND message_user_name != "slackbot" GROUP BY message_user_name', [ ':minDate' => $minDate ]);
            if ($result && $result->count) {
                $out = 'Today\'s stats: ' . PHP_EOL;
                $total = 0;
                $users = [];
                while ($row = Lib\Db::Fetch($result)) {
                    $total += (int) $row->characters;
                    $users[$row->message_user_name] = (int)$row->characters;
                }
                $out .= 'Chracters sent: ' . $total . PHP_EOL;
                $breakdown = [];
                foreach ($users as $user => $count) {
                    $breakdown[] = '*' . $user . '*: ' . $count . ' (' . round($count / $total * 100) . '%)';
                }
                $out .= implode('; ', $breakdown);
                $slack->respond($out);
            }
        }

        public static function findPhrase(\Slack $slack, $words) {
            $words = trim(implode(' ', $words));
            if (strlen($words) > 0) {
                $result = Lib\Db::Query('SELECT COUNT(1) AS total FROM messages WHERE message_body LIKE :words', [ ':words' => '%' . $words . '%' ]);
                if ($result && $result->count) {
                    $row = Lib\Db::Fetch($result);
                    $slack->respond('"' . $words . '" has been used ' . $row->total . ' time' . ($row->total != 1 ? 's' : ''));
                }
            }
            exit;
        }

        /**
         * Returns a list of the highest ranking keywords
         */
        public static function getKeywordRanks(\Slack $slack, $user = null) {
            $data = self::_getKeywordsData($user);

            if ($data) {
                $phrases = self::_getCommonPhrases($data);
                
                $out = [];
                $i = 0;
                foreach ($phrases as $phrase => $count) {
                    
                    $out[] = '*' . $phrase . '* - ' . $count;

                    $i++;
                    if ($i >= self::MAX_PHRASES) {
                        break;
                    }
                }

                $slack->respond(implode(PHP_EOL, $out));
            }

            exit;
        }

        private static function _getKeywordsData($user = null) {

            $retVal = null;

            $minDate = strtotime('-24 hours');
            $params = [ ':minDate' => $minDate ];

            $query = 'SELECT message_id, message_body FROM messages WHERE message_date >= :minDate AND message_user_name != "slackbot"';

            if ($user) {
                $params[':userName'] = $user;
                $query .= ' AND message_user_name = :userName';
            }

            $result = Lib\Db::Query($query, $params);
            if ($result && $result->count) {
                $retVal = self::_getKeywordsFromDbQuery($result);
            }

            return $retVal;
        }

        /**
         * Returns a list of common phrases in a list of phrases ordered by usage
         */
        public static function _getCommonPhrases($data) {

            $phrases = [];

            foreach($data as $id => $item) {

                $title = explode(' ', trim($item->keywords));
                $found = 0;
                $words = 1;
                $titleLen = count($title);
                $localPhrases = [];

                for ($i = 0; $i < $titleLen; $i++) {
                    $lastPhrase = '';
                    $phrase = '';
                    for ($j = 0, $wordsLeft = $titleLen - $i; $j < $wordsLeft; $j++) {
                        $phrase = trim($phrase . ' ' . $title[$i + $j]);

                        if (strlen($phrase) > 2 && self::_hasSimilarPhrases($phrase, $id, $data)) {
                            if (!isset($localPhrases[$phrase])) {
                                $localPhrases[$phrase] = 1;
                            } else {
                                $localPhrases[$phrase]++;
                            }
                            if (isset($localPhrases[$lastPhrase])) {
                                $localPhrases[$lastPhrase]--;
                                if ($localPhrases[$lastPhrase] <= 0) {
                                    unset($localPhrases[$lastPhrase]);
                                }
                            }
                        } else {
                            break;
                        }

                        $lastPhrase = $phrase;
                    }
                }

                // Remove duplicate phrase chunks leaving only the longest
                foreach ($localPhrases as $needle => $nCount) {
                    if ($localPhrases[$needle] > 0) {
                        foreach ($localPhrases as $haystack => $hCount) {
                            if ($needle != $haystack && strpos($haystack, $needle) !== false) {
                                $localPhrases[$needle] = 0;
                                break;
                            }
                        }
                    }
                }

                $phrases = self::_mergePhrases($localPhrases, $phrases);

            }

            $phrases = array_filter($phrases, function($a) { return $a > 0; });
            arsort($phrases);

            return $phrases;

        }

        private static function _hasSimilarPhrases($keyphrase, $postId, &$data) {
            foreach ($data as $id => $item) {
                if ($postId != $id && strpos($item->keywords, $keyphrase) !== false) {
                    return true;
                }
            }
            return false;
        }

        private static function _mergePhrases($arr, $phrases) {
            foreach ($arr as $key => $val) {
                if ($val > 0) {
                    if (isset($phrases[$key])) {
                        $phrases[$key] += $val;
                    } else {
                        $phrases[$key] = $val;
                    }
                }
            }
            return $phrases;
        }

        private static function _getKeywordsFromDbQuery($result) {
            $retVal = [];
            while ($row = Lib\Db::Fetch($result)) {
                $obj = new stdClass;
                $obj->keywords = self::_generateKeywords($row->message_body);
                $retVal[$row->message_id] = $obj;
            }
            return $retVal;
        }

        private static function _generateKeywords($text) {
            $stop = '/\b(a|\\n|able|about|above|abroad|according|accordingly|across|actually|adj|after|afterwards|again|against|ago|ahead|ain\'t|all|allow|allows|almost|alone|along|alongside|already|also|although|always|am|amid|amidst|among|amongst|an|and|another|any|anybody|anyhow|anyone|anything|anyway|anyways|anywhere|apart|appear|appreciate|appropriate|are|aren\'t|around|as|a\'s|aside|ask|asking|associated|at|available|away|awfully|back|backward|backwards|be|became|because|become|becomes|becoming|been|before|beforehand|begin|behind|being|believe|below|beside|besides|best|better|between|beyond|both|brief|but|by|came|can|cannot|cant|can\'t|caption|cause|causes|certain|certainly|changes|clearly|c\'mon|co|co.|com|come|comes|concerning|consequently|consider|considering|contain|containing|contains|corresponding|could|couldn\'t|course|c\'s|currently|dare|daren\'t|definitely|described|despite|did|didn\'t|different|directly|do|does|doesn\'t|doing|done|don\'t|down|downwards|during|each|edu|eg|eight|eighty|either|else|elsewhere|end|ending|enough|entirely|especially|et|etc|even|ever|evermore|every|everybody|everyone|everything|everywhere|ex|exactly|example|except|fairly|far|farther|few|fewer|fifth|first|five|followed|following|follows|for|forever|former|formerly|forth|forward|found|four|from|further|furthermore|get|gets|getting|given|gives|go|goes|going|gone|got|gotten|greetings|had|hadn\'t|half|happens|hardly|has|hasn\'t|have|haven\'t|having|he|he\'d|he\'ll|hello|help|hence|her|here|hereafter|hereby|herein|here\'s|hereupon|hers|herself|he\'s|hi|him|himself|his|hither|hopefully|how|howbeit|however|hundred|i\'d|ie|if|ignored|i\'ll|i\'m|immediate|in|inasmuch|inc|inc.|indeed|indicate|indicated|indicates|inner|inside|insofar|instead|into|inward|is|isn\'t|it|it\'d|it\'ll|its|it\'s|itself|i\'ve|just|keep|keeps|kept|know|known|knows|last|lately|later|latter|latterly|least|less|lest|let|let\'s|like|liked|likely|likewise|look|looking|looks|low|lower|ltd|made|mainly|make|makes|many|may|maybe|mayn\'t|me|mean|meantime|meanwhile|merely|might|mightn\'t|mine|minus|miss|more|moreover|most|mostly|mr|mrs|much|must|mustn\'t|my|myself|name|namely|nd|near|nearly|necessary|need|needn\'t|needs|neither|never|neverf|neverless|nevertheless|new|next|nine|ninety|nobody|non|none|nonetheless|noone|no-one|nor|normally|not|nothing|notwithstanding|novel|now|nowhere|obviously|of|off|often|oh|ok|okay|old|once|one|ones|one\'s|only|onto|opposite|or|other|others|otherwise|ought|oughtn\'t|our|ours|ourselves|out|outside|over|overall|own|particular|particularly|past|per|perhaps|placed|please|plus|possible|presumably|probably|provided|provides|que|quite|qv|rather|rd|re|really|reasonably|recent|recently|regarding|regardless|regards|relatively|respectively|right|round|said|same|saw|say|saying|says|second|secondly|see|seeing|seem|seemed|seeming|seems|seen|self|selves|sensible|sent|serious|seriously|seven|several|shall|shan\'t|she|she\'d|she\'ll|she\'s|should|shouldn\'t|since|six|some|somebody|someday|somehow|someone|something|sometime|sometimes|somewhat|somewhere|soon|sorry|specified|specify|specifying|still|sub|such|sup|sure|take|taken|taking|tell|tends|th|than|thank|thanks|thanx|that|that\'ll|thats|that\'s|that\'ve|the|their|theirs|them|themselves|then|thence|there|thereafter|thereby|there\'d|therefore|therein|there\'ll|there\'re|theres|there\'s|thereupon|there\'ve|these|they|they\'d|they\'ll|they\'re|they\'ve|thing|things|think|third|thirty|this|thorough|thoroughly|those|though|three|through|throughout|thru|thus|till|together|too|took|toward|towards|tried|tries|truly|try|trying|t\'s|twice|two|un|under|underneath|undoing|unfortunately|unless|unlike|unlikely|until|unto|up|upon|upwards|us|use|used|useful|uses|using|usually|v|value|various|versus|very|via|viz|vs|want|wants|was|wasn\'t|way|we|we\'d|welcome|well|we\'ll|went|were|we\'re|weren\'t|we\'ve|what|whatever|what\'ll|what\'s|what\'ve|when|whence|whenever|where|whereafter|whereas|whereby|wherein|where\'s|whereupon|wherever|whether|which|whichever|while|whilst|whither|who|who\'d|whoever|whole|who\'ll|whom|whomever|who\'s|whose|why|will|willing|wish|with|within|without|wonder|won\'t|would|wouldn\'t|yes|yet|you|you\'d|you\'ll|your|you\'re|yours|yourself|yourselves|s|you\'ve|zero)\b/i';
            $retVal = strtolower($text);

            // Remove special characters, punctuation, and stop words
            $retVal = htmlspecialchars_decode($retVal);
            $retVal = preg_replace('/http[s]?\:\/\/[^\s]+/', '', $retVal); // remove links
            $retVal = preg_replace($stop, '', $retVal);
            $retVal = str_replace(array ('.', '>', '<', '\'', '|', '[', ']', '(', ')', '{', '}', '!', '@', '#', '$', '%', '^', '&', '*', '?', '"', ':', ',', '_'), ' ', $retVal);
            $retVal = str_replace ('  ', ' ', $retVal);

            // Remove duplicate words, clean up spaces
            $temp = explode(' ', $retVal);
            $retVal = array();
            for ($i = 0, $count = count($temp); $i < $count; $i++) {
                $temp[$i] = trim($temp[$i]);
                if (strlen($temp[$i]) > 1) {
                    $place = true;
                    for ($j = 0, $c = count($retVal); $j < $c; $j++) {
                        if ($retVal[$j] == $temp[$i]) {
                            $place = false;
                            break;
                        }
                    }
                    if ($place) {
                        $retVal[] = $temp[$i];
                    }
                }
            }

            return implode(' ', $retVal);

        }

    }

}
