<?php

namespace wm\bots\controllers;

use Yii;

use wm\bots\models\Bot;
use wm\bots\models\Chat;
use wm\bots\models\Session;
use wm\bots\models\VK;

use yii\httpclient\Client;

class VkController extends BotController
{

    const MAX_MESSAGE_LEN = 600;
    const MAX_ANSWER_LEN = 40;

    const MAX_INLINE_BUTTONS_CNT = 6;
    const MAX_CONTROL_BUTTONS_CNT = 8;

    const BUTTONS_SEPARATOR = '.';


    protected $peerId;
    protected $btnGroup = 0;

    protected $replyBotMessageId;

    /**
     * @var VK\Chat
     */
    protected $chat;


    /*
    protected function sendDebugMessage(Bot $bot) {

        try {

            $this->vkSend('Мое тестовое сообщение 10', [
                'inline' => true,
                'buttons' => [

                    [['action' => [
                        'type' => 'text',
                        'payload' => json_encode(['replyId' => $this->chat->last_vk_message_id+1]),
                        'label' => 'Callback-кнопка 500'
                    ],
                        'color' => 'primary'
                       // 'color' => 'secondary'
                    ]]

                    [['action' => [
                        'type' => 'text',
                        'payload' => json_encode(['replyId' => $this->chat->last_vk_message_id+1]),
                        'label' => 'Callback-кнопка 600'
                    ],
                        'color' => 'primary'
                        // 'color' => 'secondary'
                    ]]

                ]

             ]
            , true, $this->peerId, $this->replyMessageId
            );

        } catch (\Exception $e) {
                   }


        $this->sendMessage([
           'text' => 'Тестовое сообщение 5',
           'control_buttons' => true,
        ]);


        return 'ok';
    }
*/

    /**
     * @param Bot $bot
     * @return mixed|string
     * @throws \Exception
     *
     */
    public function actionAlgorithm(Bot $bot)
    {
        try {
            $this->bot = $bot;
            $request = Yii::$app->request;

            if (!$request->isPost)
                return 'Request is not POST';

            $message = $request->getBodyParams();


            switch ($message['type']) {
                case 'confirmation':
                    return $bot->confirmation_code;

                case 'message_reply':
                    return 'ok';

                case 'message_new':

                        Yii::info("Request:\n".$request->getRawBody(), 'bots');
                        $params = $this->getMessageParams($message);


                        $this->chat = VK\Chat::instance2($bot->id, $params['chatId']);

                        if ($this->chat->last_vk_message_id >= $params['messageId'])
                            return 'ok';

                        
                        $this->peerId = $params['peerId'];

                        $this->chat->last_vk_message_id = $params['messageId'];
                        $this->chat->save();

                        if (isset($params['buttonData']['replyId'])) {
                            $this->replyBotMessageId = (int)$params['buttonData']['replyId'];
                            $this->replyMessageId = $this->chat->getVkMessageId($this->replyBotMessageId);

                            $this->btnGroup = isset($params['buttonData']['btnGroup'])?(int)$params['buttonData']['btnGroup']:0;
                        }

                        /*
                        $this->sendDebugMessage($bot);

                        $this->chat->last_vk_message_id = $params['messageId'];
                        $this->chat->save();

                        return 'ok';
                        */

                        $this->runAlgorithm($params['answerText'], $params['buttonData']);
                        $this->chat->save();

                        $this->chat->clearMessages();

                    return 'ok';
            }

        } catch (\Exception $e) {
            $error = $e->getMessage();

            Yii::error($error, 'bots');
            $this->sendDebugMessage('ERROR: '.$error);

            Yii::error($e->getMessage(), 'bots');
        }

        return 'ok';
    }

    public function getMessageParams($message) {

        $result = [
            'chatId' => $message['object']['message']['from_id'],
            'answerText' => '',
            'buttonData' => [],
            'messageId' => $message['object']['message']['id'],
            'peerId' => null,
        ];


        if (isset($message['object']['message']['payload'])) {
            $data = trim($message['object']['message']['payload']);

            if ($data[0]!=='{')
                $result['answerText'] = $message['object']['message']['text'];
            else {
                $data = json_decode($data, true);

                if (isset($data['text']))
                    $result['answerText'] = $data['text'];
                else
                    $result['buttonData'] = $data;
            }

        } else {
            $result['answerText'] = $message['object']['message']['text'];
        }


        if (empty($result['chatId']))
            throw new \Exception("Chat id is not set");


        if (isset($message['object']['message']['peer_id']))
            $result['peerId'] = $message['object']['message']['peer_id'];

        return $result;
    }


