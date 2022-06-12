<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 20.02.2017
 * Time: 00:39
 */
namespace wm\bots\models\VK;

use Yii;


class BotAlgorithm extends \wm\bots\models\BotAlgorithm
{
    public static function tableName()
    {
        return 'vkcom.bot_algorithm';
    }
}
