<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>المركز الوطني للذكاء الاصطناعي</title>
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
    @endphp
    <main class="command-center">
        <header class="command-hero">
            <div class="live-block">
                <span class="live-dot"></span>
                <small>بغداد - {{ now()->format('H:i') }}</small>
            </div>

            <div class="command-title">
                <img class="center-logo" src="{{ asset('branding/national-ai-center-logo.jpg') }}" alt="شعار المركز الوطني للذكاء الاصطناعي">
                <span>منصة المحتوى الذكي</span>
                <h1>المركز الوطني للذكاء الاصطناعي</h1>
            </div>
        </header>

        <section class="metric-grid" aria-label="مؤشرات المنشور">
            <div class="metric-card {{ filled($topic) ? 'is-complete' : '' }}" data-progress-step="input">
                <span>المدخلات</span>
                <strong>نص</strong>
            </div>
            <div class="metric-card {{ $isReady ? 'is-complete' : '' }}" data-progress-step="processing">
                <span>المعالجة</span>
                <strong>صياغة</strong>
            </div>
            <div class="metric-card {{ $isReady && $result['image_url'] ? 'is-complete' : '' }}" data-progress-step="image">
                <span>الصورة</span>
                <strong>مشهد</strong>
            </div>
            <div class="metric-card {{ $isReady ? 'is-complete' : '' }}" data-progress-step="ready">
                <span>الناتج</span>
                <strong>منشور</strong>
            </div>
        </section>

        <section class="command-layout">
            <form class="command-card composer-panel" method="POST" action="{{ route('posts.generate') }}">
                @csrf
                <div class="card-heading">
                    <span>لوحة الإدخال</span>
                    <strong>النص</strong>
                </div>

                <label for="topic">أدخل النص</label>
                <textarea id="topic" name="topic" rows="9" maxlength="4000" placeholder="أدخل خبراً أو فعالية ليتم تحويلها إلى منشور احترافي..." required>{{ old('topic', $topic) }}</textarea>
                <div class="composer-meta">
                    <span class="input-hint">نص خام، خبر، فعالية، أو إعلان قصير</span>
                    <span><strong id="topic-count">0</strong> / 4000</span>
                </div>

                @error('topic')
                    <p class="error-message">{{ $message }}</p>
                @enderror

                <button type="submit" class="primary-action">
                    <span>توليد المنشور</span>
                    <span aria-hidden="true">↵</span>
                </button>
            </form>

            <aside class="command-card signal-panel">
                <div class="card-heading">
                    <span>حالة المحتوى</span>
                    <strong id="pipeline-status">{{ $isReady ? 'جاهز' : 'بانتظار النص' }}</strong>
                </div>

                <div class="signal-list">
                    <div class="{{ $isReady ? 'is-complete' : '' }}" data-signal-step="title"><span></span> عنوان مناسب</div>
                    <div class="{{ $isReady ? 'is-complete' : '' }}" data-signal-step="copy"><span></span> نص مصحح</div>
                    <div class="{{ $isReady && $result['image_url'] ? 'is-complete' : '' }}" data-signal-step="image"><span></span> صورة للموضوع</div>
                    <div class="{{ $isReady ? 'is-complete' : '' }}" data-signal-step="hashtags"><span></span> هاشتاقات للنشر</div>
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
                            <span>المخرجات</span>
                            <strong>جاهزة للمراجعة</strong>
                        </div>

                        <div class="output-toolbar">
                            <button type="button" class="compact-action" data-copy-target="facebook-copy">نسخ المنشور</button>
                            <button type="button" class="compact-action" data-resubmit>إعادة التوليد</button>
                            @if ($result['image_url'])
                                <a class="compact-action" href="{{ $result['image_url'] }}" download>تحميل الصورة</a>
                            @endif
                        </div>

                        <div class="output-card-grid">
                            <section class="result-card">
                                <div class="result-card-header">
                                    <strong>العنوان المقترح:</strong>
                                    <button type="button" class="icon-action" title="نسخ العنوان" data-copy-target="suggested-title">نسخ</button>
                                </div>
                                <p id="suggested-title">{{ $result['suggested_title'] }}</p>
                            </section>

                            <section class="result-card wide">
                                <div class="result-card-header">
                                    <strong>نص المنشور المصحح:</strong>
                                    <button type="button" class="icon-action" title="نسخ نص المنشور" data-copy-target="corrected-copy">نسخ</button>
                                </div>
                                <p id="corrected-copy">{{ $result['corrected_news'] }}</p>
                            </section>

                            <section class="result-card wide">
                                <div class="result-card-header">
                                    <strong>نسخة ملف نشاطات المركز:</strong>
                                    <button type="button" class="icon-action" title="نسخ نسخة ملف النشاطات" data-copy-target="activity-copy">نسخ</button>
                                </div>
                                <p id="activity-copy">{{ $result['activity_file_copy'] }}</p>
                            </section>

                            <section class="result-card">
                                <div class="result-card-header">
                                    <strong>مقترح الصورة أو التصميم:</strong>
                                    <button type="button" class="icon-action" title="نسخ المقترح" data-copy-target="visual-copy">نسخ</button>
                                </div>
                                <p id="visual-copy">{{ $result['visual_suggestion'] }}</p>
                            </section>

                            <section class="result-card">
                                <div class="result-card-header">
                                    <strong>الهاشتاقات:</strong>
                                    <button type="button" class="icon-action" title="نسخ الهاشتاقات" data-copy-target="hashtags-copy">نسخ</button>
                                </div>
                                <p id="hashtags-copy">{{ $hashtagsText }}</p>
                            </section>
                        </div>
                    </article>

                    <section class="preview-shell" aria-label="معاينة المنشور">
                        <div class="preview-heading">
                            <div>
                                <span>المعاينة</span>
                                <strong>شكل النشر</strong>
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
                                    <strong>المركز الوطني للذكاء الاصطناعي</strong>
                                    <span>الآن</span>
                                </div>
                            </header>

                            <div class="facebook-post-copy">
                                <p class="post-title-line">({{ $result['suggested_title'] }})</p>
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
                                    <span>أعجبني</span>
                                    <span>تعليق</span>
                                    <span>مشاركة</span>
                                </div>
                            </footer>
                        </article>

                        <article class="instagram-post platform-preview" data-preview-panel="instagram">
                            <header class="instagram-header">
                                <img class="page-avatar logo-avatar" src="{{ asset('branding/national-ai-center-logo.jpg') }}" alt="">
                                <strong>المركز الوطني للذكاء الاصطناعي</strong>
                            </header>

                            @if ($result['image_url'])
                                <img class="instagram-image" src="{{ $result['image_url'] }}" alt="صورة مناسبة للمنشور">
                            @endif

                            <div class="instagram-caption">
                                <strong>{{ $result['suggested_title'] }}</strong>
                                <p>{{ $result['corrected_news'] }}</p>
                                <p class="instagram-tags">{{ $hashtagsText }}</p>
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
