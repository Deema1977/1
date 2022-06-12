<?php

namespace wm\bots\models\VK;


class Session extends \wm\bots\models\Session
{
    public static function tableName()
    {
        return 'vkcom.session';
    }
}
