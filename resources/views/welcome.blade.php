<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>المركز الوطني للذكاء الاصطناعي</title>
    <script>
        try {
            if (localStorage.getItem('preferred-theme') === 'dark') {
                document.documentElement.classList.add('theme-dark');
            }
            if (localStorage.getItem('preferred-language') === 'en') {
                document.documentElement.lang = 'en';
                document.documentElement.dir = 'ltr';
            }
        } catch {
        }
    </script>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <script src="{{ asset('app.js') }}" defer></script>
</head>
<body class="command-shell">
    @php
        $isReady = $result && $result['source'] === 'gemini';
        $hashtagsText = $isReady ? implode(' ', $result['hashtags']) : '';
        $facebookCopy = $isReady
            ? '('.$result['suggested_title'].')'."\n\n".$result['corrected_news']."\n\n".$hashtagsText
            : '';
        $instagramCopy = $facebookCopy;
        $hasGenerationError = $errors->any() || ($result && ! $isReady);
        $generationState = $isReady ? 'success' : ($hasGenerationError ? 'error' : 'idle');
        $generationMessage = $isReady
            ? 'تم إنشاء المنشور بنجاح'
            : ($hasGenerationError ? 'لم يتم إنشاء المنشور' : '');
    @endphp
    <main class="command-center">
        <header class="command-hero">
            <div class="hero-logo-block">
                <img class="center-logo" src="{{ asset('branding/national-ai-center-logo.jpg') }}" alt="شعار المركز الوطني للذكاء الاصطناعي">
                <small data-i18n="org_name">المركز الوطني للذكاء الاصطناعي</small>
            </div>
            <div class="command-title">
                <h1 data-i18n="app_title">منصة المحتوى الذكي</h1>
                <span data-i18n="app_subtitle">(Smart Social Content Platform)</span>
            </div>

            <div class="live-block">
                <span class="live-dot"></span>
                <small><span data-i18n="baghdad">بغداد</span> - {{ now()->format('H:i') }}</small>
            </div>

            <div class="header-actions">
                <button type="button" class="theme-toggle" id="theme-toggle" aria-pressed="false">
                    <span class="theme-toggle-mark" aria-hidden="true"></span>
                    <span class="theme-toggle-text">الثيم الليلي</span>
                </button>
                <button type="button" class="language-toggle" id="language-toggle" aria-pressed="false">
                    <span class="language-toggle-mark" aria-hidden="true">EN</span>
                    <span class="language-toggle-text">English</span>
                </button>
            </div>
        </header>

        <section class="command-layout">
            <form class="command-card composer-panel" method="POST" action="{{ route('posts.generate') }}">
                @csrf
                <div class="card-heading">
                    <span data-i18n="input_panel">لوحة الإدخال</span>
                    <strong data-i18n="text_label_short">النص</strong>
                </div>

                <label for="topic" data-i18n="enter_text">أدخل النص</label>
                <textarea id="topic" name="topic" rows="9" maxlength="4000" placeholder="أدخل خبراً أو فعالية ليتم تحويلها إلى منشور احترافي..." data-i18n-placeholder="topic_placeholder" required>{{ old('topic', $topic) }}</textarea>
                <div class="composer-meta">
                    <span class="input-hint" data-i18n="input_hint">نص خام، خبر، فعالية، أو إعلان قصير</span>
                    <span><strong id="topic-count">0</strong> / 4000</span>
                </div>

                @error('topic')
                    <p class="error-message">{{ $message }}</p>
                @enderror

                <button type="submit" class="primary-action">
                    <span data-i18n="generate_post">توليد المنشور</span>
                    <span aria-hidden="true">↵</span>
                </button>

                <div id="generation-status" class="generation-status {{ $generationState !== 'idle' ? 'is-visible is-'.$generationState : '' }}" data-status-state="{{ $generationState }}" role="status" aria-live="polite">
                    <span class="generation-icon" aria-hidden="true"></span>
                    <span class="generation-text">{{ $generationMessage }}</span>
                </div>
            </form>

            <aside class="command-card signal-panel {{ $isReady ? 'is-success' : ($hasGenerationError ? 'is-error' : '') }}">
                <div class="card-heading">
                    <span data-i18n="content_status">حالة المحتوى</span>
                    <strong id="pipeline-status" data-status-state="{{ $isReady ? 'ready' : ($hasGenerationError ? 'error' : 'idle') }}">{{ $isReady ? 'جاهز' : ($hasGenerationError ? 'تعذر الإنشاء' : 'بانتظار النص') }}</strong>
                </div>

                <div class="signal-list">
                    <div class="{{ $isReady ? 'is-complete' : '' }}" data-signal-step="title"><span></span><b data-i18n="step_title">عنوان مناسب</b></div>
                    <div class="{{ $isReady ? 'is-complete' : '' }}" data-signal-step="copy"><span></span><b data-i18n="step_copy">نص مصحح</b></div>
                    <div class="{{ $isReady && $result['image_url'] ? 'is-complete' : '' }}" data-signal-step="image"><span></span><b data-i18n="step_image">صورة للموضوع</b></div>
                    <div class="{{ $isReady ? 'is-complete' : '' }}" data-signal-step="hashtags"><span></span><b data-i18n="step_hashtags">هاشتاقات للنشر</b></div>
                </div>
            </aside>
        </section>

        @if ($result)
            <section class="post-section" aria-live="polite">
                @if (! $isReady)
                    <div class="notice">
                        {{ $result['image_error'] }}
                    </div>
                @else
                    <article class="output-summary" aria-label="مخرجات المنشور">
                        <div class="output-summary-heading">
                            <span data-i18n="outputs">المخرجات</span>
                            <strong data-i18n="ready_review">جاهزة للمراجعة</strong>
                        </div>

                        <div class="output-toolbar">
                            <button type="button" class="compact-action" data-copy-target="facebook-copy" data-i18n="copy_post">نسخ المنشور</button>
                            <button type="button" class="compact-action" data-resubmit data-i18n="regenerate">إعادة التوليد</button>
                            @if ($result['image_url'])
                                <a class="compact-action" href="{{ $result['image_url'] }}" download data-i18n="download_image">تحميل الصورة</a>
                            @endif
                        </div>

                        <div class="output-card-grid">
                            <section class="result-card title-card">
                                <div class="result-card-header">
                                    <strong data-i18n="suggested_title">العنوان المقترح:</strong>
                                    <button type="button" class="icon-action" title="نسخ العنوان" data-i18n-title="copy_title" data-copy-target="suggested-title" data-i18n="copy">نسخ</button>
                                </div>
                                <p id="suggested-title" dir="auto">{{ $result['suggested_title'] }}</p>
                            </section>

                            <section class="result-card wide">
                                <div class="result-card-header">
                                    <strong data-i18n="corrected_post">نص المنشور المصحح:</strong>
                                    <button type="button" class="icon-action" title="نسخ نص المنشور" data-i18n-title="copy_post_text" data-copy-target="corrected-copy" data-i18n="copy">نسخ</button>
                                </div>
                                <p id="corrected-copy" dir="auto">{{ $result['corrected_news'] }}</p>
                            </section>

                            <section class="result-card wide">
                                <div class="result-card-header">
                                    <strong data-i18n="activity_file">نسخة ملف نشاطات المركز:</strong>
                                    <button type="button" class="icon-action" title="نسخ نسخة ملف النشاطات" data-i18n-title="copy_activity" data-copy-target="activity-copy" data-i18n="copy">نسخ</button>
                                </div>
                                <p id="activity-copy" dir="auto">{{ $result['activity_file_copy'] }}</p>
                            </section>

                            <section class="result-card">
                                <div class="result-card-header">
                                    <strong data-i18n="visual_suggestion">مقترح الصورة أو التصميم:</strong>
                                    <button type="button" class="icon-action" title="نسخ المقترح" data-i18n-title="copy_suggestion" data-copy-target="visual-copy" data-i18n="copy">نسخ</button>
                                </div>
                                <p id="visual-copy" dir="auto">{{ $result['visual_suggestion'] }}</p>
                            </section>

                            <section class="result-card">
                                <div class="result-card-header">
                                    <strong data-i18n="hashtags">الهاشتاقات:</strong>
                                    <button type="button" class="icon-action" title="نسخ الهاشتاقات" data-i18n-title="copy_hashtags" data-copy-target="hashtags-copy" data-i18n="copy">نسخ</button>
                                </div>
                                <p id="hashtags-copy" dir="auto">{{ $hashtagsText }}</p>
                            </section>
                        </div>
                    </article>

                    <section class="preview-shell" aria-label="معاينة المنشور">
                        <div class="preview-heading">
                            <div>
                                <span data-i18n="preview">المعاينة</span>
                                <strong data-i18n="publish_shape">شكل النشر</strong>
                            </div>
                            <div class="preview-tabs" role="tablist" aria-label="اختيار المنصة">
                                <button type="button" class="preview-tab is-active" data-preview-tab="facebook" role="tab" aria-selected="true">Facebook</button>
                                <button type="button" class="preview-tab" data-preview-tab="instagram" role="tab" aria-selected="false">Instagram</button>
                            </div>
                        </div>

                        <article class="facebook-post platform-preview is-active" data-preview-panel="facebook">
                            <header class="facebook-post-header">
                                <img class="page-avatar logo-avatar" src="{{ asset('branding/national-ai-center-logo.jpg') }}" alt="">
                                <div>
                                    <strong data-i18n="org_name">المركز الوطني للذكاء الاصطناعي</strong>
                                    <span data-i18n="now">الآن</span>
                                </div>
                            </header>

                            <div class="facebook-post-copy">
                                <p class="post-title-line" dir="auto">({{ $result['suggested_title'] }})</p>
                                <p>{{ $result['corrected_news'] }}</p>
                            </div>

                            <div class="facebook-hashtags facebook-hashtags-body">
                                @foreach ($result['hashtags'] as $hashtag)
                                    <span>{{ $hashtag }}</span>
                                @endforeach
                            </div>

                            @if ($result['image_url'])
                                <img class="facebook-post-image" src="{{ $result['image_url'] }}" alt="صورة مناسبة للمنشور">
                                @if ($result['image_credit'] ?? null)
                                    <p class="image-credit">
                                        @if ($result['image_source_url'] ?? null)
                                            <a href="{{ $result['image_source_url'] }}" target="_blank" rel="noopener noreferrer">{{ $result['image_credit'] }}</a>
                                        @else
                                            {{ $result['image_credit'] }}
                                        @endif
                                    </p>
                                @endif
                            @elseif ($result['image_error'])
                                <div class="notice post-notice">{{ $result['image_error'] }}</div>
                            @endif

                            <footer class="facebook-post-footer">
                                <div class="facebook-actions" aria-hidden="true">
                                    <span data-i18n="like">أعجبني</span>
                                    <span data-i18n="comment">تعليق</span>
                                    <span data-i18n="share">مشاركة</span>
                                </div>
                            </footer>
                        </article>

                        <article class="instagram-post platform-preview" data-preview-panel="instagram">
                            <header class="instagram-header">
                                <img class="page-avatar logo-avatar" src="{{ asset('branding/national-ai-center-logo.jpg') }}" alt="">
                                <strong data-i18n="org_name">المركز الوطني للذكاء الاصطناعي</strong>
                            </header>

                            @if ($result['image_url'])
                                <img class="instagram-image" src="{{ $result['image_url'] }}" alt="صورة مناسبة للمنشور">
                            @endif

                            <div class="instagram-caption">
                                <strong dir="auto">{{ $result['suggested_title'] }}</strong>
                                <p dir="auto">{{ $result['corrected_news'] }}</p>
                                <p class="instagram-tags" dir="auto">{{ $hashtagsText }}</p>
                            </div>
                        </article>

                        <textarea id="facebook-copy" class="copy-buffer" readonly>{{ $facebookCopy }}</textarea>
                        <textarea id="instagram-copy" class="copy-buffer" readonly>{{ $instagramCopy }}</textarea>
                    </section>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
