<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostAgentTest extends TestCase
{
    public function test_it_corrects_language_and_suggests_title_with_gemini(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'suggested_title' => 'مبادرة تدريبية لتحليل البيانات الصحية',
                                        'corrected_news' => 'أطلق المركز الوطني للذكاء الاصطناعي مبادرة تدريبية متخصصة في تحليل البيانات الصحية.',
                                        'hashtags' => [
                                            '#الذكاء_الاصطناعي',
                                            '#تحليل_البيانات',
                                            '#البيانات_الصحية',
                                        ],
                                        'title' => 'تصحيح لغوي مع عنوان مقترح',
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'اطلق المركز الوطني للذكاء الاصطناعي مبادره تدريبيه في تحليل البيانات الصحيه.',
        ]);

        $response
            ->assertOk()
            ->assertSee('العنوان المقترح')
            ->assertSee('مبادرة تدريبية لتحليل البيانات الصحية')
            ->assertSee('النص بعد التصحيح')
            ->assertSee('أطلق المركز الوطني للذكاء الاصطناعي مبادرة تدريبية متخصصة في تحليل البيانات الصحية.')
            ->assertSee('هاشتاقات مقترحة لـ Facebook')
            ->assertSee('#الذكاء_الاصطناعي')
            ->assertSee('#تحليل_البيانات')
            ->assertSee('#البيانات_الصحية');

        Http::assertSentCount(1);
    }

    public function test_it_explains_when_gemini_key_is_missing(): void
    {
        Config::set('services.gemini.key', null);

        $response = $this->post('/generate', [
            'topic' => 'نظم المركز الوطني للذكاء الاصطناعي ورشة تدريبية حول تطبيقات الذكاء الاصطناعي في الخدمات الحكومية.',
        ]);

        $response
            ->assertOk()
            ->assertSee('لم يتم تفعيل المدقق اللغوي')
            ->assertSee('GEMINI_API_KEY');
    }
}
