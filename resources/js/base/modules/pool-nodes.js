/**
 * Keeps the per-node limits table honest: live pool totals, inputs disabled on unticked nodes,
 * and a warning on nodes where the selected template has no image built yet.
 */
export default function initPoolNodes() {
    const root = document.querySelector('[data-pool-nodes]');

    if (!root) {
        return;
    }

    let builtTargets = {};

    try {
        builtTargets = JSON.parse(root.dataset.builtTargets || '{}');
    } catch {
        builtTargets = {};
    }

    const rows = Array.from(root.querySelectorAll('tr[data-target-id]'));
    const templateSelect = document.getElementById('runner_template_id');
    const totalMinIdle = root.querySelector('[data-total-min-idle]');
    const totalMaxConcurrent = root.querySelector('[data-total-max-concurrent]');

    const fieldsOf = (row) => ({
        toggle: row.querySelector('input[type="checkbox"]'),
        preference: row.querySelector('input[name$="[preference]"]'),
        minIdle: row.querySelector('input[name$="[min_idle_runners]"]'),
        maxConcurrent: row.querySelector('input[name$="[max_concurrent]"]'),
        warning: row.querySelector('[data-unbuilt-warning]'),
    });

    const refresh = () => {
        const built = builtTargets[templateSelect?.value] || [];
        let minIdleSum = 0;
        let maxConcurrentSum = 0;

        rows.forEach((row) => {
            const { toggle, preference, minIdle, maxConcurrent, warning } = fieldsOf(row);
            const enabled = toggle.checked;

            preference.disabled = !enabled;
            minIdle.disabled = !enabled;
            maxConcurrent.disabled = !enabled;
            row.classList.toggle('opacity-50', !enabled);

            if (enabled) {
                minIdleSum += Number(minIdle.value) || 0;
                maxConcurrentSum += Number(maxConcurrent.value) || 0;
            }

            const unbuilt = enabled && !built.includes(Number(row.dataset.targetId));
            warning?.classList.toggle('d-none', !unbuilt);
        });

        totalMinIdle.textContent = String(minIdleSum);
        totalMaxConcurrent.textContent = String(maxConcurrentSum);
    };

    root.addEventListener('change', refresh);
    root.addEventListener('input', refresh);
    templateSelect?.addEventListener('change', refresh);

    refresh();
}
