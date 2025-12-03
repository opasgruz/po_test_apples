<?php

namespace backend\models\forms;

use yii\base\Model;

class EatForm extends Model
{
    public $percent;

    public function rules()
    {
        return [
            ['percent', 'required', 'message' => 'Необходимо указать процент'],
            ['percent', 'integer', 'message' => 'Процент должен быть целым числом'],
            ['percent', 'compare', 'compareValue' => 0, 'operator' => '>', 'type' => 'number', 'message' => 'Процент должен быть больше 0'],
            // Верхней границы нет, так как можно откусить больше, чем осталось (съесть целиком)
        ];
    }

    public function formName()
    {
        return ''; // Чтобы в POST запросе параметры передавались без префикса формы (просто percent=50)
    }
}