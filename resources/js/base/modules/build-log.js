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
