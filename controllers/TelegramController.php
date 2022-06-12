<?php

namespace wm\bots\controllers;

use Yii;
use yii\base\Exception;
use wm\bots\models\Bot;
use wm\bots\models\Chat;
use wm\bots\models\Telegram;
use wm\bots\Module;

use yii\httpclient\Client;


class TelegramController extends BotController
{
    const MAX_MESSAGE_LEN = 4096;
    const MAX_ANSWER_LEN = 62;

    /**
     * @var Module;
     */
    public $module;

    /**
     * @var Telegram\Api
     */
    protected $telegram = null;

    /**
     * @var Telegram\Bot
     */
    protected $bot = null;

    protected $showErrors = false;


    /**
     * @param Bot $bot
     * @return string
     *
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */

    /*
    protected function sendDebugMessage(Bot $bot) {

         $telegram = new Telegram\Api($bot->api_key);

        $result = Yii::$app->getRequest()->getBodyParams();

        if (isset($result['callback_query']))
            $chatId = $result['callback_query']["message"]["chat"]["id"];
        else
            $chatId = $result["message"]["chat"]["id"];


        $message = [
            'chat_id' => $chatId,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'text' => 'Test <u>message</u> 123',
            'reply_markup' => json_encode(
                [
                    'inline_keyboard' => [
                        [['text' => '☑ Button 1', 'callback_data' => '1']],
                        [['text' => 'Button 2', 'callback_data' => '2']],
                    ],
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true,
                ]
            )
        ];

        $telegram->sendMessage($message);

        return 'OK';
    }
    */

    /**
     * @param Bot $bot
     * @return string
     */
    /*
    public function actionDebug(Bot $bot)
    {
        // return $this->sendDebugMessage($bot);

        $answerText = '';
        $buttonData = null;


        try {

            if ($this->logEnabled)
                Yii::warning('Telegram message: ' . file_get_contents('php://input'), __METHOD__);

            $result = Yii::$app->getRequest()->getBodyParams();


            if (isset($result['callback_query'])) {
                $chatId = $result['callback_query']["message"]["chat"]["id"];

                $data = trim($result['callback_query']['data']);

                if ($data[0]!=='{')
                    $answerText = $data;
                else
                    $buttonData = json_decode($data, true);

                if (isset($result['callback_query']['message']['message_id']))
                    $this->replyMessageId = $result['callback_query']['message']['message_id'];

            } else {
                $chatId = $result["message"]["chat"]["id"];
                $answerText = $result["message"]["text"];
            }


            if (empty($chatId))
                throw new \Exception("Chat id is not set");


            $this->chat = Telegram\Chat::instance2($bot->id, $chatId);
            $this->bot = $bot;


            $this->runAlgorithm($answerText, $buttonData);

        } catch (\Exception $e) {
            Yii::debug($e->getMessage(), __METHOD__);

            return 'ERROR (Debug)';
        }

        return 'OK (Debug)';
    }

    */

    public function actionAlgorithm(Bot $bot)
    {
        try {
            $this->bot = $bot;
            $request = Yii::$app->request;

            if (!$request->isPost)
                return 'Request is not POST';

            $message = $request->getBodyParams();

            Yii::info("Request:\n".$request->getRawBody(), 'bots');
            $params = $this->getMessageParams($message);


            $this->chat = Telegram\Chat::instance2($bot->id, $params['chatId']);
           

            $this->replyMessageId = $params['replyMessageId'];

            $this->runAlgorithm($params['answerText'], $params['buttonData']);

            $this->chat->save();

        } catch (\Exception $e) {
            $error = $e->getMessage();

            Yii::error($error, 'bots');
            $this->sendDebugMessage('ERROR: '.$error);

            return 'FAILED: ' . $error;
        }

        return 'OK';
    }

