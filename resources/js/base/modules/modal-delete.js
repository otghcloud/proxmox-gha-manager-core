import $ from 'jquery';
import { Modal } from '@tabler/core/js/tabler';

/**
 * Turns any `[data-action="delete-modal"]` link into a confirmation dialog that
 * posts a DELETE request, so destructive actions never happen on a plain GET.
 */
export default function initDeleteModal() {
    const modalNode = document.getElementById('delete-modal');

    if (!modalNode) {
        return;
    }

    const modal = new Modal(modalNode);
    const form = modalNode.querySelector('form');
    const message = modalNode.querySelector('[data-delete-message]');

    $(document).on('click', '[data-action="delete-modal"]', function (event) {
        event.preventDefault();

        const href = this.dataset.deleteUrl || this.getAttribute('href');

        if (!href || href === '#') {
            return;
        }

        form.setAttribute('action', href);

        if (message) {
            message.textContent = this.dataset.deleteMessage
                || 'This cannot be undone. Are you sure you want to continue?';
        }

        modal.show();
    });
}
