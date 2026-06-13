<?php

namespace App\Services;

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
- اجعل visual_suggestion عبارة بحث قصيرة ومختصرة لصورة واقعية مناسبة في Pexels، من 3 إلى 7 كلمات، وتمثل معنى corrected_news وليست بطاقة نصية.
- لا تكتب في visual_suggestion تعليمات لتوليد صورة، ولا تذكر أي خدمة توليد صور.
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

        $pexelsImage = $this->findPexelsImage($query);

        if ($pexelsImage !== null) {
            return $pexelsImage;
        }

        return [
            'url' => null,
            'error' => filled(config('services.pexels.key'))
                ? 'لم يعثر Pexels على صورة مناسبة للمقترح: '.$query
                : 'أضف PEXELS_API_KEY في ملف .env لاستخدام صور Pexels.',
            'credit' => null,
            'source_url' => null,
        ];
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
                ->withHeaders([
                    'Authorization' => config('services.pexels.key'),
                ])
                ->get('https://api.pexels.com/v1/search', [
                    'query' => $query,
                    'orientation' => 'landscape',
                    'size' => 'large',
                    'per_page' => 10,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->pickPexelsImage($response->json());
    }

    private function imageSearchQuery(array $content): string
    {
        $source = trim((string) ($content['visual_suggestion'] ?? ''));

        if ($source === '') {
            $source = trim((string) ($content['corrected_news'] ?? ''));
        }

        if ($source === '') {
            $source = trim((string) ($content['suggested_title'] ?? ''));
        }

        return $this->cleanImageSearchQuery($this->localPhotoSearchQuery(
            $this->summarizeVisualSuggestion($source)
        ));
    }

    private function summarizeVisualSuggestion(string $suggestion): string
    {
        $suggestion = preg_replace('/[^\pL\pN\s-]+/u', ' ', $suggestion) ?? $suggestion;
        $suggestion = preg_replace('/\s+/u', ' ', trim($suggestion)) ?? trim($suggestion);

        $stopWords = [
            'a', 'an', 'the', 'for', 'with', 'and', 'or', 'of', 'in', 'on', 'to', 'from',
            'photo', 'image', 'picture', 'design', 'facebook', 'post', 'scene', 'suitable',
            'صورة', 'تصميم', 'مشهد', 'مناسب', 'مناسبة', 'لمنشور', 'منشور', 'فيسبوك',
            'يعبر', 'تعبر', 'يمثل', 'تمثل', 'عن', 'في', 'من', 'على', 'مع', 'او', 'أو', 'و',
        ];

        $words = preg_split('/\s+/u', $suggestion, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter($words, function (string $word) use ($stopWords) {
            $normalized = mb_strtolower(trim($word, " \t\n\r\0\x0B-"));

            return $normalized !== '' && ! in_array($normalized, $stopWords, true);
        }));

        return implode(' ', array_slice($words, 0, 8)) ?: 'real life people photo';
    }

    private function localPhotoSearchQuery(string $source): string
    {
        $source = mb_strtolower($source);
        $keywords = [];

        $keywordMap = [
            'happy smiling couple' => ['زوج', 'زوجة', 'زوجتي', 'زوجي', 'حب', 'حبيب', 'حبيبة', 'wife', 'husband', 'couple', 'love'],
            'happy family' => ['عائلة', 'أسرة', 'اهل', 'الأهل', 'والد', 'والدة', 'أب', 'أم', 'family', 'father', 'mother', 'parents'],
            'cute baby portrait' => ['رضيع', 'مولود', 'طفل رضيع', 'baby', 'infant', 'cute baby'],
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

        return $this->cleanImageSearchQuery($source) ?: 'real life people photo';
    }

    private function cleanImageSearchQuery(string $query): string
    {
        $query = preg_replace('/[^\pL\pN\s-]+/u', ' ', $query) ?? $query;
        $query = preg_replace('/\s+/u', ' ', trim($query)) ?? trim($query);

        return Str::limit($query, 90, '');
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