    public function getMessageParams($message) {

        $result = [
            'chatId' => 0,
            'answerText' => '',
            'buttonData' => [],
            'replyMessageId' => null,
        ];


        if (isset($message['callback_query'])) {
            $result['chatId'] = $message['callback_query']["message"]["chat"]["id"];

            $data = trim($message['callback_query']['data']);

            if ($data[0]!=='{')
                $result['answerText'] = $data;
            else
                $result['buttonData'] = json_decode($data, true);

            if (isset($message['callback_query']['message']['message_id']))
                $result['replyMessageId'] = $message['callback_query']['message']['message_id'];

        } else {
            $result['chatId'] = $message["message"]["chat"]["id"];
            $result['answerText'] = $message["message"]["text"];
        }


        if (empty($result['chatId']))
            throw new \Exception("Chat id is not set");


        return $result;

    }

    /**
     * @param $text
     * @param array $buttons
     * @throws \Exception
     */

    protected function editMessage($text, $buttons = [], $header = '')
    {
        if (strlen($header))
            $text  = "<b>{$header}</b>\n\n".$text;

        $messages = $this->splitMessage($text);

        $lastMessIdx = count($messages)-1;

        $keyboard = [];

        if (count($buttons))
            $keyboard['inline_keyboard'] = $this->getInlineKeyboard($buttons);


        $this->telegramSend($messages[$lastMessIdx], $keyboard, true, $this->replyMessageId);
    }

    /**
     * @param $text
     * @param array $buttons
     * @param bool $controlButtons
     * @param string $header
     * @return int
     * @throws \yii\httpclient\Exception
     */

    protected function sendMessage($text, $buttons = [], $controlButtons = false, $header = '')
    {
        if (strlen($header))
            $text  = "<b>{$header}</b>\n\n".$text;

        $messages = $this->splitMessage($text);

        $lastMessIdx = count($messages)-1;

        for ($i = 0; $i < $lastMessIdx; $i++) {
            $this->telegramSend($messages[$i], [], null);
        }

        $keyboard = [];

        if (count($buttons))
            $keyboard['inline_keyboard'] = $this->getInlineKeyboard($buttons);

        elseif ($controlButtons) {
            $keyboard['keyboard'] = $this->getControlKeyboard();
            $keyboard['resize_keyboard'] = true;
            $keyboard['one_time_keyboard'] = true;
        }


        $msgId = $this->telegramSend($messages[$lastMessIdx], $keyboard);
        return $msgId;
    }


    /**
     * @param null $imageURL
     * @param null $imageFile
     * @param string $captionHeader
     * @param string $captionText
     * @param array $buttons
     * @param bool $controlButtons
     * @return int
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */

    protected function sendImage($imageURL = null, $imageFile = null, $captionHeader = '', $captionText = '',  $buttons = [], $controlButtons = false)
    {
        if (strlen($captionHeader))
            $captionText  = "<b>{$captionHeader}</b>\n\n".$captionText;


        $keyboard = [];

        if (count($buttons))
            $keyboard['inline_keyboard'] = $this->getInlineKeyboard($buttons);

        elseif ($controlButtons) {
            $keyboard['keyboard'] = $this->getControlKeyboard();
            $keyboard['resize_keyboard'] = true;
            $keyboard['one_time_keyboard'] = true;
        }


        $msgId = $this->telegramSendPhoto($imageURL, $imageFile, $captionText, $keyboard);
        return $msgId;
    }


    /**
     * @param null $imageURL
     * @param null $imageFile
     * @param string $captionHeader
     * @param string $captionText
     * @param array $buttons
     * @param bool $controlButtons
     * @return int
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */

    protected function sendVideoURL($videoURL, $header = '', $text = '',  $buttons = [], $controlButtons = false)
    {
        if (strlen($header))
            $text = "<b>{$header}</b>\n\n".$text;

        $text = "{$text}\n<a href=\"{$videoURL}\">{$videoURL}</a>";

        $keyboard = [];

        if (count($buttons))
            $keyboard['inline_keyboard'] = $this->getInlineKeyboard($buttons);

        elseif ($controlButtons) {
            $keyboard['keyboard'] = $this->getControlKeyboard();
            $keyboard['resize_keyboard'] = true;
            $keyboard['one_time_keyboard'] = true;
        }

        $msgId = $this->telegramSend($text, $keyboard, false, null, true);

        return $msgId;
    }


