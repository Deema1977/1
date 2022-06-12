<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 13.01.2018
 * Time: 13:35
 */

namespace wm\bots\models\Telegram;

use Telegram\Bot\Objects\Message;

class Api extends \Telegram\Bot\Api
{
    public function editMessage(array $params)
    {
        $response = $this->post('editMessageText', $params);

        return new Message($response->getDecodedBody());
    }
}
