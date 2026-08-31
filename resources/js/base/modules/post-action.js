import $ from 'jquery';

/**
 * Submits `[data-action="post"]` links as POST requests.
 *
 * Actions that change state or reach out to third parties should not be reachable by a plain
 * link, so these are turned into a CSRF-protected form submission instead.
 */
export default function initPostActions() {
    $(document).on('click', '[data-action="post"]', function (event) {
        event.preventDefault();

        const url = this.dataset.postUrl || this.getAttribute('href');

        if (!url || url === '#') {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
        form.appendChild(token);

        document.body.appendChild(form);
        form.submit();
    });
}
