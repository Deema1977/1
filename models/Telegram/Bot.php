<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 20.02.2017
 * Time: 00:39
 */
namespace wm\bots\models\Telegram;

use Yii;
use yii\db\ActiveRecord;

/**
 * Class Bot
 * @package app\models\Telegram
 *
 *
 * @property $id
 * @property $name
 * @property $api_key
 * @property $route
 * @property $hello_message
 * @property $invalid_answer_message
 *
 */

class Bot extends \wm\bots\models\Bot
{

    public static function tableName()
    {
        return 'telegram.bot';
    }

    public function rules()
    {
        return [
            [['name', 'api_key', 'route', 'hello_message', 'lang'], 'required'],
            [['settings', 'invalid_answer_message'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => 'Название',
            'api_key' => 'API Token',
            'route' => 'URL',
            'hello_message' => 'Приветственное сообщение',
            'invalid_answer_message' => 'Сообщение при неправильном ответе',
            'lang' => 'Язык',
         //   'settings' => 'Настройки'
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
        return "https://api.telegram.org/bot{$this->api_key}/setWebhook?url=https://bot.riskover.ru/bots/telegram/{$this->route}/";
        //return "https://bot.riskover.ru/bot/telegram/{$this->route}/";
    }

}
