<?php
/**
 * Created by PhpStorm.
 * User: Jeka
 * Date: 06.01.2018
 * Time: 16:57
 */

namespace wm\bots\models;

class Answers
{
    public $indexes;

    public $questionId;

    protected $answers = [];

    protected $hasLongText = false;

    protected $maxLen;


    public function __construct($questionId, $maxLen)
    {
        $this->questionId = $questionId;
        $this->maxLen = $maxLen;
    }

    public function hasLongText()
    {
        return $this->hasLongText;
    }

    public function addAnswer($id, $text)
    {
        if (mb_strlen($text) > $this->maxLen) {
            $this->hasLongText = true;
        }

        $idx = count($this->answers) + 1;

        $this->answers[$idx] = [
            'id' => $id,
            'text' => $text
        ];
    }

    public function getAnswerText($answerId)
    {
        foreach ($this->answers as $answer) {
            if ($answer['id'] == $answerId) {
                return $answer['text'];
            }
        }

        return null;
    }


    public function getAnswerId($text)
    {
        $text = trim($text);
        return $this->findAnswer($text);
    }

    public function getAnswersText()
    {
        $text = "";

        foreach ($this->answers as $k => $answer) {
            $text .= "{$k}. {$answer['text']}\n\n";
        }

        return $text;
    }


    public function getButtons($selectedId = null)
    {
        $buttons = [];

        foreach ($this->answers as $k => $answer) {
            $selected = false;

            if ($answer['id']==$selectedId)
                $selected = true;

            $text = $this->cleanAnswer($answer['text']);

            $buttons[] = ['id' => $answer['id'], 'text' => "{$k}. {$text}", 'questionId' => $this->questionId, 'selected' => $selected ];
        }

        return $buttons;
    }


    protected function findAnswer($text)
    {
        $text = mb_strtolower($text);

        foreach ($this->answers as $k => $answer) {
            if ((int) $text == $k) {
                return $answer['id'];
            }

            //            if ($text == "/$k")
            //                return $answer['id'];
            //            if (!$this->useLongText and $text=="вариант {$k}")
            //                return $answer['id'];

            if ($text == mb_strtolower($answer['text'])) {
                return $answer['id'];
            }
        }

        return null;
    }


    protected function cleanAnswer($text) {
        $text = strip_tags($text);
        $text = html_entity_decode($text, null, 'UTF-8');
        $text = preg_replace('~\n+~', ' ', $text);

        return $text;
    }
}
