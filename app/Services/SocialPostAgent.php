<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialPostAgent
{
    public function generate(string $topic): array
    {
        $topic = trim($topic);

        if ($this->hasOpenAiKey()) {
            $generated = $this->generateWithModel($topic);

            if ($generated !== null) {
                return $generated + ['source' => 'openai'];
            }
        }

        return $this->fallbackGenerate($topic) + ['source' => 'local'];
    }

    private function hasOpenAiKey(): bool
    {
        return filled(config('services.openai.key'));
    }

    private function generateWithModel(string $topic): ?array
    {
        $response = Http::timeout(45)
            ->acceptJson()
            ->withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5-mini'),
                'input' => $this->prompt($topic),
                'text' => [
                    'format' => [
                        'type' => 'json_object',
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $rawText = $response->json('output_text') ?? $this->collectOutputText($response->json('output', []));
        $payload = json_decode((string) $rawText, true);

        if (! is_array($payload)) {
            return null;
        }

        return $this->normalizePayload($payload, $topic);
    }

    private function prompt(string $topic): string
    {
        return <<<PROMPT
You are an Arabic AI Agent for the National Center for Artificial Intelligence social media team.
Create polished Arabic content for Facebook and Instagram from the submitted topic/news/event.

Return valid JSON only with these exact keys:
{
  "corrected_news": "Arabic proofread version",
  "title": "short suggested Arabic title",
  "official_post": "formal social post",
  "activity_post": "activity-file version focused on center activities",
  "hashtags": ["#tag1", "#tag2", "#tag3"],
  "visual_suggestion": "image/design suggestion",
  "quality_notes": ["brief note 1", "brief note 2"]
}

Rules:
- Use Modern Standard Arabic.
- Keep a professional governmental/institutional tone.
- Make the formal version suitable for Facebook and Instagram.
- Make the activity version useful for an internal/archival activity file.
- Return exactly 3 hashtags.
- Do not invent dates, locations, speakers, partners, or measurable results unless present in the input.

Input:
{$topic}
PROMPT;
    }

    private function collectOutputText(array $output): string
    {
        $text = '';

        foreach ($output as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    $text .= $content['text'] ?? '';
                }
            }
        }

        return $text;
    }

    private function normalizePayload(array $payload, string $topic): array
    {
        $fallback = $this->fallbackGenerate($topic);

        return [
            'corrected_news' => $payload['corrected_news'] ?? $fallback['corrected_news'],
            'title' => $payload['title'] ?? $fallback['title'],
            'official_post' => $payload['official_post'] ?? $fallback['official_post'],
            'activity_post' => $payload['activity_post'] ?? $fallback['activity_post'],
            'hashtags' => array_slice(array_values($payload['hashtags'] ?? $fallback['hashtags']), 0, 3),
            'visual_suggestion' => $payload['visual_suggestion'] ?? $fallback['visual_suggestion'],
            'quality_notes' => $payload['quality_notes'] ?? $fallback['quality_notes'],
        ];
    }

    private function fallbackGenerate(string $topic): array
    {
        $cleanTopic = $this->lightProofread($topic);
        $shortTitle = Str::of($cleanTopic)->words(8, '')->toString();

        return [
            'corrected_news' => $cleanTopic,
            'title' => $shortTitle !== '' ? $shortTitle : 'نشاط جديد للمركز الوطني للذكاء الاصطناعي',
            'official_post' => "يعلن المركز الوطني للذكاء الاصطناعي عن {$cleanTopic}\n\nويأتي ذلك في إطار جهود المركز لتعزيز تبني حلول الذكاء الاصطناعي، ودعم الابتكار، وتطوير القدرات الوطنية في المجالات التقنية المتقدمة.",
            'activity_post' => "ضمن نشاطات المركز الوطني للذكاء الاصطناعي، تم تناول موضوع: {$cleanTopic}\n\nيركز هذا النشاط على توثيق الجهود المعرفية والتطبيقية للمركز، وإبراز دوره في نشر الوعي وبناء القدرات في مجال الذكاء الاصطناعي.",
            'hashtags' => [
                '#الذكاء_الاصطناعي',
                '#المركز_الوطني_للذكاء_الاصطناعي',
                '#التحول_الرقمي',
            ],
            'visual_suggestion' => 'تصميم مربع لمنصات التواصل بخلفية تقنية هادئة، يتضمن شعار المركز، عنواناً واضحاً، عناصر بصرية مرتبطة بالذكاء الاصطناعي، ومساحة مختصرة لأبرز رسالة في الخبر.',
            'quality_notes' => [
                'تم تطبيق تدقيق لغوي أساسي محلياً بسبب عدم توفر مفتاح نموذج لغوي.',
                'يمكن تحسين الصياغة تلقائياً عند إضافة OPENAI_API_KEY في ملف البيئة.',
            ],
        ];
    }

    private function lightProofread(string $topic): string
    {
        $topic = preg_replace('/\s+/u', ' ', trim($topic)) ?? trim($topic);
        $topic = str_replace([' ,', ' .', ' ؛', ' :'], [',', '.', '؛', ':'], $topic);

        return rtrim($topic, " \t\n\r\0\x0B.،,") . '.';
    }
}
