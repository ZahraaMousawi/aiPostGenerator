<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostAgentTest extends TestCase
{
    private array $existingGeneratedImages = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pexels.key', null);
        $this->existingGeneratedImages = $this->generatedImageFileNames();
    }

    protected function tearDown(): void
    {
        $this->deleteNewGeneratedFiles($this->existingGeneratedImages);

        parent::tearDown();
    }

    public function test_it_renders_outputs_with_a_real_image_result(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');
        $existingImages = $this->generatedImageFileNames();

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'AI Training Initiative',
                'corrected_news' => 'The national AI center launched a specialized training initiative for health data analysis.',
                'activity_file_copy' => 'A specialized training initiative for health data analysis was launched.',
                'hashtags' => ['#AI', '#DataAnalysis', '#HealthData'],
                'visual_suggestion' => 'People learning from data charts.',
            ]), 200),
            'image.pollinations.ai/*' => Http::response('fake-jpg', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'commons.wikimedia.org/*' => Http::response($this->commonsPayload('https://upload.wikimedia.org/example/photo.jpg'), 200),
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
            ->assertSee('generated-images/', false)
            ->assertSee('الصورة: Pollinations AI')
            ->assertDontSee('Gemini');

        Http::assertSent(function ($request) {
            $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text', '');

            return str_contains($request->url(), 'generativelanguage.googleapis.com')
                && ! str_contains($prompt, 'image_search_query')
                && str_contains($prompt, 'لا تضف أي معلومة جديدة')
                && str_contains($prompt, 'لا تذكر "المركز"');
        });
        Http::assertSent(fn ($request) => str_contains($request->url(), 'image.pollinations.ai')
            && str_contains(urldecode($request->url()), 'real unedited photo')
            && str_contains(urldecode($request->url()), 'model=flux')
            && str_contains(urldecode($request->url()), 'enhance=true')
            && str_contains(urldecode($request->url()), 'The national AI center launched a specialized training initiative'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'commons.wikimedia.org')
            && str_contains(urldecode($request->url()), 'real people using laptops in technology workshop')
            && str_contains(urldecode($request->url()), 'people training workshop')
            && str_contains(urldecode($request->url()), 'data analysis charts'));

        $this->deleteNewGeneratedFiles($existingImages);
    }

    public function test_it_falls_back_to_pexels_photo_when_pollinations_fails(): void
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
            'image.pollinations.ai/*' => Http::response('temporary failure', 503, [
                'Content-Type' => 'text/plain',
            ]),
            'api.pexels.com/*' => Http::response($this->pexelsPayload(
                'https://images.pexels.com/photos/example/family-graduation.jpeg',
                'Jane Doe'
            ), 200),
            'commons.wikimedia.org/*' => Http::response($this->commonsPayload('https://upload.wikimedia.org/example/photo.jpg'), 200),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'A family celebrate a graduation ceremony together.',
        ]);

        $response
            ->assertOk()
            ->assertSee('https://images.pexels.com/photos/example/family-graduation.jpeg', false)
            ->assertSee('الصورة: Jane Doe / Pexels')
            ->assertDontSee('https://upload.wikimedia.org/example/photo.jpg', false);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.pexels.com')
            && str_contains(urldecode($request->url()), 'query=happy family students graduation ceremony'));
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

    public function test_it_falls_back_to_local_design_when_real_image_search_has_no_results(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');
        $existingImages = $this->generatedImageFileNames();

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'Local Design Fallback',
                'corrected_news' => 'Corrected post text.',
                'activity_file_copy' => 'Corrected activity file text.',
                'hashtags' => ['#Fallback', '#Design'],
                'visual_suggestion' => 'A clean Facebook scene.',
            ]), 200),
            'image.pollinations.ai/*' => Http::response('temporary failure', 503, [
                'Content-Type' => 'text/plain',
            ]),
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]], 200),
        ]);

        $response = $this->post('/generate', [
            'topic' => 'Correct this post text please.',
        ]);

        $response
            ->assertOk()
            ->assertSee('generated-images/', false)
            ->assertDontSee('Gemini');

        $this->deleteNewGeneratedFiles($existingImages);
    }

    public function test_it_falls_back_to_local_design_when_real_image_search_times_out(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');
        $existingImages = $this->generatedImageFileNames();

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'Timeout Fallback',
                'corrected_news' => 'Corrected post text.',
                'activity_file_copy' => 'Corrected activity file text.',
                'hashtags' => ['#Fallback'],
                'visual_suggestion' => 'A clean Facebook scene.',
            ]), 200),
            'image.pollinations.ai/*' => fn () => throw new ConnectionException('Timed out'),
            'commons.wikimedia.org/*' => fn () => throw new ConnectionException('Timed out'),
        ]);

        $this->post('/generate', [
            'topic' => 'Correct this post text please.',
        ])
            ->assertOk()
            ->assertSee('generated-images/', false);

        $this->deleteNewGeneratedFiles($existingImages);
    }

    public function test_post_body_uses_corrected_text_not_original_text(): void
    {
        Config::set('services.gemini.key', 'gemini-test-key');
        Config::set('services.gemini.model', 'gemini-3.5-flash');
        $existingImages = $this->generatedImageFileNames();

        Http::fake([
            'generativelanguage.googleapis.com/*gemini-3.5-flash*' => Http::response($this->modelPayload([
                'suggested_title' => 'عنوان مصحح',
                'corrected_news' => 'زوجتي جميلة.',
                'activity_file_copy' => 'زوجتي جميلة.',
                'hashtags' => ['#العائلة'],
                'visual_suggestion' => 'مشهد زوجين سعيدين.',
            ]), 200),
            'image.pollinations.ai/*' => Http::response('temporary failure', 503, [
                'Content-Type' => 'text/plain',
            ]),
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]], 200),
        ]);

        $this->post('/generate', [
            'topic' => 'زوجتي عمار جميلة',
        ])
            ->assertOk()
            ->assertSee('<p>زوجتي جميلة.</p>', false)
            ->assertDontSee('<p>زوجتي عمار جميلة</p>', false);

        $this->deleteNewGeneratedFiles($existingImages);
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

    private function commonsPayload(string $imageUrl): array
    {
        return [
            'query' => [
                'pages' => [
                    10 => [
                        'title' => 'File:Example photo.jpg',
                        'imageinfo' => [
                            [
                                'url' => $imageUrl,
                                'mime' => 'image/jpeg',
                                'width' => 1200,
                                'height' => 800,
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

    private function generatedImageFileNames(): array
    {
        if (! File::isDirectory(public_path('generated-images'))) {
            return [];
        }

        return collect(File::files(public_path('generated-images')))
            ->map(fn ($file) => $file->getFilename())
            ->all();
    }

    private function deleteNewGeneratedFiles(array $existingImages): void
    {
        collect(File::files(public_path('generated-images')))
            ->reject(fn ($file) => in_array($file->getFilename(), $existingImages, true))
            ->each(fn ($file) => File::delete($file->getPathname()));
    }
}
