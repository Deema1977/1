<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 04.12.2016
 * Time: 00:50
 */

namespace wm\bots;

use Yii;
use yii\log\FileTarget;


class Module extends \yii\base\Module
{

    public $layout = '';

    public $serviceUrl;

    public $logsEnabled = false;


    public function init()
    {
        parent::init();

        Yii::$app->i18n->translations['bots'] = [
            'class'          => 'yii\i18n\PhpMessageSource',
            'sourceLanguage' => 'en-US',
            'basePath'       => '@wm/bots/messages',
        ];


        if (!$this->logsEnabled)
            return;

        $messenger = Yii::$app->request->get('messenger');


        Yii::$app->getLog()->targets['bots-request'] = new FileTarget([
            'levels' => ['info'],
            'logVars' => [],
            'logFile' =>   "@runtime/logs/bots/{$messenger}.request.log",
            'categories' =>   ['bots'],
            'enableRotation' => true,
            'maxLogFiles' => 5,
            'maxFileSize' => 10240,
            'exportInterval' => 100,
        ]);


        Yii::$app->getLog()->targets['bots-error'] = new FileTarget([
            'levels' => ['error'],
            'logVars' => [],
            'logFile' =>   "@runtime/logs/bots/{$messenger}.error.log",
            'categories' =>   ['bots'],
            'enableRotation' => true,
            'maxLogFiles' => 5,
            'maxFileSize' => 10240,
            'exportInterval' => 100,
        ]);


        if (!YII_DEBUG)
            return;

        Yii::$app->getLog()->targets['bots-debug'] = new FileTarget([
            'levels' => ['error','info','trace'],
            'logVars' => [],
            'logFile' =>   "@runtime/logs/bots/{$messenger}.debug.log",
            'categories' =>   ['bots'],
            'enableRotation' => true,
            'maxLogFiles' => 1,
            'maxFileSize' => 10240,
            'exportInterval' => 1,
        ]);

    }

}