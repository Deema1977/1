<?php

namespace wm\bots\models;

use Yii;
use yii\db\ActiveRecord;
use yii\httpclient\Client;

/**
 * Class Bot
 * @package app\models\Vkcom
 *
 *
 * @property $id
 * @property $name
 * @property $api_key
 * @property $route
 * @property $hello_message
 * @property $invalid_answer_message
 * @property $algorithms
 *
 */

abstract class Bot extends ActiveRecord
{
    public $restartButtonText;
    public $stepBackButtonText;

    public $hideAnswerButtons = false;

    //abstract public static function tableName();
    //abstract public function rules();
    //abstract public function attributeLabels();
    //abstract public function getAlgorithms();
    //abstract public function getWebhookUrl();

    public function checkConclusionHash($hash, $chatId, $conclusionId)
    {
        return $hash == $this->conclusionHash($chatId, $conclusionId)
            ? true
            : false;
    }

    public function conclusionHash($chatId, $conclusionId)
    {
        return md5(
            $this->id .
                '|' .
                $this->api_key .
                '|' .
                $chatId .
                '|' .
                $conclusionId .
                '|riskover_telegram_bot_secret483013012'
        );
    }

    public function getSettings()
    {
        return json_decode($this->settings, true);
    }

    public function setSettings($value)
    {
        $this->settings = json_encode($value);
    }


    public function afterFind()
    {
        parent::afterFind();

        $settings = $this->settings;

        // if (isset($settings['hide_answer_buttons']))
        //    $this->hideAnswerButtons = (bool)$settings['hide_answer_buttons'];

        if (isset($settings['restart_button_text']))
            $this->restartButtonText = $settings['restart_button_text'];

        if (isset($settings['step_back_button_text']))
            $this->stepBackButtonText = $settings['step_back_button_text'];
    }
}
