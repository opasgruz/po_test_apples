<?php

use common\models\User;
use yii\db\Migration;

class m251203_181656_seed_users_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $time = time();

        // Пользователь 1
        $this->insert('{{%user}}', [
            'username' => 'po1',
            'auth_key' => Yii::$app->security->generateRandomString(),
            'password_hash' => Yii::$app->security->generatePasswordHash('po1'),
            'email' => 'po1@example.com',
            'status' => User::STATUS_ACTIVE,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        // Пользователь 2
        $this->insert('{{%user}}', [
            'username' => 'po2',
            'auth_key' => Yii::$app->security->generateRandomString(),
            'password_hash' => Yii::$app->security->generatePasswordHash('po2'),
            'email' => 'po2@example.com',
            'status' => User::STATUS_ACTIVE,
            'created_at' => $time,
            'updated_at' => $time,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%user}}', ['username' => ['po1', 'po2']]);
    }
}
