<?php

namespace wm\bots\models\VK;

use Yii;

/**
 * Class Bot
 * @package app\models\VK
 *
 *
 * @property $id
 * @property $name
 * @property $api_key
 * @property $route
 * @property $hello_message
 *
 */

class Bot extends \wm\bots\models\Bot
{
    public $hideAnswerButtons = false;

    public static function tableName()
    {
        return 'vkcom.bot';
    }

    public function rules()
    {
        return [
            [['name', 'api_key', 'confirmation_code', 'route', 'hello_message', 'lang'], 'required'],
            [['settings', 'invalid_answer_message'], 'safe']
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => 'Название',
            'api_key' => 'API Token',
            'confirmation_code' => 'Code for confirmation',
            'route' => 'URL',
            'hello_message' => 'Приветственное сообщение',
            'invalid_answer_message' => 'Сообщение при неправильном ответе',
            'lang' => 'Язык',
        ];
    }

    public function getAlgorithms()
    {
        return $this->hasMany(BotAlgorithm::className(), [
            'bot_id' => 'id'
        ])->orderBy(['id' => SORT_ASC]);
    }

    public function getWebhookUrl()
    {
        return "https://bot.riskover.ru/bots/vk/{$this->route}/";
    }
}
