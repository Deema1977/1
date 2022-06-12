<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 06.01.2018
 * Time: 16:57
 */

namespace wm\bots\models;

use Yii;

class AnswersMult extends Answers
{
    protected $continueText;


    public function __construct($questionId, $maxLen)
    {
        parent::__construct($questionId, $maxLen);

        $this->continueText = Yii::t('bots', 'Continue');
    }


    public function isComplete($answerId)
    {
        return $answerId==-1?true:false;
    }


    public function getAnswerId($text)
    {
        $text = mb_strtolower(trim($text));

        if ($text == mb_strtolower($this->continueText))
            return -1;

        foreach ($this->answers as $k => $answer) {
            if ((int)$text == $k)
                return $answer['id'];

            if ($text == mb_strtolower($answer['text'])) {
                return $answer['id'];
            }
        }
    }



    public function getButtons($selectedId = null)
    {
        $buttons = [];

        if (!is_array($selectedId))
            $selectedId = [];

        foreach ($this->answers as $k => $answer) {
            $selected = false;

            if (in_array($answer['id'], $selectedId))
                $selected = true;

            $buttons[] = ['id' => $answer['id'], 'text' => "{$k}. {$answer['text']}", 'questionId' => $this->questionId, 'selected' => $selected ];
        }

        $buttons[] = ['id' => -1, 'text' => Yii::t('bots', 'Continue'), 'questionId' => $this->questionId, 'continue' => true ];

        return $buttons;
    }

}