    /**
     * @param $url
     * @param string $header
     * @param string $text
     * @param array $buttons
     * @param bool $controlButtons
     * @return int
     * @throws \yii\httpclient\Exception
     */

    protected function sendURL($url, $header = '', $text = '',  $buttons = [], $controlButtons = false)
    {
        if (strlen($header))
            $text = "<b>{$header}</b>\n\n".$text;

        $text = "{$text}\n<a href=\"{$url}\">{$url}</a>";

        $keyboard = [];

        if (count($buttons))
            $keyboard['inline_keyboard'] = $this->getInlineKeyboard($buttons);

        elseif ($controlButtons) {
            $keyboard['keyboard'] = $this->getControlKeyboard();
            $keyboard['resize_keyboard'] = true;
            $keyboard['one_time_keyboard'] = true;
        }

        $msgId = $this->telegramSend($text, $keyboard, false, null, true);

        return $msgId;
    }


    /**
     * @param $path
     * @param string $header
     * @param string $text
     * @param array $buttons
     * @param bool $controlButtons
     * @return int
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */

    protected function sendFile($path, $header = '', $text = '',  $buttons = [], $controlButtons = false)
    {
        if (strlen($header))
            $text  = "<b>{$header}</b>\n\n".$text;


        $keyboard = [];

        if (count($buttons))
            $keyboard['inline_keyboard'] = $this->getInlineKeyboard($buttons);

        elseif ($controlButtons) {
            $keyboard['keyboard'] = $this->getControlKeyboard();
            $keyboard['resize_keyboard'] = true;
            $keyboard['one_time_keyboard'] = true;
        }


        $msgId = $this->telegramSendDocument($path, $text, $keyboard);
        return $msgId;
    }


    /**
     * @param $text
     * @param $keyboard
     * @param bool $edit
     * @param null $messageId
     * @return int
     * @throws \yii\httpclient\Exception
     */

    protected function telegramSend($text, $keyboard, $edit = false, $messageId = null, $webPreview = false) {

        if ($edit)
            $method = 'editMessageText';
        else
            $method = 'sendMessage';


        $url = "https://api.telegram.org/bot{$this->bot->api_key}/{$method}";

        $message = [
            'text' => $text,
            'chat_id' => $this->chat->chat_id,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => !$webPreview,
        ];

        if ($edit)
            $message['message_id'] = $messageId;


        if (count($keyboard))
            $message['reply_markup'] = json_encode($keyboard);


        $client = new Client();
        $request = $client->post($url, $message, ['Content-type' => 'application/x-www-form-urlencoded'], ['timeout' => 10]);
        $response = $request->send();

        if (YII_DEBUG) {
            Yii::debug("Message:\n" . var_export($request->getData(), true), 'bots');
            Yii::debug("Response:\n" . $response->getContent(), 'bots');
        }

        if ($response->getStatusCode()!=200)
            throw new \yii\httpclient\Exception("Invalid response: ".$response->getContent());

        $responseData = json_decode($response->getContent(), true);

        if (!$responseData['ok'])
            throw new \yii\httpclient\Exception("Invalid response: ".$response->getContent());


        if (isset($responseData['result']['message_id']))
            return (int)$responseData['result']['message_id'];
        else
            return 0;
    }


    /**
     * @param $url
     * @param $file
     * @param string $caption
     * @param array $keyboard
     * @return int
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */

