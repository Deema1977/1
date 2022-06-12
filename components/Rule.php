<?php

namespace wm\bots\components;

use Yii;
use wm\bots\models\Telegram;
use wm\bots\models\VK;

use yii\web\UrlRuleInterface;
use yii\base\BaseObject;
use yii\web\Request;


class Rule extends BaseObject implements UrlRuleInterface
{
    const MESSENGER_TELEGRAM = 'telegram';
    const MESSENGER_VK = 'vk';

    protected $messenger;


    public function createUrl($manager, $route, $params)
    {
        return false;
    }

    /**
     * @param \yii\web\UrlManager $manager
     * @param Request $request
     *
     * @return array|bool
     *
     * @throws \yii\base\InvalidConfigException
     */

    public function parseRequest($manager, $request)
    {
        $pathInfo = $request->getPathInfo();

        if (preg_match('~^bots/telegram/~', $pathInfo)) {
            $this->messenger = self::MESSENGER_TELEGRAM;

        } elseif (preg_match('~^bots/vk/~', $pathInfo)) {
            $this->messenger = self::MESSENGER_VK;

        } else {
            return false;
        }

        $pathInfo = trim($pathInfo, '/');
        $parts = explode('/', $pathInfo);

        unset($parts[0]);
        unset($parts[1]);

        if ($parts[2] == 'conclusion') {
            return $this->conclusion($request);
        }

        if ($parts[2] == 'auto-conclusion') {
            return $this->autoConclusion($request);
        }

        $bot = $this->findBot(['route' => implode('/', $parts)]);

        if (is_null($bot)) {
            return [
                "bots/{$this->messenger}/invalid",
                [ 'messenger' => $this->messenger ]
            ];
        }

        /*
        if ($bot->name == 'RiskoverDebugBot') {
            return [
                "bots/" . $this->controller_name . "/debug",
                ['bot' => $bot]
            ];
        } else {
            return [
                "bots/" . $this->controller_name . "/algorithm",
                ['bot' => $bot]
            ];
        }
        */

        return [
            "bots/{$this->messenger}/algorithm",
            [ 'bot' => $bot, 'messenger' => $this->messenger ]
        ];
    }

    /**
     * @param $find_args
     * @return mixed
     */

    protected function findBot($args)
    {
        switch ($this->messenger) {
            case self::MESSENGER_TELEGRAM:
                return Telegram\Bot::findOne($args);

            case self::MESSENGER_VK:
                return VK\Bot::findOne($args);
        }
    }

    protected function conclusion(Request $request)
    {
        $botId = $request->get('bot_id');
        $chatId = $request->get('chat_id');
        $conclusionId = $request->get('conclusion_id');
        $hash = $request->get('hash');

        $bot = $this->findBot(['id' => $botId]);

        if (is_null($bot)) {
            return ["bots/{$this->messenger}/invalid", []];
        }

        if (!$bot->checkConclusionHash($hash, $chatId, $conclusionId)) {
            return ["bots/{$this->messenger}/invalid", []];
        }

        return [
            "bots/{$this->messenger}/conclusion",
            [
                'bot' => $bot,
                'chatId' => $chatId,
                'conclusionId' => $conclusionId
            ]
        ];
    }

    protected function autoConclusion(Request $request)
    {
        $botId = $request->get('bot_id');
        $chatId = $request->get('chat_id');
        $hash = $request->get('hash');

        $bot = $this->findBot(['id' => $botId]);

        if (is_null($bot)) {
            return ["bots/{$this->messenger}/invalid", []];
        }

        if (!$bot->checkConclusionHash($hash, $chatId, 0)) {
            return ["bots/{$this->messenger}/invalid", []];
        }

        return [
            "bots/{$this->messenger}/auto-conclusion",
            ['bot' => $bot, 'chatId' => $chatId]
        ];
    }
}
