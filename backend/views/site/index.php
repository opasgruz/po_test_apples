<?php

/** @var yii\web\View $this */

$this->title = 'Яблочный сад';

// Подключаем наш JS скрипт
$this->registerJsFile(
    '@web/js/garden.js?version=1.4',
    ['depends' => [\yii\web\JqueryAsset::class]] // Обязательно после jQuery
);
?>

<div class="site-index">
    <!-- Панель управления -->
    <div class="row mb-4">
        <div class="col-md-12 text-center">
            <h1>🍎 Яблочный сад</h1>
            <button id="btn-generate" class="btn btn-primary btn-lg mt-2">
                <i class="fas fa-sync"></i> Сгенерировать новые яблоки
            </button>
        </div>
    </div>

    <!-- Область сада -->
    <div class="garden-container" id="garden">
        <!-- Дерево -->
        <div class="tree">
            <div class="trunk"></div>
            <div class="branch branch-left"></div>
            <div class="branch branch-right"></div>
            <div class="branch branch-left-top"></div>
            <div class="branch branch-right-top"></div>
        </div>

        <!-- Слой для яблок (сюда JS будет добавлять div.apple-item) -->
        <div id="apples-layer"></div>
    </div>
</div>