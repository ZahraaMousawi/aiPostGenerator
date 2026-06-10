<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>برنامج التصحيح اللغوي</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
</head>
<body class="app-shell">
    <main class="workspace">
        <section class="intro-band">
            <div class="brand-mark">AI</div>
            <div>
                <p class="eyebrow">مدقق لغوي عربي</p>
                <h1>برنامج التصحيح اللغوي</h1>
                <p class="lead">أدخل النص كما هو، وسيعيد البرنامج النص مصححا لغويا مع عنوان وهاشتاقات وفكرة تصميم مناسبة.</p>
            </div>
        </section>

        <section class="tool-grid single-tool">
            <form class="input-panel" method="POST" action="{{ route('posts.generate') }}">
                @csrf
                <div class="panel-header">
                    <div>
                        <h2>النص المراد تصحيحه</h2>
                        <p>المخرج هو عنوان مقترح، النص المصحح، هاشتاقات، وفكرة صورة أو تصميم لمنشور Facebook.</p>
                    </div>
                    <span class="status-pill">Gemini</span>
                </div>

                <label for="topic">النص الأصلي</label>
                <textarea id="topic" name="topic" rows="13" placeholder="اكتب النص هنا..." required>{{ old('topic', $topic) }}</textarea>

                @error('topic')
                    <p class="error-message">{{ $message }}</p>
                @enderror

                <button type="submit" class="primary-action">
                    <span>تصحيح النص واقتراح مخرجات</span>
                    <span aria-hidden="true">↵</span>
                </button>
            </form>
        </section>

        @if ($result)
            <section class="results" aria-live="polite">
                <div class="result-header">
                    <div>
                        <p class="eyebrow">المخرج</p>
                        <h2>{{ $result['title'] }}</h2>
                    </div>
                    <span class="status-pill {{ $result['source'] === 'gemini' ? 'live' : 'warning' }}">
                        @if ($result['source'] === 'gemini')
                            Gemini
                        @else
                            يحتاج إلى إعداد
                        @endif
                    </span>
                </div>

                @if ($result['source'] !== 'gemini')
                    <div class="notice">
                        {{ $result['image_error'] }}
                    </div>
                @else
                    <div class="output-grid">
                        <article class="output-card wide">
                            <h3>العنوان المقترح</h3>
                            <p>{{ $result['suggested_title'] }}</p>
                        </article>

                        <article class="output-card wide">
                            <h3>النص بعد التصحيح</h3>
                            <p>{{ $result['corrected_news'] }}</p>
                        </article>

                        <article class="output-card wide">
                            <h3>هاشتاقات مقترحة لـ Facebook</h3>
                            <div class="hashtags">
                                @foreach ($result['hashtags'] as $hashtag)
                                    <span>{{ $hashtag }}</span>
                                @endforeach
                            </div>
                        </article>

                        <article class="output-card wide">
                            <h3>اقتراح تصميم أو صورة</h3>
                            <p>{{ $result['visual_suggestion'] }}</p>
                        </article>
                    </div>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
