<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;

class SocialPostAgent
{
    private ?string $lastError = null;

    public function generate(string $topic): array
    {
        $topic = trim($topic);

        if (! $this->hasGeminiKey()) {
            return $this->missingKeyResult();
        }

        $generated = $this->generateWithGemini($topic);

        if ($generated !== null) {
            return $generated;
        }

        return $this->failedGeminiResult($this->lastError);
    }

    private function hasGeminiKey(): bool
    {
        return filled(config('services.gemini.key'));
    }

    private function generateWithGemini(string $topic): ?array
    {
        $response = Http::timeout(35)
            ->connectTimeout(10)
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => config('services.gemini.key'),
            ])
            ->post($this->geminiGenerateContentUrl(config('services.gemini.model', 'gemini-3.5-flash')), [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->contentPrompt($topic)],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->geminiContentSchema(),
                ],
            ]);

        if (! $response->successful()) {
            $this->lastError = $this->responseErrorMessage($response->json(), $response->status(), 'خدمة الذكاء الاصطناعي');

            return null;
        }

        $payload = $this->decodeJsonFromModelText($this->collectGeminiText($response->json()));

        if (! is_array($payload)) {
            $this->lastError = 'تعذر قراءة استجابة خدمة الذكاء الاصطناعي كصيغة JSON صحيحة.';

            return null;
        }

        $normalized = $this->normalizePayload($payload);
        $image = $this->findRealImage($normalized);

        return array_merge($normalized, [
            'source' => 'gemini',
            'image_url' => $image['url'],
            'image_error' => $image['error'],
            'image_credit' => $image['credit'],
            'image_source_url' => $image['source_url'],
        ]);
    }

    private function geminiGenerateContentUrl(string $model): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';
    }

    private function contentPrompt(string $topic): string
    {
        return <<<PROMPT
أنت مدقق لغوي عربي ومحرر عناوين.
حوّل النص المدخل إلى نص منشور نهائي مصحح لغويا وإملائيا ونحويا فقط، مع الحفاظ الصارم على المعنى والمعلومات الموجودة في النص الأصلي، ثم اقترح عنوانا موجزا ومناسبا للنص المصحح.

أعد JSON فقط بدون شرح إضافي، بالمفاتيح التالية:
{
  "suggested_title": "عنوان مناسب مشتق من النص",
  "corrected_news": "نص المنشور النهائي بعد التصحيح، وليس النص الأصلي",
  "activity_file_copy": "نسخة رسمية موجزة تصلح لملف نشاطات المركز",
  "hashtags": ["#هاشتاق_مناسب", "#هاشتاق_آخر"],
  "visual_suggestion": "مقترح صورة أو تصميم مناسب للمنشور مشتق من النص المصحح",
  "title": "تصحيح لغوي مع عنوان مقترح"
}

قيود مهمة:
- لا تضف أي معلومة جديدة غير موجودة صراحة في النص الأصلي.
- لا تستنتج جهة أو مؤسسة أو مكانا أو تاريخا أو رقما أو اسما أو سببا أو نتيجة غير مذكورة.
- لا تذكر "المركز" أو "المركز الوطني" أو "المركز الوطني للذكاء الاصطناعي" إلا إذا ورد ذلك صراحة في النص الأصلي.
- لا توسع الخبر ولا تضف سياقا توضيحيا. صحح اللغة فقط وأعد ترتيب الجملة عند الحاجة.
- إذا كان النص الأصلي ناقصا أو عاما، أبقه عاما بعد التصحيح ولا تكمل النقص من عندك.
- لا تخترع تاريخا أو مكانا أو أسماء أو أرقاما.
- لا تنسخ النص المدخل كما هو إذا كان يحتوي خطأ لغويا أو تركيبا ركيكا.
- اجعل corrected_news هو النص النهائي الجاهز للنشر، لا تضع فيه الأخطاء الأصلية ولا تضف عليه معلومات خارج النص.
- اجعل activity_file_copy نسخة رسمية ومباشرة تناسب ملف نشاطات المركز، مشتقة من corrected_news، وبأسلوب توثيقي لا إعلاني.
- لا تضف في activity_file_copy وقائع أو تواريخ أو أماكن أو أرقام أو أسماء جهات غير موجودة في corrected_news.
- صحح الكلمات الزائدة أو الدخيلة الواضحة التي تكسر المعنى عندما يكون حذفها ضروريا لصياغة سليمة.
- إذا كان النص قصيرا، أعده بجملة عربية سليمة ومباشرة.
- اجعل العنوان واضحا وقصيرا ومشتقا من مضمون النص.
- اقترح من 3 إلى 6 هاشتاقات مناسبة لمنشور Facebook، قصيرة وواضحة ومشتقة من النص.
- اكتب الهاشتاقات بالعربية عند الإمكان، وابدأ كل هاشتاق بعلامة #.
- اقترح في visual_suggestion صورة أو تصميما مناسبا لمنشور Facebook يمثل معنى corrected_news، وليس بطاقة نصية.
- إذا كان النص عاطفيا أو اجتماعيا فاقترح أشخاصا ومشاعر ومكانا مناسبا. مثال: "زوجتي جميلة" = رجل وامرأة يضحكان بسعادة في مشهد دافئ.
- اجعل الاقتراح عمليا وواضحا، ولا تضف وقائع أو شعارات أو أسماء غير موجودة في النص.
- استخدم العربية الفصحى.

النص المدخل:
{$topic}
PROMPT;
    }

    private function collectGeminiText(array $payload): string
    {
        $text = '';

        foreach (($payload['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    private function findRealImage(array $content): array
    {
        $query = $this->imageSearchQuery($content);

        $pollinationsImage = $this->generatePollinationsImage($content, $query);

        if ($pollinationsImage !== null) {
            return $pollinationsImage;
        }

        $pexelsImage = $this->findPexelsImage($query);

        if ($pexelsImage !== null) {
            return $pexelsImage;
        }

        $wikimediaImage = $this->findWikimediaImage($query);

        if ($wikimediaImage !== null) {
            return $wikimediaImage;
        }

        return $this->generateLocalDesignImage($content);
    }

    private function generatePollinationsImage(array $content, string $query): ?array
    {
        try {
            $response = Http::timeout(35)
                ->connectTimeout(8)
                ->get($this->pollinationsImageUrl($this->pollinationsPrompt($content, $query)));
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));

        if (! str_starts_with($contentType, 'image/')) {
            return null;
        }

        $binary = $response->body();

        if ($binary === '') {
            return null;
        }

        $directory = public_path('generated-images');
        $filename = Str::uuid().'.'.$this->imageExtension($contentType);

        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.$filename, $binary);

        return [
            'url' => asset('generated-images/'.$filename),
            'error' => null,
            'credit' => 'الصورة: Pollinations AI',
            'source_url' => 'https://pollinations.ai',
        ];
    }

    private function pollinationsImageUrl(string $prompt): string
    {
        return 'https://image.pollinations.ai/prompt/'.rawurlencode($prompt).'?'.http_build_query([
            'width' => 1200,
            'height' => 900,
            'model' => 'flux',
            'enhance' => 'true',
            'nologo' => 'true',
            'private' => 'true',
            'safe' => 'true',
            'seed' => random_int(1, 999999),
        ]);
    }

    private function pollinationsPrompt(array $content, string $query): string
    {
        $correctedNews = trim((string) ($content['corrected_news'] ?? ''));

        return <<<PROMPT
Create an ordinary real-world photograph that could have been taken with a phone or DSLR camera.

Scene meaning from the corrected post:
{$correctedNews}

Search keywords:
{$query}

Strict image rules:
- Make it look like a real unedited photo, not a designed poster.
- Natural available light, normal colors, realistic shadows, realistic camera perspective.
- Natural people with imperfect real skin texture, normal facial features, realistic hands and body proportions.
- Candid composition, everyday background, believable clothing, believable room or outdoor environment.
- Show only the main person, people, place, object, or action implied by the corrected post.
- No written words, no Arabic text, no English text, no captions, no typography, no UI, no logos, no official seals, no watermark.
- Avoid illustration, cartoon, anime, 3D render, CGI, plastic skin, glossy advertising look, fantasy lighting, surreal elements, perfect studio lighting.
- Avoid adding brands, names, dates, numbers, famous landmarks, flags, uniforms, or symbols unless they are explicitly in the corrected post.
- If the post is abstract or about technology, show realistic people using laptops or attending a real workshop, not glowing robots or sci-fi graphics.
PROMPT;
    }

    private function imageExtension(string $mimeType): string
    {
        return match (strtolower(trim(explode(';', $mimeType)[0]))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'png',
        };
    }

    private function findPexelsImage(string $query): ?array
    {
        if (! filled(config('services.pexels.key'))) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->withToken(config('services.pexels.key'))
                ->get('https://api.pexels.com/v1/search', [
                    'query' => $query,
                    'orientation' => 'landscape',
                    'size' => 'large',
                    'per_page' => 12,
                    'locale' => 'en-US',
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->pickPexelsImage($response->json());
    }

    private function findWikimediaImage(string $query): ?array
    {
        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->get('https://commons.wikimedia.org/w/api.php', [
                    'action' => 'query',
                    'format' => 'json',
                    'origin' => '*',
                    'generator' => 'search',
                    'gsrnamespace' => 6,
                    'gsrsearch' => $this->photoSearchQuery($query),
                    'gsrlimit' => 20,
                    'prop' => 'imageinfo',
                    'iiprop' => 'url|mime|size|extmetadata',
                    'iiurlwidth' => 1200,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->pickWikimediaImage($response->json());
    }

    private function generateLocalDesignImage(array $content): array
    {
        $directory = public_path('generated-images');
        $filename = Str::uuid().'.svg';

        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.$filename, $this->localDesignSvg($content));

        return [
            'url' => asset('generated-images/'.$filename),
            'error' => null,
            'credit' => null,
            'source_url' => null,
        ];
    }

    private function localDesignSvg(array $content): string
    {
        $title = $this->escapeSvgText((string) ($content['suggested_title'] ?? 'عنوان مقترح'));
        $summary = $this->escapeSvgText((string) ($content['visual_suggestion'] ?? 'تصميم مناسب للموضوع'));
        $hashtags = array_slice($content['hashtags'] ?? [], 0, 4);
        $scene = $this->localSceneSvg((string) ($content['corrected_news'] ?? '').' '.(string) ($content['visual_suggestion'] ?? ''));

        $titleLines = $this->svgTextLines($title, 28, 2, 128, 48, 56, 700);
        $summaryLines = $this->svgTextLines($summary, 48, 2, 836, 24, 30, 400);
        $hashtagText = $this->escapeSvgText(implode('  ', $hashtags));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#fde68a"/>
      <stop offset="48%" stop-color="#fca5a5"/>
      <stop offset="100%" stop-color="#93c5fd"/>
    </linearGradient>
    <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="16" stdDeviation="18" flood-color="#334155" flood-opacity="0.22"/>
    </filter>
  </defs>
  <rect width="1024" height="1024" fill="url(#bg)"/>
  <circle cx="146" cy="156" r="74" fill="#ffffff" opacity="0.48"/>
  <circle cx="850" cy="214" r="116" fill="#ffffff" opacity="0.18"/>
  <circle cx="824" cy="802" r="152" fill="#ffffff" opacity="0.18"/>
  <rect x="80" y="74" width="864" height="876" rx="44" fill="rgba(255,255,255,0.30)" stroke="rgba(255,255,255,0.62)" stroke-width="2" filter="url(#shadow)"/>
  <text x="850" y="96" direction="rtl" unicode-bidi="plaintext" text-anchor="end" font-family="Arial, Tahoma, sans-serif" font-size="25" font-weight="700" fill="#0f172a">منشور Facebook</text>
  {$titleLines}
  {$scene}
  {$summaryLines}
  <rect x="132" y="884" width="760" height="52" rx="26" fill="rgba(15,23,42,0.14)"/>
  <text x="850" y="918" direction="rtl" unicode-bidi="plaintext" text-anchor="end" font-family="Arial, Tahoma, sans-serif" font-size="24" font-weight="700" fill="#0f172a">{$hashtagText}</text>
</svg>
SVG;
    }

    private function localSceneSvg(string $source): string
    {
        if ($this->isRelationshipScene($source)) {
            return <<<SVG
  <g id="generated-scene-couple">
    <ellipse cx="512" cy="725" rx="310" ry="64" fill="#334155" opacity="0.12"/>
    <path d="M220 620 C290 448 390 372 500 412 C610 372 742 448 806 620 C742 708 314 708 220 620Z" fill="#fff7ed" opacity="0.86"/>
    <circle cx="405" cy="406" r="92" fill="#f8c7a1"/>
    <path d="M315 390 C322 294 408 258 486 316 C476 390 410 430 315 390Z" fill="#1f2937"/>
    <path d="M330 534 C354 476 455 476 482 534 L520 702 L286 702Z" fill="#2563eb"/>
    <circle cx="622" cy="406" r="92" fill="#f4b183"/>
    <path d="M546 350 C576 276 696 292 728 382 C704 446 616 446 546 350Z" fill="#7c2d12"/>
    <path d="M548 534 C578 474 684 474 718 534 L754 702 L512 702Z" fill="#db2777"/>
    <path d="M374 420 Q405 446 436 420" fill="none" stroke="#7c2d12" stroke-width="7" stroke-linecap="round"/>
    <path d="M592 420 Q622 448 654 420" fill="none" stroke="#7c2d12" stroke-width="7" stroke-linecap="round"/>
    <path d="M462 556 C498 604 540 604 578 556" fill="none" stroke="#f97316" stroke-width="18" stroke-linecap="round"/>
    <path d="M512 292 C538 248 604 250 620 306 C616 358 546 372 512 420 C478 372 408 358 404 306 C420 250 486 248 512 292Z" fill="#ef4444" opacity="0.92"/>
  </g>
SVG;
        }

        return <<<SVG
  <g id="generated-scene-general">
    <ellipse cx="512" cy="724" rx="318" ry="70" fill="#334155" opacity="0.12"/>
    <rect x="274" y="340" width="476" height="296" rx="36" fill="#ffffff" opacity="0.74"/>
    <path d="M330 576 L440 464 L520 536 L612 424 L704 576Z" fill="#38bdf8" opacity="0.86"/>
    <circle cx="650" cy="398" r="42" fill="#facc15"/>
    <circle cx="386" cy="674" r="64" fill="#f8c7a1"/>
    <path d="M314 790 C336 706 436 706 460 790Z" fill="#2563eb"/>
    <circle cx="634" cy="674" r="64" fill="#f4b183"/>
    <path d="M562 790 C584 706 684 706 708 790Z" fill="#059669"/>
  </g>
SVG;
    }

    private function isRelationshipScene(string $source): bool
    {
        return preg_match('/زوج|زوجة|زوجتي|حبيب|حب|جميل|جميلة|امرأة|رجل|عائلة|سعادة|ضحك|يضحك/u', $source) === 1;
    }

    private function svgTextLines(string $text, int $maxChars, int $maxLines, int $startY, int $fontSize, int $lineHeight, int $fontWeight): string
    {
        $lines = $this->wrapText($text, $maxChars, $maxLines);

        return collect($lines)
            ->map(function (string $line, int $index) use ($startY, $fontSize, $lineHeight, $fontWeight) {
                $y = $startY + ($index * $lineHeight);

                return '<text x="850" y="'.$y.'" direction="rtl" unicode-bidi="plaintext" text-anchor="end" font-family="Arial, Tahoma, sans-serif" font-size="'.$fontSize.'" font-weight="'.$fontWeight.'" fill="#ffffff">'.$line.'</text>';
            })
            ->implode("\n  ");
    }

    private function wrapText(string $text, int $maxChars, int $maxLines): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = trim($current.' '.$word);

            if (mb_strlen($candidate) > $maxChars && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }

            if (count($lines) === $maxLines) {
                break;
            }
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        return $lines;
    }

    private function escapeSvgText(string $text): string
    {
        return e($text);
    }

    private function imageSearchQuery(array $content): string
    {
        $source = trim((string) ($content['corrected_news'] ?? ''));

        if ($source === '') {
            $source = trim((string) ($content['suggested_title'] ?? ''));
        }

        return $this->cleanImageSearchQuery($this->localPhotoSearchQuery($source));
    }

    private function localPhotoSearchQuery(string $source): string
    {
        $source = mb_strtolower($source);
        $keywords = [];

        $keywordMap = [
            'happy smiling couple' => ['زوج', 'زوجة', 'زوجتي', 'زوجي', 'حب', 'حبيب', 'حبيبة', 'wife', 'husband', 'couple', 'love'],
            'happy family' => ['عائلة', 'أسرة', 'اهل', 'الأهل', 'والد', 'والدة', 'أب', 'أم', 'family', 'father', 'mother', 'parents'],
            'smiling child portrait' => ['طفل', 'طفلة', 'ابنتي', 'ابني', 'بنت', 'ولد', 'أطفال', 'child', 'children', 'daughter', 'son'],
            'students graduation ceremony' => ['تخرج', 'خريج', 'خريجة', 'جامعة', 'مدرسة', 'طلاب', 'طالبات', 'graduation', 'graduate', 'university', 'school', 'students'],
            'real people using laptops in technology workshop' => ['ذكاء اصطناعي', 'الذكاء الاصطناعي', 'خوارزميات', 'تقنية', 'تكنولوجيا', 'artificial intelligence', ' ai ', 'technology', 'algorithms'],
            'people training workshop' => ['تدريب', 'ورشة', 'دورة', 'محاضرة', 'فعالية', 'ندوة', 'training', 'workshop', 'lecture', 'initiative'],
            'data analysis charts' => ['بيانات', 'تحليل', 'إحصاء', 'مخططات', 'رسوم بيانية', 'data', 'analysis', 'charts', 'statistics'],
            'healthcare professionals hospital' => ['صحة', 'طبي', 'طبية', 'مستشفى', 'أطباء', 'مرضى', 'health', 'healthcare', 'medical', 'hospital'],
            'business meeting office' => ['اجتماع', 'مؤتمر', 'عمل', 'فريق', 'مكتب', 'meeting', 'conference', 'business', 'team', 'office'],
            'city street photo' => ['مدينة', 'شارع', 'بغداد', 'محافظة', 'مكان', 'city', 'street', 'baghdad'],
            'nature landscape' => ['طبيعة', 'حديقة', 'أشجار', 'زهور', 'منظر', 'nature', 'garden', 'trees', 'flowers'],
        ];

        foreach ($keywordMap as $keyword => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($source, $needle)) {
                    $keywords[] = $keyword;
                    break;
                }
            }
        }

        if ($keywords !== []) {
            return implode(' ', array_slice(array_unique($keywords), 0, 3));
        }

        $latinWords = preg_split('/\s+/u', preg_replace('/[^a-z0-9\s-]+/i', ' ', $source) ?? '') ?: [];
        $latinWords = array_values(array_filter($latinWords, fn ($word) => mb_strlen($word) > 2));

        if ($latinWords !== []) {
            return implode(' ', array_slice($latinWords, 0, 8));
        }

        return 'real life people photo';
    }

    private function cleanImageSearchQuery(string $query): string
    {
        $query = preg_replace('/[^\pL\pN\s-]+/u', ' ', $query) ?? $query;
        $query = preg_replace('/\s+/u', ' ', trim($query)) ?? trim($query);

        return Str::limit($query, 90, '');
    }

    private function photoSearchQuery(string $query): string
    {
        $query = trim($query);

        if ($query === '') {
            return 'realistic photo';
        }

        return $query.' photograph -logo -icon -illustration -vector -diagram -map';
    }

    private function pickPexelsImage(?array $payload): ?array
    {
        foreach (data_get($payload, 'photos', []) as $photo) {
            if (! is_array($photo)) {
                continue;
            }

            $url = (string) (
                data_get($photo, 'src.large2x')
                ?? data_get($photo, 'src.large')
                ?? data_get($photo, 'src.original')
                ?? ''
            );

            if ($url === '') {
                continue;
            }

            $photographer = trim((string) ($photo['photographer'] ?? ''));
            $credit = $photographer !== ''
                ? 'الصورة: '.$photographer.' / Pexels'
                : 'الصورة: Pexels';

            return [
                'url' => $url,
                'error' => null,
                'credit' => $credit,
                'source_url' => filled($photo['url'] ?? null) ? (string) $photo['url'] : null,
            ];
        }

        return null;
    }

    private function pickWikimediaImage(?array $payload): ?array
    {
        $pages = data_get($payload, 'query.pages', []);

        foreach ($pages as $page) {
            $info = $page['imageinfo'][0] ?? null;

            if (! is_array($info)) {
                continue;
            }

            $mime = (string) ($info['mime'] ?? '');
            $url = (string) ($info['thumburl'] ?? $info['url'] ?? '');
            $width = (int) ($info['width'] ?? 0);
            $height = (int) ($info['height'] ?? 0);
            $title = (string) ($page['title'] ?? '');

            if (! str_starts_with($mime, 'image/')) {
                continue;
            }

            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $url === '') {
                continue;
            }

            if (($width > 0 && $width < 640) || ($height > 0 && $height < 420)) {
                continue;
            }

            if ($this->looksLikeNonPhoto($title)) {
                continue;
            }

            return [
                'url' => $url,
                'error' => null,
                'credit' => $this->wikimediaCredit($info),
                'source_url' => filled($info['descriptionurl'] ?? null) ? (string) $info['descriptionurl'] : null,
            ];
        }

        return null;
    }

    private function looksLikeNonPhoto(string $title): bool
    {
        return preg_match('/logo|icon|diagram|map|chart|graph|svg|seal|flag|coat of arms|illustration|vector/i', $title) === 1;
    }

    private function wikimediaCredit(array $info): string
    {
        $artist = $this->plainMetadataValue(data_get($info, 'extmetadata.Artist.value'));
        $license = $this->plainMetadataValue(data_get($info, 'extmetadata.LicenseShortName.value'));
        $parts = array_values(array_filter([$artist, $license, 'Wikimedia Commons']));

        return 'الصورة: '.implode(' / ', $parts);
    }

    private function plainMetadataValue(mixed $value): ?string
    {
        $value = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value !== '' ? $value : null;
    }

    private function decodeJsonFromModelText(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/u', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/u', '', $text) ?? $text;

        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/us', $text, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizePayload(array $payload): array
    {
        return [
            'suggested_title' => trim((string) ($payload['suggested_title'] ?? '')),
            'corrected_news' => trim((string) ($payload['corrected_news'] ?? '')),
            'activity_file_copy' => trim((string) ($payload['activity_file_copy'] ?? $payload['corrected_news'] ?? '')),
            'hashtags' => $this->normalizeHashtags($payload['hashtags'] ?? []),
            'visual_suggestion' => trim((string) ($payload['visual_suggestion'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? 'تصحيح لغوي مع عنوان مقترح')),
        ];
    }

    private function normalizeHashtags(mixed $hashtags): array
    {
        if (! is_array($hashtags)) {
            return [];
        }

        return collect($hashtags)
            ->map(fn ($hashtag) => trim((string) $hashtag))
            ->filter()
            ->map(fn ($hashtag) => str_starts_with($hashtag, '#') ? $hashtag : '#'.$hashtag)
            ->values()
            ->all();
    }

    private function geminiContentSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'suggested_title' => ['type' => 'string'],
                'corrected_news' => ['type' => 'string'],
                'activity_file_copy' => ['type' => 'string'],
                'hashtags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'visual_suggestion' => ['type' => 'string'],
                'title' => ['type' => 'string'],
            ],
            'required' => [
                'suggested_title',
                'corrected_news',
                'activity_file_copy',
                'hashtags',
                'visual_suggestion',
                'title',
            ],
        ];
    }

    private function responseErrorMessage(?array $payload, int $status, string $provider): string
    {
        $message = data_get($payload, 'error.message');

        if (filled($message)) {
            $lowerMessage = strtolower((string) $message);

            if (
                str_contains($lowerMessage, 'invalid authentication credentials')
                || str_contains($lowerMessage, 'api key not valid')
                || str_contains($lowerMessage, 'permission_denied')
            ) {
                return 'مفتاح خدمة الذكاء الاصطناعي غير صالح أو تم رفضه. أنشئ مفتاحا جديدا، ثم شغل php artisan config:clear.';
            }

            return $provider.': '.$message;
        }

        return $provider.' returned HTTP '.$status.'.';
    }

    private function missingKeyResult(): array
    {
        return [
            'source' => 'missing_key',
            'suggested_title' => '',
            'corrected_news' => '',
            'activity_file_copy' => '',
            'hashtags' => [],
            'visual_suggestion' => '',
            'image_url' => null,
            'image_credit' => null,
            'image_source_url' => null,
            'title' => 'لم يتم تفعيل المدقق اللغوي',
            'image_error' => 'أضف مفتاح خدمة الذكاء الاصطناعي في ملف .env ثم أعد تشغيل الخادم حتى يتم إنشاء المنشور.',
        ];
    }

    private function failedGeminiResult(?string $details = null): array
    {
        return [
            'source' => 'gemini_error',
            'suggested_title' => '',
            'corrected_news' => '',
            'activity_file_copy' => '',
            'hashtags' => [],
            'visual_suggestion' => '',
            'image_url' => null,
            'image_credit' => null,
            'image_source_url' => null,
            'title' => 'تعذر إنشاء المنشور',
            'image_error' => $details
                ? 'فشل إنشاء المنشور: '.$details
                : 'فشل إنشاء المنشور. تحقق من مفتاح خدمة الذكاء الاصطناعي واسم النموذج.',
        ];
    }
}
