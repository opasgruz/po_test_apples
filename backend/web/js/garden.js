$(document).ready(function() {

    const STORAGE_KEY = 'apple_garden_positions_v4';
    const API_URL = {
        list: '/apples',       // GET
        generate: '/generate', // POST
        baseUrl: '/apples/'    // POST /apples/{id}/{action}
    };
    const REFRESH_INTERVAL = 60000;
    let refreshTimer;

    // --- ГЛОБАЛЬНЫЙ ПОПАП ---
    // Создаем его один раз и вставляем в body (поверх всего)
    const $popover = $('<div id="global-apple-popover" class="apple-popover"></div>');
    $('body').append($popover);

    let popoverTimeout;

    // --- ИНИЦИАЛИЗАЦИЯ ---
    loadApples();
    startTimer();

    // --- ОБРАБОТЧИКИ СОБЫТИЙ ---

    $('#btn-generate').click(function() {
        if (!confirm('Вы уверены? Старый урожай будет удален.')) return;
        $.post(API_URL.generate, function(data) {
            localStorage.removeItem(STORAGE_KEY);
            renderApples(data);
        }).fail(function() { alert('Ошибка при генерации'); });
    });

    // 1. Наведение на ЯБЛОКО
    $(document).on('mouseenter', '.apple-item', function() {
        clearTimeout(popoverTimeout); // Отменяем таймер скрытия

        const apple = $(this).data('apple'); // Достаем данные яблока из DOM-элемента
        const $el = $(this);

        // Заполняем контент попапа данными этого яблока
        fillPopoverContent(apple);

        // Вычисляем позицию на экране
        const offset = $el.offset(); // Координаты относительно документа
        const width = $el.outerWidth();
        const popoverWidth = $popover.outerWidth();
        const popoverHeight = $popover.outerHeight();

        // Позиционируем попап над яблоком по центру
        $popover.css({
            top: offset.top - popoverHeight - 12, // 12px отступ вверх (учитывая стрелочку)
            left: offset.left + (width / 2) - (popoverWidth / 2),
            display: 'block'
        });
    });

    // 2. Уход с ЯБЛОКА
    $(document).on('mouseleave', '.apple-item', function() {
        // Даем 200мс задержки, чтобы пользователь успел перевести курсор на попап
        popoverTimeout = setTimeout(function() {
            $popover.hide();
        }, 200);
    });

    // 3. Наведение на ПОПАП (курсор на кнопках)
    $popover.on('mouseenter', function() {
        clearTimeout(popoverTimeout); // Не скрывать, пока мы внутри попапа
    });

    // 4. Уход с ПОПАПА
    $popover.on('mouseleave', function() {
        $popover.hide();
    });

    // 5. Клик по кнопкам действий (теперь они внутри глобального попапа)
    $(document).on('click', '.btn-action', function(e) {
        // e.stopPropagation() не нужен, так как попап в body
        const id = $(this).data('id');
        const method = $(this).data('method');

        let data = {};

        if (method === 'eat') {
            const percent = prompt("Сколько процентов откусить?", "25");
            if (percent === null || percent === "") return;
            data.percent = percent;
        } else if (method === 'status') {
            data.status = 1;
        }

        // Скрываем попап, чтобы не мешал анимации
        $popover.hide();

        $.ajax({
            url: API_URL.baseUrl + id + '/' + method,
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function() { loadApples(); },
            error: function(xhr) {
                let msg = 'Ошибка';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                alert(msg);
            }
        });
    });

    // --- ФУНКЦИИ ЛОГИКИ ---

    function loadApples() {
        $.get(API_URL.list, function(data) { renderApples(data); });
    }

    function startTimer() {
        clearInterval(refreshTimer);
        refreshTimer = setInterval(loadApples, REFRESH_INTERVAL);
    }

    function renderApples(apples) {
        const $container = $('#apples-layer');
        const currentIds = new Set(apples.map(a => a.id));

        // Получаем позиции из Storage
        let allPositions = getStoredData();
        let hasChanges = false;

        // Удаление старых
        $container.find('.apple-item').each(function() {
            const id = parseInt($(this).attr('data-id'));
            if (!currentIds.has(id)) {
                if (allPositions[id]) {
                    delete allPositions[id];
                    hasChanges = true;
                }
                $(this).fadeOut(500, function() { $(this).remove(); });
            }
        });

        apples.forEach(apple => {
            let $el = $container.find('.apple-item[data-id="' + apple.id + '"]');

            if ($el.length === 0) {
                $el = $('<div>')
                    .addClass('apple-item')
                    .attr('data-id', apple.id);
                // ВАЖНО: Больше не добавляем .apple-popover внутрь
                $container.append($el);
            }

            // СОХРАНЯЕМ ДАННЫЕ В ЭЛЕМЕНТЕ для использования при hover
            $el.data('apple', apple);

            // Цвет
            $el.css('background-color', apple.color);

            // Червячок
            $el.find('.worm-icon').remove();
            if (apple.status == 2) {
                $el.append('<div class="worm-icon">🐛</div>');
            }

            // Координаты
            let posData = resolveCoordinates(apple, allPositions);
            if (posData.updatedList) {
                allPositions = posData.updatedList;
                hasChanges = true;
            }
            $el.css({ top: posData.coords.top + '%', left: posData.coords.left + '%' });
        });

        if (hasChanges) saveStoredData(allPositions);
    }

    /**
     * Заполнение HTML глобального попапа
     */
    function fillPopoverContent(apple) {
        const created = formatDate(apple.created_at);
        const fall = apple.fall_at ? formatDate(apple.fall_at) : '-';

        let html = `
            <div class="popover-header">Яблоко #${apple.id}</div>
            <div class="popover-row"><b>Статус:</b> ${apple.statusLabel}</div>
            <div class="popover-row"><b>Целостность:</b> ${apple.integrity}%</div>
            <div class="popover-row"><b>Появилось:</b> ${created}</div>
            <div class="popover-row"><b>Упало:</b> ${fall}</div>
        `;

        html += '<div class="popover-actions">';
        if (apple.actions && apple.actions.length > 0) {
            apple.actions.forEach(action => {
                html += `<button class="btn btn-sm btn-${action.color} btn-action" 
                            data-id="${apple.id}" 
                            data-method="${action.method}">
                            ${action.title}
                         </button> `;
            });
        } else {
            html += '<small class="text-muted">Действий нет</small>';
        }
        html += '</div>';

        $popover.html(html);
    }

    // --- ЛОГИКА КООРДИНАТ ---
    function resolveCoordinates(apple, allPositions) {
        const id = apple.id;
        const currentStatus = parseInt(apple.status);
        let stored = allPositions[id];
        let updatedList = null;

        // Сценарий 1: Новое яблоко
        if (!stored) {
            let newPos = generateRandomCoords(currentStatus);
            stored = {
                top: newPos.top,
                left: newPos.left,
                lastStatus: currentStatus
            };
            allPositions[id] = stored;
            updatedList = allPositions;
            return { coords: stored, updatedList: updatedList };
        }

        // Сценарий 2: Яблоко упало (Было 0, стало > 0)
        // Генерируем новые координаты для земли
        if (stored.lastStatus === 0 && currentStatus > 0) {
            let groundPos = generateRandomCoords(currentStatus);

            stored.top = groundPos.top;
            stored.left = groundPos.left;
            stored.lastStatus = currentStatus;

            allPositions[id] = stored;
            updatedList = allPositions;
            return { coords: stored, updatedList: updatedList };
        }

        // Сценарий 3: Просто смена статуса (1 -> 2), координаты не трогаем
        if (stored.lastStatus !== currentStatus) {
            stored.lastStatus = currentStatus;
            allPositions[id] = stored;
            updatedList = allPositions;
        }

        return { coords: stored, updatedList: updatedList };
    }

    function generateRandomCoords(status) {
        let top, left;
        if (status == 0) {
            // НА ДЕРЕВЕ
            top = 5 + Math.random() * 40;
            left = 20 + Math.random() * 60;
        } else {
            // НА ЗЕМЛЕ
            top = 90 + Math.random() * 5;
            left = 5 + Math.random() * 90;
        }
        return { top: top, left: left };
    }

    function formatDate(timestamp) {
        if (!timestamp) return '-';
        const date = new Date(timestamp * 1000);
        return date.toLocaleString('ru-RU', {
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            day: 'numeric', month: 'short'
        });
    }

    // --- Helpers для LocalStorage ---

    function getStoredData() {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return {};
        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function saveStoredData(data) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    }
});