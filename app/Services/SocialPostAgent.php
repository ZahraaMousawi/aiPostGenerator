<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->geminiContentSchema(),
                ],
            ]);

        if (! $response->successful()) {
            $this->lastError = $this->responseErrorMessage($response->json(), $response->status(), 'Gemini API');

            return null;
        }

        $payload = $this->decodeJsonFromModelText($this->collectGeminiText($response->json()));

        if (! is_array($payload)) {
            $this->lastError = 'تعذر قراءة استجابة Gemini كصيغة JSON صحيحة.';

            return null;
        }

        return array_merge($this->normalizePayload($payload), [
            'source' => 'gemini',
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
صحح النص المدخل لغويا وإملائيا ونحويا مع الحفاظ على المعنى الأصلي، ثم اقترح عنوانا موجزا ومناسبا للنص المصحح.

أعد JSON فقط بدون شرح إضافي، بالمفاتيح التالية:
{
  "suggested_title": "عنوان مناسب مشتق من النص",
  "corrected_news": "النص المصحح",
  "hashtags": ["#هاشتاق_مناسب", "#هاشتاق_آخر"],
  "visual_suggestion": "اقتراح تصميم أو صورة مناسبة للموضوع",
  "title": "تصحيح لغوي مع عنوان مقترح"
}

قيود مهمة:
- لا تضف معلومات جديدة غير موجودة في النص.
- لا تخترع تاريخا أو مكانا أو أسماء أو أرقاما.
- اجعل العنوان واضحا وقصيرا ومشتقا من مضمون النص.
- اقترح من 3 إلى 6 هاشتاقات مناسبة لمنشور Facebook، قصيرة وواضحة ومشتقة من النص.
- اكتب الهاشتاقات بالعربية عند الإمكان، وابدأ كل هاشتاق بعلامة #.
- اقترح فكرة تصميم أو صورة مناسبة لمنشور Facebook، مع وصف العناصر البصرية والألوان والنص الظاهر إن وجد.
- اجعل اقتراح التصميم عمليا وواضحا، ولا تضف وقائع أو شعارات أو أسماء غير موجودة في النص.
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
            'hashtags' => [],
            'visual_suggestion' => '',
            'title' => 'لم يتم تفعيل المدقق اللغوي',
            'image_error' => 'أضف GEMINI_API_KEY في ملف .env ثم أعد تشغيل الخادم حتى يتم تصحيح النص بواسطة Gemini.',
        ];
    }

    private function failedGeminiResult(?string $details = null): array
    {
        return [
            'source' => 'gemini_error',
            'suggested_title' => '',
            'corrected_news' => '',
            'hashtags' => [],
            'visual_suggestion' => '',
            'title' => 'تعذر التصحيح عبر Gemini',
            'image_error' => $details
                ? 'فشل Gemini في تصحيح النص: '.$details
                : 'فشل Gemini في تصحيح النص. تحقق من GEMINI_API_KEY واسم النموذج.',
        ];
    }
}
