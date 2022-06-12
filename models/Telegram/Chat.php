<?php

namespace wm\bots\models\Telegram;


class Chat extends \wm\bots\models\Chat
{
    public static function tableName()
    {
        return 'telegram.chat';
    }

    public function getSession()
    {
        return $this->hasOne(Session::className(), ['id' => 'session_id']);
    }
}
