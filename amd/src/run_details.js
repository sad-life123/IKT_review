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
                } catch (error) {
                    window.alert(trigger.dataset.errorMessage);
                }
            });
        }
    };
});