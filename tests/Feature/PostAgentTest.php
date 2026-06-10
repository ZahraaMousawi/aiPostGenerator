<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Tests\TestCase;

class PostAgentTest extends TestCase
{
    public function test_it_renders_outputs_as_a_facebook_post_without_provider_name(): void
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
                                        'suggested_title' => 'AI Training Initiative',
                                        'corrected_news' => 'The national AI center launched a specialized training initiative for health data analysis.',
                                        'hashtags' => ['#AI', '#DataAnalysis', '#HealthData'],
                                        'visual_suggestion' => 'A polished Facebook image showing people learning from data charts.',
                                        'title' => 'Corrected text with suggested outputs',
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'image.pollinations.ai/*' => Http::response('fake-png', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'The national AI center launch a specialized training initiative for health data analysis.',
        ]);

        $response
            ->assertOk()
            ->assertSee('facebook-post')
            ->assertSee('المركز الوطني للذكاء الاصطناعي')
            ->assertSee('الآن')
            ->assertSee('AI Training Initiative')
            ->assertSee('The national AI center launched a specialized training initiative for health data analysis.')
            ->assertSee('#AI')
            ->assertSee('#DataAnalysis')
            ->assertSee('generated-images/', false)
            ->assertDontSee('Gemini');

        Http::assertSentCount(2);

        collect(File::files(public_path('generated-images')))
            ->filter(fn ($file) => $file->getContents() === 'fake-png')
            ->each(fn ($file) => File::delete($file->getPathname()));
    }

    public function test_it_explains_when_ai_key_is_missing_without_provider_name(): void
    {
        Config::set('services.gemini.key', null);

        $response = $this->post('/generate', [
            'topic' => 'The national AI center launched a training workshop about AI applications.',
        ]);

        $response
            ->assertOk()
            ->assertSee('مفتاح خدمة الذكاء الاصطناعي')
            ->assertDontSee('Gemini');
    }

    public function test_it_falls_back_to_local_design_when_pollinations_rejects_generation(): void
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
                                        'suggested_title' => 'Local Design Fallback',
                                        'corrected_news' => 'Corrected post text.',
                                        'hashtags' => ['#Fallback', '#Design'],
                                        'visual_suggestion' => 'A clean Facebook scene.',
                                        'title' => 'Corrected text with suggested outputs',
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'image.pollinations.ai/*' => Http::response('Payment required', 402),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'Correct this post text please.',
        ]);

        $response
            ->assertOk()
            ->assertSee('generated-images/', false)
            ->assertDontSee('Gemini');

        collect(File::files(public_path('generated-images')))
            ->filter(fn ($file) => $file->getExtension() === 'svg' && str_contains($file->getContents(), 'generated-scene'))
            ->each(fn ($file) => File::delete($file->getPathname()));
    }

    public function test_it_falls_back_to_local_design_when_pollinations_times_out(): void
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
                                        'suggested_title' => 'Timeout Fallback',
                                        'corrected_news' => 'Corrected post text.',
                                        'hashtags' => ['#Fallback'],
                                        'visual_suggestion' => 'A clean Facebook scene.',
                                        'title' => 'Corrected text with suggested outputs',
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'image.pollinations.ai/*' => fn () => throw new ConnectionException('Timed out'),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'Correct this post text please.',
        ]);

        $response
            ->assertOk()
            ->assertSee('generated-images/', false);

        collect(File::files(public_path('generated-images')))
            ->filter(fn ($file) => $file->getExtension() === 'svg' && str_contains($file->getContents(), 'generated-scene'))
            ->each(fn ($file) => File::delete($file->getPathname()));
    }

    public function test_post_body_uses_corrected_text_not_original_text(): void
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
                                        'suggested_title' => 'عنوان مصحح',
                                        'corrected_news' => 'زوجتي جميلة.',
                                        'hashtags' => ['#العائلة'],
                                        'visual_suggestion' => 'مشهد زوجين سعيدين.',
                                        'title' => 'Corrected text with suggested outputs',
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'image.pollinations.ai/*' => Http::response('Payment required', 402),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'زوجتي عمار جميلة',
        ]);

        $response
            ->assertOk()
            ->assertSee('<p>زوجتي جميلة.</p>', false)
            ->assertDontSee('<p>زوجتي عمار جميلة</p>', false);

        collect(File::files(public_path('generated-images')))
            ->filter(fn ($file) => $file->getExtension() === 'svg' && str_contains($file->getContents(), 'generated-scene'))
            ->each(fn ($file) => File::delete($file->getPathname()));
    }

    public function test_generate_get_route_returns_the_form(): void
    {
        $this->get('/generate')
            ->assertOk()
            ->assertSee('المركز الوطني للذكاء الاصطناعي');
    }

    public function test_validation_messages_are_readable_arabic(): void
    {
        $this->from('/')->post('/generate', [
            'topic' => '',
        ])
            ->assertRedirect('/')
            ->assertSessionHasErrors([
                'topic' => 'يرجى إدخال النص.',
            ]);
    }
}
