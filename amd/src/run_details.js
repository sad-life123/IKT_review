// This file is part of Moodle - http://moodle.org/

import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

export const init = () => {
    document.addEventListener('click', async(event) => {
        const trigger = event.target.closest('[data-ikt-review-run-details]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        try {
            const response = await fetch(trigger.href, {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
            if (!response.ok) {
                window.alert(trigger.dataset.errorMessage);
                return;
            }

            const data = await response.json();
            const modal = await ModalFactory.create({
                title: data.title,
                body: data.html,
                large: true,
            });
            modal.getRoot().on(ModalEvents.hidden, () => modal.destroy());
            modal.show();
        } catch (error) {
            window.alert(trigger.dataset.errorMessage);
        }
    });
};
