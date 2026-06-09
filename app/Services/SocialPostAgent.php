<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SocialPostAgent
{
    public function generate(string $topic): array
    {
        $topic = trim($topic);

        if ($this->hasGeminiKey()) {
            $generated = $this->generateWithGemini($topic);

            if ($generated !== null) {
                return $generated;
            }
        }

        if (! $this->hasOpenAiKey()) {
            return $this->missingKeyResult();
        }

        $content = $this->generateContentWithModel($topic);

        if ($content === null) {
            return $this->failedTextResult();
        }

        $image = $this->generateImageWithModel($content['image_prompt'] ?? $content['visual_suggestion'] ?? $topic);

        return array_merge($content, [
            'source' => 'openai',
            'image_data_url' => $image['data_url'],
            'image_error' => $image['error'],
        ]);
    }

    private function hasOpenAiKey(): bool
    {
        return filled(config('services.openai.key'));
    }

    private function hasGeminiKey(): bool
    {
        return filled(config('services.gemini.key'));
    }

    private function generateWithGemini(string $topic): ?array
    {
        $content = $this->generateContentWithGemini($topic);

        if ($content === null) {
            return null;
        }

        $image = $this->generateImageWithGemini($content['image_prompt'] ?? $content['visual_suggestion'] ?? $topic);

        return array_merge($content, [
            'source' => 'gemini',
            'image_data_url' => $image['data_url'],
            'image_error' => $image['error'],
        ]);
    }

    private function generateContentWithGemini(string $topic): ?array
    {
        $response = Http::timeout(60)
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
                    'responseFormat' => [
                        'text' => [
                            'mimeType' => 'application/json',
                            'schema' => $this->contentSchema(),
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $rawText = $this->collectGeminiText($response->json());
        $payload = $this->decodeJsonFromModelText($rawText);

        return is_array($payload) ? $this->normalizePayload($payload) : null;
    }

    private function generateImageWithGemini(string $prompt): array
    {
        $response = Http::timeout(90)
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => config('services.gemini.key'),
            ])
            ->post($this->geminiGenerateContentUrl(config('services.gemini.image_model', 'gemini-3.1-flash-image')), [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->imagePrompt($prompt)],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return [
                'data_url' => null,
                'error' => 'تعذر توليد الصورة من Gemini. تحقق من صلاحية GEMINI_API_KEY واسم نموذج الصور.',
            ];
        }

        $image = $this->collectGeminiImage($response->json());

        return [
            'data_url' => $image ? 'data:'.$image['mime_type'].';base64,'.$image['data'] : null,
            'error' => $image ? null : 'استجاب Gemini بدون صورة قابلة للعرض.',
        ];
    }

    private function generateContentWithModel(string $topic): ?array
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5-mini'),
                'input' => $this->contentPrompt($topic),
            ]);

        if (! $response->successful()) {
            return null;
        }

        $rawText = $response->json('output_text') ?? $this->collectOutputText($response->json('output', []));
        $payload = $this->decodeJsonFromModelText((string) $rawText);

        if (! is_array($payload)) {
            return null;
        }

        return $this->normalizePayload($payload);
    }

    private function generateImageWithModel(string $prompt): array
    {
        $response = Http::timeout(90)
            ->acceptJson()
            ->withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => config('services.openai.image_model', 'gpt-image-1.5'),
                'prompt' => $this->imagePrompt($prompt),
                'n' => 1,
                'size' => '1024x1024',
            ]);

        if (! $response->successful()) {
            return [
                'data_url' => null,
                'error' => 'تعذر توليد الصورة من نموذج الصور. تحقق من صلاحية المفتاح واسم نموذج الصور.',
            ];
        }

        $base64 = $response->json('data.0.b64_json');

        return [
            'data_url' => $base64 ? 'data:image/png;base64,'.$base64 : null,
            'error' => $base64 ? null : 'استجاب نموذج الصور بدون صورة قابلة للعرض.',
        ];
    }

    private function geminiGenerateContentUrl(string $model): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';
    }

    private function contentPrompt(string $topic): string
    {
        return <<<PROMPT
أنت AI Agent عربي لفريق التواصل الاجتماعي في المركز الوطني للذكاء الاصطناعي.
مهمتك معالجة النص المدخل فعلياً، وليس تكراره:

1. صحح الأخطاء اللغوية والنحوية والإملائية.
2. أعد ترتيب الخبر ليصبح مناسباً للنشر المؤسسي.
3. أنشئ نسختين مختلفتين من المنشور:
   - نسخة رسمية مناسبة لـ Facebook وInstagram.
   - نسخة لملف نشاطات المركز بصياغة توثيقية.
4. اقترح عنواناً قصيراً.
5. ولّد 3 Hashtags مرتبطة مباشرة بموضوع النص. لا تستخدم وسوماً عامة ثابتة إلا إذا كانت مرتبطة بالنص.
6. اقترح وصفاً بصرياً، ثم اكتب prompt إنجليزي واضح لتوليد صورة مربعة للمنشور.

أعد JSON فقط بدون شرح إضافي، بالمفاتيح التالية:
{
  "corrected_news": "النص المصحح",
  "title": "العنوان المقترح",
  "official_post": "النسخة الرسمية",
  "activity_post": "نسخة ملف النشاطات",
  "hashtags": ["#...", "#...", "#..."],
  "visual_suggestion": "اقتراح الصورة أو التصميم بالعربية",
  "image_prompt": "English image generation prompt, square social media post, no readable text, institutional AI center visual"
}

قيود مهمة:
- لا تخترع تاريخاً أو مكاناً أو أسماء أو أرقاماً غير موجودة في النص.
- استخدم العربية الفصحى.
- اجعل النص النهائي مختلفاً ومحسناً بوضوح عن الإدخال.
- الهاشتاكات يجب أن تأتي من معنى الخبر نفسه.
- لا تضع نصوصاً قابلة للقراءة داخل الصورة المقترحة.

النص المدخل:
{$topic}
PROMPT;
    }

    private function imagePrompt(string $prompt): string
    {
        return <<<PROMPT
Create a professional square social media image for an official National Artificial Intelligence Center post.
Use a clean institutional visual style, modern AI motifs, subtle circuit patterns, data visualization shapes, and balanced blue, green, white, and gold accents.
Do not include readable text, logos, flags, people with identifiable faces, or fake badges.
Core topic:
{$prompt}
PROMPT;
    }

    private function collectOutputText(array $output): string
    {
        $text = '';

        foreach ($output as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (in_array(($content['type'] ?? null), ['output_text', 'text'], true)) {
                    $text .= $content['text'] ?? '';
                }
            }
        }

        return $text;
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

    private function collectGeminiImage(array $payload): ?array
    {
        foreach (($payload['candidates'][0]['content']['parts'] ?? []) as $part) {
            $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;

            if (is_array($inlineData) && isset($inlineData['data'])) {
                return [
                    'data' => $inlineData['data'],
                    'mime_type' => $inlineData['mimeType'] ?? $inlineData['mime_type'] ?? 'image/png',
                ];
            }
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
        $hashtags = collect($payload['hashtags'] ?? [])
            ->filter(fn ($hashtag) => is_string($hashtag) && trim($hashtag) !== '')
            ->map(fn ($hashtag) => str_starts_with(trim($hashtag), '#') ? trim($hashtag) : '#'.trim($hashtag))
            ->take(3)
            ->values()
            ->all();

        return [
            'corrected_news' => trim((string) ($payload['corrected_news'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? 'منشور جديد للمركز الوطني للذكاء الاصطناعي')),
            'official_post' => trim((string) ($payload['official_post'] ?? '')),
            'activity_post' => trim((string) ($payload['activity_post'] ?? '')),
            'hashtags' => $hashtags,
            'visual_suggestion' => trim((string) ($payload['visual_suggestion'] ?? '')),
            'image_prompt' => trim((string) ($payload['image_prompt'] ?? '')),
        ];
    }

    private function contentSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'corrected_news' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'official_post' => ['type' => 'string'],
                'activity_post' => ['type' => 'string'],
                'hashtags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'visual_suggestion' => ['type' => 'string'],
                'image_prompt' => ['type' => 'string'],
            ],
            'required' => [
                'corrected_news',
                'title',
                'official_post',
                'activity_post',
                'hashtags',
                'visual_suggestion',
                'image_prompt',
            ],
        ];
    }

    private function missingKeyResult(): array
    {
        return [
            'source' => 'missing_key',
            'corrected_news' => '',
            'title' => 'لم يتم تفعيل نموذج الذكاء الاصطناعي',
            'official_post' => '',
            'activity_post' => '',
            'hashtags' => [],
            'visual_suggestion' => '',
            'image_prompt' => '',
            'image_data_url' => null,
            'image_error' => 'أضف GEMINI_API_KEY أو OPENAI_API_KEY في ملف .env ثم أعد تشغيل الخادم حتى يتم توليد النص والهاشتاكات والصورة بواسطة AI.',
        ];
    }

    private function failedTextResult(): array
    {
        return [
            'source' => 'openai_error',
            'corrected_news' => '',
            'title' => 'تعذر توليد المحتوى',
            'official_post' => '',
            'activity_post' => '',
            'hashtags' => [],
            'visual_suggestion' => '',
            'image_prompt' => '',
            'image_data_url' => null,
            'image_error' => 'فشل الاتصال بنموذج اللغة أو تعذر تحليل الاستجابة. تحقق من المفتاح واسم النموذج.',
        ];
    }
}
