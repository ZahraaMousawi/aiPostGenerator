document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.composer-panel');
    const textarea = document.querySelector('#topic');
    const counter = document.querySelector('#topic-count');
    const generationStatus = document.querySelector('#generation-status');
    const generationText = generationStatus?.querySelector('.generation-text');
    const pipelineStatus = document.querySelector('#pipeline-status');
    const signalPanel = document.querySelector('.signal-panel');
    const themeToggle = document.querySelector('#theme-toggle');
    const themeToggleText = themeToggle?.querySelector('.theme-toggle-text');
    const languageToggle = document.querySelector('#language-toggle');
    const languageToggleText = languageToggle?.querySelector('.language-toggle-text');
    const languageToggleMark = languageToggle?.querySelector('.language-toggle-mark');
    const translations = {
        ar: {
            activity_file: 'نسخة ملف نشاطات المركز:',
            app_subtitle: '(Smart Social Content Platform)',
            app_title: 'منصة المحتوى الذكي',
            baghdad: 'بغداد',
            comment: 'تعليق',
            content_status: 'حالة المحتوى',
            copied: 'تم النسخ',
            copy: 'نسخ',
            copy_activity: 'نسخ نسخة ملف النشاطات',
            copy_hashtags: 'نسخ الهاشتاقات',
            copy_post: 'نسخ المنشور',
            copy_post_text: 'نسخ نص المنشور',
            copy_suggestion: 'نسخ المقترح',
            copy_title: 'نسخ العنوان',
            corrected_post: 'نص المنشور المصحح:',
            download_image: 'تحميل الصورة',
            download_video: 'تحميل الفيديو',
            enter_text: 'أدخل النص',
            generate_post: 'توليد المنشور',
            generating_post: 'جاري إنشاء المنشور',
            hashtags: 'الهاشتاقات:',
            input_hint: 'نص خام، خبر، فعالية، أو إعلان قصير',
            input_panel: 'لوحة الإدخال',
            language_next: 'English',
            like: 'أعجبني',
            now: 'الآن',
            org_name: 'المركز الوطني للذكاء الاصطناعي',
            outputs: 'المخرجات',
            pipeline_error: 'تعذر الإنشاء',
            pipeline_idle: 'بانتظار النص',
            pipeline_loading: 'جاري التوليد',
            pipeline_ready: 'جاهز',
            preview: 'المعاينة',
            publish_shape: 'شكل النشر',
            ready_review: 'جاهزة للمراجعة',
            regenerate: 'إعادة التوليد',
            share: 'مشاركة',
            status_error: 'لم يتم إنشاء المنشور',
            status_success: 'تم إنشاء المنشور بنجاح',
            step_copy: 'نص مصحح',
            step_hashtags: 'هاشتاقات للنشر',
            step_image: 'صورة للموضوع',
            step_title: 'عنوان مناسب',
            step_video: 'فيديو Reels',
            suggested_title: 'العنوان المقترح:',
            text_label_short: 'النص',
            theme_dark: 'الثيم الليلي',
            theme_light: 'الثيم النهاري',
            topic_placeholder: 'أدخل خبراً أو فعالية ليتم تحويلها إلى منشور احترافي...',
            visual_suggestion: 'مقترح الصورة أو التصميم:',
        },
        en: {
            activity_file: 'Activity file copy:',
            app_subtitle: '(منصة المحتوى الذكي)',
            app_title: 'Smart Social Content Platform',
            baghdad: 'Baghdad',
            comment: 'Comment',
            content_status: 'Content Status',
            copied: 'Copied',
            copy: 'Copy',
            copy_activity: 'Copy activity file',
            copy_hashtags: 'Copy hashtags',
            copy_post: 'Copy post',
            copy_post_text: 'Copy post text',
            copy_suggestion: 'Copy suggestion',
            copy_title: 'Copy title',
            corrected_post: 'Corrected post text:',
            download_image: 'Download image',
            download_video: 'Download video',
            enter_text: 'Enter text',
            generate_post: 'Generate post',
            generating_post: 'Generating post',
            hashtags: 'Hashtags:',
            input_hint: 'Raw text, news, activity, or short announcement',
            input_panel: 'Input Panel',
            language_next: 'العربية',
            like: 'Like',
            now: 'Now',
            org_name: 'National Center for Artificial Intelligence',
            outputs: 'Outputs',
            pipeline_error: 'Generation failed',
            pipeline_idle: 'Waiting for text',
            pipeline_loading: 'Generating',
            pipeline_ready: 'Ready',
            preview: 'Preview',
            publish_shape: 'Post preview',
            ready_review: 'Ready for review',
            regenerate: 'Regenerate',
            share: 'Share',
            status_error: 'Post was not generated',
            status_success: 'Post generated successfully',
            step_copy: 'Corrected text',
            step_hashtags: 'Publishing hashtags',
            step_image: 'Topic image',
            step_title: 'Suitable title',
            step_video: 'Reels video',
            suggested_title: 'Suggested title:',
            text_label_short: 'Text',
            theme_dark: 'Dark theme',
            theme_light: 'Light theme',
            topic_placeholder: 'Enter news or an activity to turn it into a professional post...',
            visual_suggestion: 'Image or design suggestion:',
        },
    };

    const currentLanguage = () => document.documentElement.lang === 'en' ? 'en' : 'ar';
    const t = (key) => translations[currentLanguage()][key] ?? translations.ar[key] ?? key;
    const getStoredTheme = () => {
        try {
            return localStorage.getItem('preferred-theme');
        } catch {
            return null;
        }
    };
    const storeTheme = (theme) => {
        try {
            localStorage.setItem('preferred-theme', theme);
        } catch {
        }
    };
    const getStoredLanguage = () => {
        try {
            return localStorage.getItem('preferred-language');
        } catch {
            return null;
        }
    };
    const storeLanguage = (language) => {
        try {
            localStorage.setItem('preferred-language', language);
        } catch {
        }
    };

    const updateThemeLabel = () => {
        if (! themeToggle || ! themeToggleText) {
            return;
        }

        const isDark = document.documentElement.classList.contains('theme-dark');
        themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        themeToggleText.textContent = isDark ? t('theme_light') : t('theme_dark');
    };

    const updateStatusLabels = () => {
        if (pipelineStatus) {
            const state = pipelineStatus.dataset.statusState || 'idle';
            pipelineStatus.textContent = t(`pipeline_${state}`);
        }

        if (generationStatus && generationText) {
            const state = generationStatus.dataset.statusState || 'idle';
            if (state === 'success') {
                generationText.textContent = t('status_success');
            } else if (state === 'error') {
                generationText.textContent = t('status_error');
            } else if (state === 'loading') {
                generationText.textContent = t('generating_post');
            }
        }
    };

    const applyLanguage = (language) => {
        const isEnglish = language === 'en';
        document.documentElement.lang = isEnglish ? 'en' : 'ar';
        document.documentElement.dir = isEnglish ? 'ltr' : 'rtl';
        document.body.dir = isEnglish ? 'ltr' : 'rtl';

        document.querySelectorAll('[data-i18n]').forEach((item) => {
            item.textContent = t(item.dataset.i18n);
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach((item) => {
            item.setAttribute('placeholder', t(item.dataset.i18nPlaceholder));
        });
        document.querySelectorAll('[data-i18n-title]').forEach((item) => {
            item.setAttribute('title', t(item.dataset.i18nTitle));
        });

        if (languageToggle && languageToggleText && languageToggleMark) {
            languageToggle.setAttribute('aria-pressed', isEnglish ? 'true' : 'false');
            languageToggleText.textContent = t('language_next');
            languageToggleMark.textContent = isEnglish ? 'AR' : 'EN';
        }

        document.title = t('org_name');
        updateThemeLabel();
        updateStatusLabels();
    };

    const applyTheme = (theme) => {
        const isDark = theme === 'dark';
        document.documentElement.classList.toggle('theme-dark', isDark);
        document.body.classList.toggle('theme-dark', isDark);
        updateThemeLabel();
    };

    applyLanguage(getStoredLanguage() === 'en' ? 'en' : 'ar');
    applyTheme(getStoredTheme() === 'dark' ? 'dark' : 'light');

    const updateCounter = () => {
        if (! textarea || ! counter) {
            return;
        }

        counter.textContent = textarea.value.length.toString();
    };

    updateCounter();
    textarea?.addEventListener('input', updateCounter);

    if (generationStatus?.classList.contains('is-success') || generationStatus?.classList.contains('is-error')) {
        window.setTimeout(() => {
            generationStatus.classList.remove('is-visible');
        }, 2200);
    }

    form?.addEventListener('submit', () => {
        if (generationStatus && generationText) {
            generationStatus.classList.remove('is-success', 'is-error');
            generationStatus.classList.add('is-visible', 'is-loading');
            generationStatus.dataset.statusState = 'loading';
            generationText.textContent = t('generating_post');
        }

        if (pipelineStatus) {
            pipelineStatus.dataset.statusState = 'loading';
            pipelineStatus.textContent = t('pipeline_loading');
        }

        signalPanel?.classList.remove('is-success', 'is-error');
        signalPanel?.classList.add('is-loading');

        document.querySelectorAll('[data-signal-step]').forEach((item) => {
            item.classList.remove('is-complete');
            item.classList.add('is-waiting');
        });

        const button = form.querySelector('.primary-action');
        if (button) {
            button.disabled = true;
            button.querySelector('span:first-child').textContent = t('generating_post');
        }
    });

    themeToggle?.addEventListener('click', () => {
        const nextTheme = document.documentElement.classList.contains('theme-dark') ? 'light' : 'dark';
        storeTheme(nextTheme);
        applyTheme(nextTheme);
    });

    languageToggle?.addEventListener('click', () => {
        const nextLanguage = currentLanguage() === 'en' ? 'ar' : 'en';
        storeLanguage(nextLanguage);
        applyLanguage(nextLanguage);
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
                button.textContent = t('copied');
                window.setTimeout(() => {
                    button.textContent = button.dataset.i18n ? t(button.dataset.i18n) : original;
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
