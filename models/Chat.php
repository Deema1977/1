<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 20.02.2017
 * Time: 00:39
 */
namespace wm\bots\models;

use Yii;
use yii\base\Exception;
use yii\db\ActiveRecord;

/**
 * Class Chat
 * @package app\models\Telegram
 *
 * @property $bot_id
 * @property $chat_id
 * @property $state
 * @property $session_id
 * @property Session $session
 */

abstract class Chat extends ActiveRecord
{
    const ST_HELLO_MESSAGE = 'hello_message';
    const ST_ALGORITHM_LIST = 'algorithm_list';
    const ST_ANSWER_ALGORITHM = 'answer_algorithm';
    const ST_SHOW_NODE = 'show_node';
    const ST_NEXT_NODE = 'next_node';
    const ST_ANSWER_QUESTION = 'answer_question';
    const ST_ANSWER_INFO = 'answer_info';
    const ST_ALGORITHM_DONE = 'algorithm_done';
    const ST_PREV_NODE = 'prev_node';
    const ST_ANSWER_COMPLEX_QUESTION = 'answer_complex_question';
    const ST_ANSWER_QUESTION_BY_ID = 'answer_question_by_id';


    //abstract public static function tableName();
    //abstract public function getSession();

    /**
     * @param $botId
     * @param $chatId
     * @return Chat
     * @throws Exception
     */

    public static function instance2($botId, $chatId)
    {
        Yii::trace("find bot-chat " . $botId . " chat:" . $chatId, __METHOD__);
        $chat = static::findOne(['bot_id' => $botId, 'chat_id' => $chatId]);

        if ($chat) {
            return $chat;
        }

        $chat = new static();
        $chat->bot_id = $botId;
        $chat->chat_id = $chatId;
        $chat->state = self::ST_ALGORITHM_LIST;

        if (!$chat->save()) {
            throw new Exception("Can't create chat");
        }

        $chat = static::findOne(['bot_id' => $botId, 'chat_id' => $chatId]);

        if (!$chat) {
            throw new Exception("Can't find chat");
        }

        return $chat;
    }

    public function setState($state)
    {
        if ($state != $this->state) {
            $this->state = $state;
            $this->save();
        }
    }

    public function reset()
    {
        $this->state = self::ST_HELLO_MESSAGE;
        $this->session_id = null;
        $this->save();
    }
}
