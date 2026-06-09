<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>وكيل منشورات المركز الوطني للذكاء الاصطناعي</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
</head>
<body class="app-shell">
    <main class="workspace">
        <section class="intro-band">
            <div class="brand-mark">AI</div>
            <div>
                <p class="eyebrow">المركز الوطني للذكاء الاصطناعي</p>
                <h1>وكيل إنشاء منشورات التواصل الاجتماعي</h1>
                <p class="lead">أدخل خبراً أو فعالية، وسيقوم الوكيل بتدقيق النص وتوليد منشور رسمي ونسخة لملف النشاطات مع عنوان ووسوم واقتراح تصميم.</p>
            </div>
        </section>

        <section class="tool-grid">
            <form class="input-panel" method="POST" action="{{ route('posts.generate') }}">
                @csrf
                <div class="panel-header">
                    <div>
                        <h2>بيانات الخبر</h2>
                        <p>اكتب الموضوع كما وصلك، حتى لو كان يحتاج إلى ترتيب أو تدقيق.</p>
                    </div>
                    <span class="status-pill">Facebook + Instagram</span>
                </div>

                <label for="topic">الموضوع أو الخبر أو الفعالية</label>
                <textarea id="topic" name="topic" rows="13" placeholder="مثال: نظم المركز الوطني للذكاء الاصطناعي ورشة تدريبية حول تطبيقات الذكاء الاصطناعي في الخدمات الحكومية..." required>{{ old('topic', $topic) }}</textarea>

                @error('topic')
                    <p class="error-message">{{ $message }}</p>
                @enderror

                <button type="submit" class="primary-action">
                    <span>توليد المنشور</span>
                    <span aria-hidden="true">↵</span>
                </button>
            </form>

            <aside class="preview-panel">
                <div class="panel-header">
                    <div>
                        <h2>سير عمل الوكيل</h2>
                        <p>المخرجات مرتبة حسب متطلبات المشروع.</p>
                    </div>
                </div>

                <div class="workflow">
                    <div><span>1</span> استقبال الموضوع</div>
                    <div><span>2</span> تدقيق لغوي ونحوي</div>
                    <div><span>3</span> توليد نسختين للمنشور</div>
                    <div><span>4</span> اقتراح عنوان ووسوم</div>
                    <div><span>5</span> اقتراح صورة أو تصميم</div>
                </div>

                <div class="design-preview">
                    <div class="preview-topline"></div>
                    <strong>National AI Center</strong>
                    <p>منشور مؤسسي واضح، عنوان مختصر، خلفية تقنية، ومساحة مرئية مناسبة لمنصات التواصل.</p>
                </div>
            </aside>
        </section>

        @if ($result)
            <section class="results" aria-live="polite">
                <div class="result-header">
                    <div>
                        <p class="eyebrow">نتيجة الوكيل</p>
                        <h2>{{ $result['title'] }}</h2>
                    </div>
                    <span class="status-pill {{ $result['source'] === 'openai' ? 'live' : '' }}">
                        {{ $result['source'] === 'openai' ? 'نموذج لغوي' : 'وضع محلي' }}
                    </span>
                </div>

                <div class="output-grid">
                    <article class="output-card wide">
                        <h3>النص بعد التدقيق</h3>
                        <p>{{ $result['corrected_news'] }}</p>
                    </article>

                    <article class="output-card">
                        <h3>النسخة الرسمية</h3>
                        <p>{!! nl2br(e($result['official_post'])) !!}</p>
                    </article>

                    <article class="output-card">
                        <h3>نسخة ملف النشاطات</h3>
                        <p>{!! nl2br(e($result['activity_post'])) !!}</p>
                    </article>

                    <article class="output-card">
                        <h3>Hashtags</h3>
                        <div class="hashtags">
                            @foreach ($result['hashtags'] as $hashtag)
                                <span>{{ $hashtag }}</span>
                            @endforeach
                        </div>
                    </article>

                    <article class="output-card">
                        <h3>اقتراح الصورة أو التصميم</h3>
                        <p>{{ $result['visual_suggestion'] }}</p>
                    </article>
                </div>
            </section>
        @endif
    </main>
</body>
</html>