    protected function telegramSendPhoto($photoUrl, $photoFile, $caption = '', $keyboard = []) {


        $url = "https://api.telegram.org/bot{$this->bot->api_key}/sendPhoto";

        $message = [
            'chat_id' => $this->chat->chat_id,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if (strlen($caption))
            $message['caption'] = $caption;

        if ($photoUrl)
            $message['photo'] = $photoUrl;

        if (count($keyboard))
            $message['reply_markup'] = json_encode($keyboard);


        $client = new Client();
        $request = $client->post($url, $message, ['Content-type' => 'application/x-www-form-urlencoded'], ['timeout' => 10]);

        if (!$photoUrl and $photoFile)
            $request->addFile('photo', $photoFile);

        $response = $request->send();

        if (YII_DEBUG) {
            Yii::debug("Message:\n" . var_export($request->getData(), true), 'bots');
            Yii::debug("Response:\n" . $response->getContent(), 'bots');
        }

        if ($response->getStatusCode()!=200)
            throw new \yii\httpclient\Exception("Invalid response: ".$response->getContent());

        $responseData = json_decode($response->getContent(), true);

        if (!$responseData['ok'])
            throw new \yii\httpclient\Exception("Invalid response: ".$response->getContent());


        if (isset($responseData['result']['message_id']))
            return (int)$responseData['result']['message_id'];
        else
            return 0;
    }


    /**
     * @param $path
     * @param string $caption
     * @param array $keyboard
     * @return int
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */

    protected function telegramSendDocument($path, $caption = '', $keyboard = []) {


        $url = "https://api.telegram.org/bot{$this->bot->api_key}/sendDocument";

        $message = [
            'chat_id' => $this->chat->chat_id,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
        ];

        if (strlen($caption))
            $message['caption'] = $caption;

        if (count($keyboard))
            $message['reply_markup'] = json_encode($keyboard);


        $client = new Client();
        $request = $client->post($url, $message, ['Content-type' => 'application/x-www-form-urlencoded'], ['timeout' => 10]);
        $request->addFile('document', $path);

        $response = $request->send();

        if (YII_DEBUG) {
            Yii::debug("Message:\n" . var_export($request->getData(), true), 'bots');
            Yii::debug("Response:\n" . $response->getContent(), 'bots');
        }

        if ($response->getStatusCode()!=200)
            throw new \yii\httpclient\Exception("Invalid response: ".$response->getContent());

        $responseData = json_decode($response->getContent(), true);

        if (!$responseData['ok'])
            throw new \yii\httpclient\Exception("Invalid response: ".$response->getContent());


        if (isset($responseData['result']['message_id']))
            return (int)$responseData['result']['message_id'];
        else
            return 0;
    }




    protected function sendDebugMessage($text) {

        if (!YII_DEBUG or !$this->chat)
            return;
        
        $url = "https://api.telegram.org/bot{$this->bot->api_key}/sendMessage";

        $message = [
            'text' => $text,
            'chat_id' => $this->chat->chat_id,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        $client = new Client();
        $request = $client->post($url, $message, ['Content-type' => 'application/x-www-form-urlencoded'], ['timeout' => 10]);
        $request->send();
    }



    protected function getInlineKeyboard($buttons) {
        $keyboard = [];


        foreach ($buttons as $k=>$button) {
            if (isset($button['selected']) and $button['selected'])
                $button['text'] = '☑ ' . $button['text'];

            if (isset($button['continue']) and $button['continue'])
                $button['text'] = $button['text'].' ➡';

            if (isset($button['questionId']))
                $callbackData = json_encode(['questionId' => $button['questionId'], 'answerId' => $button['id']]);
            elseif (isset($button['algorithmId']))
                $callbackData = json_encode(['algorithmId' => $button['algorithmId']]);
            else
                $callbackData = $button['id'];

            $keyboard[] = [['text' => $button['text'], 'callback_data' => $callbackData]];
        }

        return $keyboard;
    }

    protected function getControlKeyboard() {
        $keyboard = [];

        foreach ($this->controlButtons as $button) {
            $keyboard[] = ['text' => $button['text']];
        }

        return [$keyboard];
    }


    protected function startSession($chat_id, $algorithm_id, $context_id)
    {
        return Telegram\Session::start($chat_id, $algorithm_id, $context_id);
    }

    /**
     * @param $botId
     * @param $chatId
     * @return Chat
     *
     * @throws Exception
     */

    protected function findChat($botId, $chatId)
    {
        return Telegram\Chat::instance2($botId, $chatId);
    }


    protected function formatMessageText($text) {
        $text = parent::formatMessageText($text);

        // Вырезать теги, т.к. телеграм отбрасывает такие сообщения
        $text = strip_tags($text, '<a><b><strong><i><em><code><s><del><u><pre>');

        return $text;
    }

}