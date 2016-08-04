<?php

namespace Model {

    use Lib;

    class Message extends Lib\Dal {

        protected $_dbTable = 'messages';
        protected $_dbPrimaryKey = 'id';
        protected $_dbMap = [
            'id' => 'message_id',
            'userId' => 'message_user_id',
            'userName' => 'message_user_name',
            'date' => 'message_date',
            'body' => 'message_body',
            'team' => 'message_team',
            'channel' => 'message_channel'
        ];

        public $id;
        public $userId;
        public $userName;
        public $date;
        public $body;
        public $team;
        public $channel;

    }

}
