<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 20.02.2017
 * Time: 00:39
 */
namespace wm\bots\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Class TelegramSession
 * @package app\models
 *
 * @property $algorithm_id
 * @property $context_id
 * @property $algorithm_uid
 * @property $node_idx
 * @property $algorithm_done
 */

abstract class Session extends ActiveRecord
{
    public $link_type = 1;

    //abstract public static function tableName();

    public function getAnswers()
    {
        return json_decode($this->answers, true);
    }

    public function getInfoData()
    {
        return json_decode($this->info_data, true);
    }

    public function getAnswer($questionId) {
        $answers = $this->getAnswers();
        return isset($answers[$questionId])?$answers[$questionId]:null;
    }

    public function setAnswer($questionId, $answerId)
    {
        $answers = $this->getAnswers();
        $answers[$questionId] = $answerId;
        $this->answers = json_encode($answers);
        $this->answers_cnt = count($answers);
        $this->algorithm_done = false;
        $this->save();
    }

    public function setInfoData($infoId, $data)
    {
        $infoData = $this->getInfoData();
        $infoData[$infoId] = $data;
        $this->info_data = json_encode($infoData);
        $this->info_data_cnt = count($infoData);
        $this->algorithm_done = false;
        $this->save();
    }

    /**
     * @param $pageId
     * @return Session|null
     */

    public static function start($chatId, $algorithmId, $contextId)
    {
        $session = new static();
        $session->algorithm_id = $algorithmId;
        $session->context_id = $contextId;
        $session->chat_id = $chatId;
        $session->answers = '[]';
        $session->info_data = '[]';
        $session->done_actions = '[]';
        $session->algorithm_done = false;
        $session->node_idx = 0;
        $session->save();

        $session = static::find()
            ->where(['chat_id' => $chatId])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $session;
    }

    public function setAlgorithmUID($uid)
    {
        $uid = (int) $uid;

        if ($uid != $this->algorithm_uid) {
            $this->algorithm_uid = $uid;
            $this->save();
        }
    }

    public function setDone()
    {
        if (!$this->algorithm_done) {
            $this->algorithm_done = true;
            $this->save();
        }
    }

    public function setNodeIdx($idx)
    {
        if ($this->node_idx != $idx) {
            $this->node_idx = $idx;
            $this->save();
        }
    }

    public function getDoneActions() {
        return json_decode($this->done_actions, true);
    }

    public function setDoneActions($actions) {
        ksort($actions);
        $actions = json_encode($actions);

        if (strcmp($this->done_actions, $actions)!==0) {
            $this->done_actions = $actions;
            $this->save();
        }
    }

}
