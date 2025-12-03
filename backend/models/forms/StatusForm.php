<?php

namespace backend\models\forms;

use common\models\Apple;
use yii\base\Model;

class StatusForm extends Model
{
    public $status;

    public function rules()
    {
        return [
            ['status', 'required'],
            ['status', 'integer'],
            ['status', 'in', 'range' => array_keys(Apple::STATUSES)],
        ];
    }

    public function formName()
    {
        return ''; // Параметры без префикса
    }
}