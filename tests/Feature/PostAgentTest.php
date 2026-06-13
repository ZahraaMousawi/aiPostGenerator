<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostAgentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pexels.key', null);
    }

    public function test_it_renders_outputs_with_a_real_image_result(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');
        Config::set('services.pexels.key', 'pexels-test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'AI Training Initiative',
                'corrected_news' => 'The national AI center launched a specialized training initiative for health data analysis.',
                'activity_file_copy' => 'A specialized training initiative for health data analysis was launched.',
                'hashtags' => ['#AI', '#DataAnalysis', '#HealthData'],
                'visual_suggestion' => 'People learning from data charts.',
            ]), 200),
            'api.pexels.com/v1/search*' => Http::response($this->pexelsPayload(
                'https://images.pexels.com/photos/example/data-workshop.jpeg',
                'Jane Doe'
            ), 200),
            'api.pexels.com/videos/search*' => Http::response($this->pexelsVideoPayload(
                'https://videos.pexels.com/video-files/example/data-workshop.mp4',
                'https://images.pexels.com/videos/example/data-workshop.jpeg',
                'John Video'
            ), 200),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'The national AI center launch a specialized training initiative for health data analysis.',
        ]);

        $response
            ->assertOk()
            ->assertSee('facebook-post')
            ->assertSee('المركز الوطني للذكاء الاصطناعي')
            ->assertSee('العنوان المقترح:')
            ->assertSee('AI Training Initiative')
            ->assertSee('نص المنشور المصحح:')
            ->assertSee('The national AI center launched a specialized training initiative for health data analysis.')
            ->assertSee('نسخة ملف نشاطات المركز:')
            ->assertSee('A specialized training initiative for health data analysis was launched.')
            ->assertSee('مقترح الصورة أو التصميم:')
            ->assertSee('People learning from data charts.')
            ->assertSee('الهاشتاقات:')
            ->assertSee('#AI')
            ->assertSee('https://images.pexels.com/photos/example/data-workshop.jpeg', false)
            ->assertSee('الصورة: Jane Doe / Pexels')
            ->assertSee('https://videos.pexels.com/video-files/example/data-workshop.mp4', false)
            ->assertSee('الفيديو: John Video / Pexels')
            ->assertDontSee('Gemini');

        Http::assertSent(function ($request) {
            $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text', '');

            return str_contains($request->url(), 'generativelanguage.googleapis.com')
                && ! str_contains($prompt, 'image_search_query')
                && str_contains($prompt, 'لا تضف أي معلومة جديدة')
                && str_contains($prompt, 'لا تذكر "المركز"')
                && str_contains($prompt, 'عبارة بحث قصيرة ومختصرة');
        });
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.pexels.com/v1/search')
            && $request->hasHeader('Authorization', 'pexels-test-key')
            && str_contains(urldecode($request->url()), 'query=data analysis charts')
            && str_contains(urldecode($request->url()), 'per_page=10'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.pexels.com/videos/search')
            && $request->hasHeader('Authorization', 'pexels-test-key')
            && str_contains(urldecode($request->url()), 'query=data analysis charts')
            && str_contains(urldecode($request->url()), 'orientation=portrait')
            && str_contains(urldecode($request->url()), 'per_page=10'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'image.pollinations.ai'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'commons.wikimedia.org'));
    }

    public function test_it_uses_pexels_photo_from_summarized_visual_suggestion(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');
        Config::set('services.pexels.key', 'pexels-test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'Family Celebration',
                'corrected_news' => 'A family celebrates a graduation ceremony together.',
                'activity_file_copy' => 'A family celebrates a graduation ceremony together.',
                'hashtags' => ['#Family', '#Graduation'],
                'visual_suggestion' => 'A family celebrating graduation together.',
            ]), 200),
            'api.pexels.com/v1/search*' => Http::response($this->pexelsPayload(
                'https://images.pexels.com/photos/example/family-graduation.jpeg',
                'Jane Doe'
            ), 200),
            'api.pexels.com/videos/search*' => Http::response($this->pexelsVideoPayload(
                'https://videos.pexels.com/video-files/example/family-graduation.mp4',
                'https://images.pexels.com/videos/example/family-graduation.jpeg',
                'John Video'
            ), 200),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'A family celebrate a graduation ceremony together.',
        ]);

        $response
            ->assertOk()
            ->assertSee('https://images.pexels.com/photos/example/family-graduation.jpeg', false)
            ->assertSee('الصورة: Jane Doe / Pexels')
            ->assertSee('https://videos.pexels.com/video-files/example/family-graduation.mp4', false)
            ->assertSee('الفيديو: John Video / Pexels');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.pexels.com/v1/search')
            && $request->hasHeader('Authorization', 'pexels-test-key')
            && str_contains(urldecode($request->url()), 'query=happy family students graduation ceremony'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.pexels.com/videos/search')
            && $request->hasHeader('Authorization', 'pexels-test-key')
            && str_contains(urldecode($request->url()), 'query=happy family students graduation ceremony'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'image.pollinations.ai'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'commons.wikimedia.org'));
    }

    public function test_it_explains_when_ai_key_is_missing_without_provider_name(): void
    {
        Config::set('services.gemini.key', null);

        $this->post('/generate', [
            'topic' => 'The national AI center launched a training workshop about AI applications.',
        ])
            ->assertOk()
            ->assertSee('مفتاح خدمة الذكاء الاصطناعي')
            ->assertDontSee('Gemini');
    }

    public function test_it_explains_when_pexels_key_is_missing_without_generating_image(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'Pexels Key Missing',
                'corrected_news' => 'Corrected post text.',
                'activity_file_copy' => 'Corrected activity file text.',
                'hashtags' => ['#Pexels'],
                'visual_suggestion' => 'A clean Facebook scene.',
            ]), 200),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'Correct this post text please.',
        ]);

        $response
            ->assertOk()
            ->assertSee('PEXELS_API_KEY')
            ->assertSee('فيديو Reels')
            ->assertDontSee('generated-images/', false)
            ->assertDontSee('Gemini');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.pexels.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'image.pollinations.ai'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'commons.wikimedia.org'));
    }

    public function test_it_shows_message_when_pexels_has_no_results(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');
        Config::set('services.pexels.key', 'pexels-test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'No Pexels Result',
                'corrected_news' => 'Corrected post text.',
                'activity_file_copy' => 'Corrected activity file text.',
                'hashtags' => ['#Fallback'],
                'visual_suggestion' => 'A clean Facebook scene.',
            ]), 200),
            'api.pexels.com/*' => Http::response(['photos' => [], 'videos' => []], 200),
        ]);

        $this->post('/generate', [
            'topic' => 'Correct this post text please.',
        ])
            ->assertOk()
            ->assertSee('لم يعثر Pexels على صورة مناسبة')
            ->assertSee('لم يعثر Pexels على فيديو مناسب')
            ->assertDontSee('generated-images/', false);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.pexels.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'image.pollinations.ai'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'commons.wikimedia.org'));
    }

    public function test_it_shows_message_when_pexels_times_out(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');
        Config::set('services.pexels.key', 'pexels-test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'Timeout',
                'corrected_news' => 'Corrected post text.',
                'activity_file_copy' => 'Corrected activity file text.',
                'hashtags' => ['#Fallback'],
                'visual_suggestion' => 'A clean Facebook scene.',
            ]), 200),
            'api.pexels.com/*' => fn () => throw new ConnectionException('Timed out'),
        ]);

        $this->post('/generate', [
            'topic' => 'Correct this post text please.',
        ])
            ->assertOk()
            ->assertSee('لم يعثر Pexels على صورة مناسبة')
            ->assertSee('لم يعثر Pexels على فيديو مناسب')
            ->assertDontSee('generated-images/', false);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'image.pollinations.ai'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'commons.wikimedia.org'));
    }

    public function test_post_body_uses_corrected_text_not_original_text(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'عنوان مصحح',
                'corrected_news' => 'زوجتي جميلة.',
                'activity_file_copy' => 'زوجتي جميلة.',
                'hashtags' => ['#العائلة'],
                'visual_suggestion' => 'مشهد زوجين سعيدين.',
            ]), 200),
        ]);

        $this->post('/generate', [
            'topic' => 'زوجتي عمار جميلة',
        ])
            ->assertOk()
            ->assertSee('<p>زوجتي جميلة.</p>', false)
            ->assertDontSee('<p>زوجتي عمار جميلة</p>', false);
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

    private function modelPayload(array $payload): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode(array_merge([
                                    'title' => 'Corrected text with suggested outputs',
                                    'activity_file_copy' => 'Activity file copy.',
                                ], $payload), JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function pexelsPayload(string $imageUrl, string $photographer): array
    {
        return [
            'photos' => [
                [
                    'url' => 'https://www.pexels.com/photo/example',
                    'photographer' => $photographer,
                    'src' => [
                        'large2x' => $imageUrl,
                        'large' => $imageUrl,
                    ],
                ],
            ],
        ];
    }

    private function pexelsVideoPayload(string $videoUrl, string $posterUrl, string $creator): array
    {
        return [
            'videos' => [
                [
                    'url' => 'https://www.pexels.com/video/example',
                    'image' => $posterUrl,
                    'user' => [
                        'name' => $creator,
                    ],
                    'video_files' => [
                        [
                            'link' => 'https://videos.pexels.com/video-files/example/square.mp4',
                            'file_type' => 'video/mp4',
                            'quality' => 'sd',
                            'width' => 720,
                            'height' => 720,
                        ],
                        [
                            'link' => $videoUrl,
                            'file_type' => 'video/mp4',
                            'quality' => 'hd',
                            'width' => 720,
                            'height' => 1280,
                        ],
                    ],
                ],
            ],
        ];
    }

}
