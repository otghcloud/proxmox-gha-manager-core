import { AnsiUp } from 'ansi_up';

function updateProgress(progress) {
    const container = document.querySelector('[data-build-progress]');

    if (!container || !progress?.available) {
        return;
    }

    const current = container.querySelector('[data-build-progress-current]');
    const completed = container.querySelector('[data-build-progress-completed]');
    const total = container.querySelector('[data-build-progress-total]');
    const bar = container.querySelector('[data-build-progress-bar]');

    if (current) {
        current.textContent = progress.status_label || progress.current_stage?.name || 'Waiting for first stage';
    }

    if (completed) {
        completed.textContent = progress.completed_count;
    }

    if (total) {
        total.textContent = progress.stage_count;
    }

    if (bar) {
        bar.style.width = `${progress.percent}%`;
    }

    progress.stages?.forEach((stage) => {
        const node = container.querySelector(`[data-build-stage-id="${CSS.escape(stage.id)}"]`);

        if (!node) {
            return;
        }

        node.classList.remove('build-stage-pending', 'build-stage-current', 'build-stage-complete');
        node.classList.add(`build-stage-${stage.state}`);
    });

    progress.groups?.forEach((group) => {
        const node = container.querySelector(`[data-build-stage-group="${CSS.escape(group.id)}"]`);

        if (!node) {
            return;
        }

        node.classList.remove('build-stage-group-pending', 'build-stage-group-current', 'build-stage-group-complete');
        node.classList.add(`build-stage-group-${group.state}`);

        const count = node.querySelector('.build-stage-group-count');

        if (count) {
            count.textContent = `${group.completed_count} / ${group.stage_count}`;
        }

        // Only auto-collapse groups the user has not touched, so a manual expand is not undone.
        const toggle = node.querySelector('[data-build-stage-toggle]');
        const stages = node.querySelector('.build-stage-group-stages');

        if (!toggle || !stages || toggle.dataset.userToggled === 'true') {
            return;
        }

        const expanded = group.state !== 'complete';
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        stages.hidden = !expanded;
    });
}

function initStageGroupToggles() {
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-build-stage-toggle]');

        if (!toggle) {
            return;
        }

        const stages = toggle.parentElement?.querySelector('.build-stage-group-stages');

        if (!stages) {
            return;
        }

        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        toggle.dataset.userToggled = 'true';
        stages.hidden = expanded;
    });
}

function initStaticLogViewers() {
    const viewers = document.querySelectorAll('#job-log, .log-viewer:not(#build-log)');
    if (!viewers.length) {
        return;
    }

    const ansi = new AnsiUp();
    viewers.forEach((viewer) => {
        const initialContent = viewer.textContent;
        if (initialContent) {
            viewer.innerHTML = ansi.ansi_to_html(initialContent);
        }
        viewer.scrollTop = viewer.scrollHeight;
    });
}

export default function initBuildLog() {
    initStaticLogViewers();
    initStageGroupToggles();

    const viewer = document.getElementById('build-log');

    if (!viewer) {
        return;
    }

    const ansi = new AnsiUp();
    let offset = viewer.textContent.length;

    const render = (content) => {
        if (!content) {
            return;
        }

        viewer.insertAdjacentHTML('beforeend', ansi.ansi_to_html(content));
    };

    const initialContent = viewer.textContent;
    viewer.textContent = '';
    render(initialContent);

    if (viewer.dataset.finished === 'true') {
        viewer.scrollTop = viewer.scrollHeight;
        return;
    }

    const atBottom = () => viewer.scrollHeight - viewer.scrollTop - viewer.clientHeight < 40;

    const poll = async () => {
        const response = await fetch(`${viewer.dataset.logUrl}?offset=${offset}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            setTimeout(poll, 3000);
            return;
        }

        const payload = await response.json();
        const stick = atBottom();

        updateProgress(payload.progress);

        if (payload.content) {
            render(payload.content);
            offset = payload.offset;

            if (stick) {
                viewer.scrollTop = viewer.scrollHeight;
            }
        }

        if (payload.finished) {
            window.location.reload();
            return;
        }

        setTimeout(poll, 3000);
    };

    viewer.scrollTop = viewer.scrollHeight;
    setTimeout(poll, 3000);
}
