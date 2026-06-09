<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PostAgentTest extends TestCase
{
    public function test_it_generates_structured_social_post_outputs(): void
    {
        Config::set('services.openai.key', null);

        $response = $this->post('/generate', [
            'topic' => 'نظم المركز الوطني للذكاء الاصطناعي ورشة تدريبية حول تطبيقات الذكاء الاصطناعي في الخدمات الحكومية.',
        ]);

        $response
            ->assertOk()
            ->assertSee('النسخة الرسمية')
            ->assertSee('نسخة ملف النشاطات')
            ->assertSee('#الذكاء_الاصطناعي');
    }
}
