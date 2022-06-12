<?php

namespace wm\bots\models\Telegram;


class Session extends \wm\bots\models\Session
{
    public static function tableName()
    {
        return 'telegram.session';
    }
}
