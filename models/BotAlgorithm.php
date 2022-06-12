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

abstract class BotAlgorithm extends ActiveRecord
{
    //abstract public static function tableName();

    public function rules()
    {
        return [[['bot_id', 'name', 'algorithm_id', 'context_id'], 'required']];
    }

    public function attributeLabels()
    {
        return [
            'name' => 'Название алгоритма',
            'algorithm_id' => 'ID алгоритма',
            'context_id' => 'ID контекста'
        ];
    }
}
