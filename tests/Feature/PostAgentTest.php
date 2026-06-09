<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostAgentTest extends TestCase
{
    public function test_it_prefers_gemini_for_content_hashtags_and_image(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');
        Config::set('services.gemini.image_model', 'gemini-3.1-flash-image');
        Config::set('services.openai.key', null);

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'corrected_news' => 'أطلق المركز الوطني للذكاء الاصطناعي مبادرة تدريبية متخصصة في تحليل البيانات الصحية.',
                                        'title' => 'مبادرة لتحليل البيانات الصحية',
                                        'official_post' => 'أطلق المركز الوطني للذكاء الاصطناعي مبادرة تدريبية متخصصة لتعزيز استخدام الذكاء الاصطناعي في تحليل البيانات الصحية.',
                                        'activity_post' => 'ضمن نشاطات المركز، تم تنفيذ مبادرة تدريبية ركزت على تحليل البيانات الصحية باستخدام تقنيات الذكاء الاصطناعي.',
                                        'hashtags' => ['#البيانات_الصحية', '#تحليل_البيانات', '#الصحة_الرقمية'],
                                        'visual_suggestion' => 'تصميم يدمج مؤشرات صحية رقمية مع عناصر ذكاء اصطناعي.',
                                        'image_prompt' => 'Square institutional AI healthcare data analysis visual, no readable text.',
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'generativelanguage.googleapis.com/*gemini-3.1-flash-image*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => base64_encode('gemini-image-bytes'),
                                    ],
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
            ->assertSee('Gemini مفعل')
            ->assertSee('#البيانات_الصحية')
            ->assertSee('data:image/png;base64,'.base64_encode('gemini-image-bytes'), false);
    }

    public function test_it_generates_content_hashtags_and_image_with_openai(): void
    {
        Config::set('services.gemini.key', null);
        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-5-mini');
        Config::set('services.openai.image_model', 'gpt-image-1.5');

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'corrected_news' => 'نظم المركز الوطني للذكاء الاصطناعي ورشة تدريبية متخصصة حول تطبيقات الذكاء الاصطناعي في الخدمات الحكومية.',
                    'title' => 'ورشة تطبيقات الذكاء الاصطناعي الحكومية',
                    'official_post' => 'أقام المركز الوطني للذكاء الاصطناعي ورشة تدريبية متخصصة لتعزيز توظيف تقنيات الذكاء الاصطناعي في تطوير الخدمات الحكومية.',
                    'activity_post' => 'ضمن نشاطات المركز، تم تنفيذ ورشة تدريبية تناولت تطبيقات الذكاء الاصطناعي في الخدمات الحكومية.',
                    'hashtags' => ['#الخدمات_الحكومية', '#ورشة_تدريبية', '#الابتكار_الحكومي'],
                    'visual_suggestion' => 'تصميم مؤسسي يظهر واجهات خدمات حكومية رقمية مع عناصر ذكاء اصطناعي.',
                    'image_prompt' => 'Official square social media design about AI in government services, no readable text.',
                ], JSON_UNESCAPED_UNICODE),
            ], 200),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [
                    ['b64_json' => base64_encode('fake-image-bytes')],
                ],
            ], 200),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'نضم المركز الوطني للذكاء الاصطناعي ورشه تدريبيه عن تطبيقات الذكاء الاصطناعي في الخدمات الحكوميه.',
        ]);

        $response
            ->assertOk()
            ->assertSee('ورشة تطبيقات الذكاء الاصطناعي الحكومية')
            ->assertSee('#الخدمات_الحكومية')
            ->assertSee('data:image/png;base64,'.base64_encode('fake-image-bytes'), false)
            ->assertDontSee('#الذكاء_الاصطناعي');
    }

    public function test_it_explains_when_openai_key_is_missing(): void
    {
        Config::set('services.gemini.key', null);
        Config::set('services.openai.key', null);

        $response = $this->post('/generate', [
            'topic' => 'نظم المركز الوطني للذكاء الاصطناعي ورشة تدريبية حول تطبيقات الذكاء الاصطناعي في الخدمات الحكومية.',
        ]);

        $response
            ->assertOk()
            ->assertSee('لم يتم تفعيل نموذج الذكاء الاصطناعي')
            ->assertSee('GEMINI_API_KEY');
    }
}
