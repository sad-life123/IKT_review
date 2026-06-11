// This file is part of Moodle - http://moodle.org/

define([
    'core/modal_factory',
    'core/modal_events'
], function(ModalFactory, ModalEvents) {

    return {
        init: function() {
            document.addEventListener('click', async function(event) {
                var trigger = event.target.closest('[data-ikt-review-run-details]');
                if (!trigger) {
                    return;
                }

                event.preventDefault();
                try {
                    var response = await fetch(trigger.href, {
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                    });
                    if (!response.ok) {
                        window.alert(trigger.dataset.errorMessage);
                        return;
                    }

                    var data = await response.json();
                    
                    // Просто создаем и показываем модалку, HTML-шаблон сам всё отрисует внутри
                    var modal = await ModalFactory.create({
                        title: data.title,
                        body: data.html,
                        large: true,
                    });

                    var modalRoot = modal.getRoot();

                    modalRoot.on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });

                    modal.show();

                    // Магия автоматической очистки названий и генерации аббревиатур
                    var labels = document.querySelectorAll('.moodle-vertical-chart-container .vertical-bar-label');
                    
                    labels.forEach(function(el) {
                        var text = el.textContent.trim();
                        
                        // Шаг 1: Если в названии есть слово "Кафедра", убираем его
                        var cleanText = text;
                        if (cleanText.toLowerCase().indexOf('кафедра') !== -1) {
                            cleanText = cleanText.replace(/кафедра/i, '').trim();
                        }
                        
                        // Шаг 2: Убираем дефисы, заменяя их на пробелы
                        cleanText = cleanText.replace(/-/g, ' ').trim();
                        
                        // Шаг 3: Проверяем длину получившейся строки без слова "Кафедра"
                        if (cleanText.length < 12) {
                            // Делаем первую букву строки заглавной, а остальное прибавляем как есть
                            if (cleanText.length > 0) {
                                cleanText = cleanText.charAt(0).toUpperCase() + cleanText.slice(1);
                            }
                            // Если строка короткая (< 12), выводим её целиком с большой буквы
                            el.textContent = cleanText;
                        } else {
                            // Если всё еще длинная (>= 12) — собираем из неё классическую аббревиатуру
                            var words = cleanText.split(/\s+/);
                            var abbr = words.map(function(word) {
                                return word.length > 2 ? word.charAt(0).toUpperCase() : '';
                            }).join('');
                            
                            if (abbr) {
                                el.textContent = abbr;
                            }
                        }
                    });

                } catch (error) {
                    window.alert(trigger.dataset.errorMessage);
                }
            });
        }
    };
});