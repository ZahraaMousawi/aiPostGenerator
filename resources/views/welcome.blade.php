<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>المركز الوطني للذكاء الاصطناعي</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
</head>
<body class="command-shell">
    <main class="command-center">
        <header class="command-hero">
            <div class="live-block">
                <span class="live-dot"></span>
                <strong>مراقبة مباشرة</strong>
                <small>بغداد - {{ now()->format('H:i') }}</small>
            </div>

            <div class="command-title">
                <img class="center-logo" src="{{ asset('branding/national-ai-center-logo.jpg') }}" alt="شعار المركز الوطني للذكاء الاصطناعي">
                <span>منصة المحتوى الذكي</span>
                <h1>المركز الوطني للذكاء الاصطناعي</h1>
            </div>
        </header>

        <section class="metric-grid" aria-label="مؤشرات المنشور">
            <div class="metric-card">
                <span>المدخلات</span>
                <strong>نص</strong>
                <small>تحويل المحتوى الخام</small>
            </div>
            <div class="metric-card">
                <span>المعالجة</span>
                <strong>صياغة</strong>
                <small>تصحيح وعنوان ووسوم</small>
            </div>
            <div class="metric-card">
                <span>الصورة</span>
                <strong>مشهد</strong>
                <small>مرتبطة بمعنى النص</small>
            </div>
            <div class="metric-card">
                <span>الناتج</span>
                <strong>منشور</strong>
                <small>جاهز للمراجعة والنشر</small>
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
                <textarea id="topic" name="topic" rows="9" placeholder="اكتب النص هنا..." required>{{ old('topic', $topic) }}</textarea>

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
                    <strong>{{ $result && $result['source'] === 'gemini' ? 'جاهز' : 'بانتظار النص' }}</strong>
                </div>

                <div class="signal-list">
                    <div><span></span> عنوان مناسب</div>
                    <div><span></span> نص مصحح</div>
                    <div><span></span> صورة للموضوع</div>
                    <div><span></span> هاشتاقات للنشر</div>
                </div>
            </aside>
        </section>

        @if ($result)
            <section class="post-section" aria-live="polite">
                @if ($result['source'] !== 'gemini')
                    <div class="notice">
                        {{ $result['image_error'] }}
                    </div>
                @else
                    <article class="output-summary" aria-label="مخرجات المنشور">
                        <div class="output-summary-heading">
                            <span>المخرجات</span>
                            <strong>جاهزة للمراجعة</strong>
                        </div>

                        <div class="output-row">
                            <strong>العنوان المقترح:</strong>
                            <p>{{ $result['suggested_title'] }}</p>
                        </div>

                        <div class="output-row">
                            <strong>نص المنشور المصحح:</strong>
                            <p>{{ $result['corrected_news'] }}</p>
                        </div>

                        <div class="output-row">
                            <strong>نسخة ملف نشاطات المركز:</strong>
                            <p>{{ $result['activity_file_copy'] }}</p>
                        </div>

                        <div class="output-row">
                            <strong>مقترح الصورة أو التصميم:</strong>
                            <p>{{ $result['visual_suggestion'] }}</p>
                        </div>

                        <div class="output-row">
                            <strong>الهاشتاقات:</strong>
                            <p>{{ implode(' ', $result['hashtags']) }}</p>
                        </div>
                    </article>

                    <article class="facebook-post">
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
                @endif
            </section>
        @endif
    </main>
</body>
</html>
