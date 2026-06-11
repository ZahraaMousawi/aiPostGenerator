document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.composer-panel');
    const textarea = document.querySelector('#topic');
    const counter = document.querySelector('#topic-count');
    const statusLabel = document.querySelector('#pipeline-status');

    const updateCounter = () => {
        if (! textarea || ! counter) {
            return;
        }

        counter.textContent = textarea.value.length.toString();
    };

    updateCounter();
    textarea?.addEventListener('input', updateCounter);

    form?.addEventListener('submit', () => {
        document.body.classList.add('is-generating');
        statusLabel && (statusLabel.textContent = 'جاري التوليد');

        document.querySelectorAll('[data-progress-step], [data-signal-step]').forEach((item) => {
            item.classList.add('is-waiting');
        });

        const button = form.querySelector('.primary-action');
        if (button) {
            button.disabled = true;
            button.querySelector('span:first-child').textContent = 'جاري التوليد';
        }
    });

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = document.getElementById(button.dataset.copyTarget);
            const value = target?.value ?? target?.innerText ?? '';

            if (! value.trim()) {
                return;
            }

            try {
                await navigator.clipboard.writeText(value.trim());
                const original = button.textContent;
                button.textContent = 'تم النسخ';
                window.setTimeout(() => {
                    button.textContent = original;
                }, 1400);
            } catch {
                target?.select?.();
            }
        });
    });

    document.querySelectorAll('[data-resubmit]').forEach((button) => {
        button.addEventListener('click', () => {
            form?.requestSubmit();
        });
    });

    document.querySelectorAll('[data-preview-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const platform = tab.dataset.previewTab;

            document.querySelectorAll('[data-preview-tab]').forEach((item) => {
                const isActive = item.dataset.previewTab === platform;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            document.querySelectorAll('[data-preview-panel]').forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.previewPanel === platform);
            });
        });
    });
});
