<?php

namespace wm\bots\controllers\admin;

use Yii;
use yii\web\Controller;
use wm\bots\models\Telegram;
use yii\web\NotFoundHttpException;

class TelegramController extends Controller
{

    public function init()
    {
        parent::init();

        Yii::$app->getView()->params['breadcrumbs'][] = [
            'label' => 'Telegram-боты',
            'url' => ['/bots/admin/telegram/index']
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction'
            ]
        ];
    }

    public function actionIndex()
    {
        $bots = Telegram\Bot::find()
            ->with(['algorithms'])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        return $this->render('index', ['bots' => $bots]);
    }

    public function actionEdit($id)
    {
        $bot = Telegram\Bot::findOne($id);

        if (!$bot) {
            throw new NotFoundHttpException("The bot was not found.");
        }

        $errors = array();

        if (Yii::$app->request->isPost) {
            $bot->load(Yii::$app->request->post());

            if ($bot->validate()) {
                $bot->save(false);
            } else {
                $errors = $bot->getErrors();
            }
        }

        return $this->render('edit', [
            'model' => $bot,
            'errors' => $errors
        ]);
    }

    public function actionAdd()
    {
        $bot = new Telegram\Bot();
        $errors = array();

        $bot->load(Yii::$app->request->post());

        if (Yii::$app->request->isPost) {
            if ($bot->validate()) {
                $bot->save(false);
                return $this->redirect(['edit', 'id' => $bot->id]);
            } else {
                $errors = $bot->getErrors();
            }
        }

        return $this->render('edit', [
            'model' => $bot,
            'errors' => $errors
        ]);
    }

    public function actionAlgorithmList($id)
    {
        $bot = Telegram\Bot::findOne($id);

        if (!$bot) {
            throw new NotFoundHttpException("The bot was not found.");
        }

        $errors = [];
        $algorithm = new Telegram\BotAlgorithm();

        if (Yii::$app->request->isPost) {
            $algorithm->load(Yii::$app->request->post());
            $algorithm->bot_id = $bot->id;

            if ($algorithm->validate()) {
                $algorithm->save(false);
                return $this->redirect(['algorithm-list', 'id' => $bot->id]);
            } else {
                $errors = $algorithm->getErrors();
            }
        }

        return $this->render('algorithm-list', [
            'bot' => $bot,
            'model' => $algorithm,
            'errors' => $errors
        ]);
    }

    public function actionAlgorithmDelete($id)
    {
        $bot = Telegram\Bot::findOne($id);

        if (!$bot) {
            throw new NotFoundHttpException("The bot was not found.");
        }

        $algorithmId = yii::$app->request->post('algorithm_id');
        $contextId = yii::$app->request->post('context_id');

        $algorithm = Telegram\BotAlgorithm::findOne([
            'bot_id' => $bot->id,
            'algorithm_id' => $algorithmId,
            'context_id' => $contextId
        ]);

        if (!$algorithm) {
            throw new NotFoundHttpException("The algorithm was not found.");
        }

        $algorithm->delete();

        return $this->redirect([
            '/admin/bots/telegram/algorithm-list',
            'id' => $bot->id
        ]);
    }
}
