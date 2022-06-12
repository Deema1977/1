<?php

namespace wm\bots\models\VK;

/**
 * Class Chat
 * @package wm\bots\models\VK
 *
 * @property $last_message_id
 * @property $last_vk_message_id
 */

class Chat extends \wm\bots\models\Chat
{
    protected $MESSAGES_HISTORY_LIMIT = 50;

    public static function tableName()
    {
        return 'vkcom.chat';
    }

    public function getSession()
    {
        return $this->hasOne(Session::className(), ['id' => 'session_id']);
    }


    /**
     * @return mixed
     * @throws \yii\db\Exception
     */

    public function newBotMessageId() {
        $db = self::getDb();

        $db->createCommand()->insert('vkcom.chat_message', [
            'bot_id' => $this->bot_id,
            'chat_id' => $this->chat_id,
        ])->execute();

        return $db->getLastInsertID();
    }

    /**
     * @param $messageId
     * @param $vkMessageId
     * @throws \yii\db\Exception
     */

    public function setVkMessageId($messageId, $vkMessageId) {
        $db = self::getDb();

        $messageId = (int)$messageId;
        $db->createCommand()->update('vkcom.chat_message', ['vk_message_id' => $vkMessageId], "bot_id='{$this->bot_id}' AND chat_id='{$this->chat_id}' AND message_id='{$messageId}'")
            ->execute();
    }


    public function getVkMessageId($messageId) {
        $db = self::getDb();
        $messageId = (int)$messageId;

        $row = $db->createCommand("SELECT vk_message_id FROM vkcom.chat_message WHERE bot_id='{$this->bot_id}' AND chat_id='{$this->chat_id}' AND message_id={$messageId}")->queryOne();

        if ($row===false)
            return null;

        $id = (int)$row['vk_message_id'];

        return $id>0?$id:null;
    }


    public function getBotMessageId($vkMessageId) {
        $db = self::getDb();
        $vkMessageId = (int)$vkMessageId;

        $row = $db->createCommand("SELECT message_id FROM vkcom.chat_message WHERE bot_id='{$this->bot_id}' AND chat_id='{$this->chat_id}' AND vk_message_id={$vkMessageId}")->queryOne();

        if ($row===false)
            return null;

        $id = (int)$row['message_id'];

        return $id>0?$id:null;
    }

    /**
     * @throws \yii\db\Exception
     */

    public function clearMessages() {
        $db = self::getDb();

        $db->createCommand(
            "DELETE FROM vkcom.chat_message 
                  WHERE bot_id='{$this->bot_id}' AND chat_id='{$this->chat_id}' AND message_id <
	                (SELECT min(message_id) FROM (SELECT message_id FROM vkcom.chat_message WHERE bot_id='{$this->bot_id}' AND chat_id='{$this->chat_id}' ORDER BY message_id DESC LIMIT {$this->MESSAGES_HISTORY_LIMIT}) as a)"
        )->execute();
    }
}