    /**
     * @param $text
     * @param array $buttons
     * @param bool $controlButtons
     * @throws \Exception
     */

    protected function sendMessage($text, $buttons = [], $controlButtons = false, $header = '')
    {
        $vkMessageId = 0;
        $keyboard = [];
        $messages = $this->splitMessage($text);

        $lastMessIdx = count($messages)-1;

        for ($i = 0; $i < $lastMessIdx; $i++) {
            $this->vkSend($messages[$i], []);
        }

        $message = $messages[$lastMessIdx];


        if (count($buttons)) {
            $buttonGroups = array_chunk($buttons, self::MAX_INLINE_BUTTONS_CNT);

            foreach ($buttonGroups as $num => $buttonGroup) {
                $botMessageId = $this->chat->newBotMessageId();

                $keyboard = $this->getInlineKeyboard($buttonGroup, $botMessageId, $num);

                $vkMessageId = $this->vkSend($message, $keyboard);

                $this->chat->setVkMessageId($botMessageId, $vkMessageId);

                $this->chat->last_message_id = $botMessageId;
                $this->chat->last_vk_message_id = $vkMessageId;

                $message = self::BUTTONS_SEPARATOR;
            }
        }
        elseif ($controlButtons) {
            $keyboard = $this->getControlKeyboard();
            $vkMessageId = $this->vkSend($message, $keyboard);

        } else {
            $vkMessageId = $this->vkSend($message, $keyboard);
        }

        return $vkMessageId;

    }


    /**
     * @param $text
     * @param array $buttons
     * @param bool $controlButtons
     * @throws \Exception
     */

    protected function editMessage($text, $buttons = [], $header = '')
    {
        if (!$this->replyBotMessageId)
            $this->replyBotMessageId = $this->chat->getBotMessageId($this->replyMessageId);

        if (!$this->replyBotMessageId)
            return;

        $keyboard = [];

        $messages = $this->splitMessage($text);
        $lastMessIdx = count($messages)-1;

        $message = $messages[$lastMessIdx];

        if (count($buttons)) {
            $buttonGroups = array_chunk($buttons, self::MAX_INLINE_BUTTONS_CNT);

            $keyboard = $this->getInlineKeyboard($buttonGroups[$this->btnGroup], $this->replyBotMessageId);

            if ($this->btnGroup>0)
                $message = self::BUTTONS_SEPARATOR;
        }

        $this->vkSend($message, $keyboard, true, $this->peerId, $this->replyMessageId);

        // if ($this->logEnabled)
        //    Yii::warning('Vk response. Url: ' . $url, __METHOD__);
    }

    /**
     * @param $chat_id
     * @param $algorithm_id
     * @param $context_id
     * @return Session|null
     */
    protected function startSession($chat_id, $algorithm_id, $context_id)
    {
        return VK\Session::start($chat_id, $algorithm_id, $context_id);
    }

    /**
     * @param $botId
     * @param $chatId
     * @return Chat
     * @throws \yii\base\Exception
     */
    protected function findChat($botId, $chatId)
    {
        return VK\Chat::instance2($botId, $chatId);
    }


    protected function getInlineKeyboard($buttons, $replyMessageId, $btnGroup = 0) {

        $keyboard = [
            'inline' => true,
            'buttons' => [],
        ];

        $buttons = array_slice($buttons, 0, self::MAX_INLINE_BUTTONS_CNT);

        foreach ($buttons as $button) {
            $text = $button['text'];
            $selected = isset($button['selected'])?$button['selected']:false;
            $continue = isset($button['continue'])?$button['continue']:false;

            if (mb_strlen($text)>self::MAX_ANSWER_LEN )
                $text = mb_substr($text, 0, self::MAX_ANSWER_LEN-3) . '...';

            if (isset($button['questionId']))
                $payload = json_encode(['questionId' => $button['questionId'], 'answerId' => $button['id'], 'replyId' => $replyMessageId, 'btnGroup' => $btnGroup]);
            elseif (isset($button['algorithmId']))
                $payload = json_encode(['algorithmId' => $button['algorithmId'], 'replyId' => $replyMessageId, 'btnGroup' => $btnGroup]);
            else
                $payload = json_encode(['text' => $button['id']]);


            if ($continue)
                $color = 'positive';
            elseif ($selected)
                $color = 'primary';
            else
                $color = 'secondary';


            $keyboard['buttons'][] = [[
                'action' => [
                    'type' => 'text',
                    'payload' => $payload,
                    'label' => $text,
                ],
                'color' => $color,
            ]];
        }

        return $keyboard;
    }


    protected function getControlKeyboard() {

        $keyboard = [
            'one_time' => false,
            'buttons' => [],
        ];

        $buttons = array_slice($this->controlButtons, 0, self::MAX_CONTROL_BUTTONS_CNT);

        foreach ($buttons as $button) {
            $text = $button['text'];

            if (mb_strlen($text)>self::MAX_ANSWER_LEN )
                $text = mb_substr($text, 0, self::MAX_ANSWER_LEN-3) . '...';

            $keyboard['buttons'][] = [[
                'action' => [
                    'type' => 'text',
                    'payload' => json_encode(['text' => $button['id']]),
                    'label' => $text
                ],
                'color' => 'secondary'
            ]];
        }

        return $keyboard;

    }



    protected function formatMessageText($text) {
        $text = parent::formatMessageText($text);

        $text = strip_tags($text, '<a>');

        $text = preg_replace(
            '/^(.*)<a href="(.*)">(.*)<\/a>(.*)$/s',
            '$1$3 ($2) $4',
            $text
        );

        $text = str_replace("&quot;", "", $text);

        // VK.com экранирует спецсимволы HTML разметки
        // перевод ссылок вида <a class="abc" href="http://url.dot/" title="exmpl">text</a> в чистый адрес: text (http://url.dot/)
        $text = preg_replace("#<a(.*?)href=\"(.*?)\"[^>]*.(.*?)<\/a>#is", " \$3 (\$2) ", $text);

        return $text;
    }


    protected function vkSend($message, $keyboard = [], $edit = false, $peerId = null, $messageId = null) {

        if ($edit)
            $method = 'messages.edit';
        else
            $method = 'messages.send';


        $url = "https://api.vk.com/method/{$method}";

        $data = [
           'v' => '5.122',
           'access_token' =>  $this->bot->api_key,
           'user_id' =>  $this->chat->chat_id,
           'random_id' => rand(1, 10000),
           'message' => $message,
           'dont_parse_links' => 1,
           'disable_mentions' => 1,
        ];


        if ($edit) {
            $data['peer_id'] = $peerId;
            $data['message_id'] = $messageId;
        }

       // if (count($keyboard))
        $data['keyboard'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);



        $client = new Client();
        $request = $client->post($url, $data, ['Content-type' => 'application/x-www-form-urlencoded'], ['timeout' => 15]);

        $response = $request->send();


        if (YII_DEBUG) {
            Yii::debug("Message:\n" . var_export($request->getData(), true), 'bots');
            Yii::debug("Response:\n" . $response->getContent(), 'bots');
        }

        if ($response->getStatusCode()!=200)
            throw new \yii\httpclient\Exception("Invalid response: ".$response->getContent());


        $responseData = json_decode($response->getContent(), true);

        if (isset($responseData['error']))
            throw new \yii\httpclient\Exception("Invalid response: ".$response->getContent());

        return isset($responseData['response'])?$responseData['response']:$responseData;
    }


    protected function sendDebugMessage($text) {
        
        if (!YII_DEBUG or !$this->chat)
            return;
        
        $url = "https://api.vk.com/method/messages.send";

        $data = [
            'v' => '5.122',
            'access_token' =>  $this->bot->api_key,
            'user_id' =>  $this->chat->chat_id,
            'random_id' => rand(1, 10000),
            'message' => $text,
            'dont_parse_links' => 1,
            'disable_mentions' => 1,
        ];


        $client = new Client();
        $request = $client->post($url, $data, ['Content-type' => 'application/x-www-form-urlencoded'], ['timeout' => 15]);

        $request->send();
    }


    protected function sendImage($imageURL, $imageFile, $captionHeader = '', $captionText = '',  $buttons = [], $controlButtons = false) {}
    protected function sendVideoURL($videoURL, $header = '', $text = '',  $buttons = [], $controlButtons = false) {}
    protected function sendURL($url, $header = '', $text = '',  $buttons = [], $controlButtons = false) {}
    protected function sendFile($path, $header = '', $text = '',  $buttons = [], $controlButtons = false) {}
}
