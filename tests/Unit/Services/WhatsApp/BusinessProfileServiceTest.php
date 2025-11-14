<?php

namespace Tests\Unit\Services\WhatsApp;

use Tests\TestCase;
use App\Services\WhatsApp\BusinessProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\workspace;

class BusinessProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private BusinessProfileService $service;
    private workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = workspace::factory()->create([
            'meta_token' => 'test_token',
            'meta_version' => 'v18.0',
            'meta_app_id' => 'test_app_id',
            'meta_phone_number_id' => 'test_phone_id',
            'meta_waba_id' => 'test_waba_id'
        ]);

        $this->service = new BusinessProfileService(
            $this->workspace->meta_token,
            $this->workspace->meta_version,
            $this->workspace->meta_app_id,
            $this->workspace->meta_phone_number_id,
            $this->workspace->meta_waba_id,
            $this->workspace->id
        );
    }

    /** @test */
    public function it_can_be_instantiated_with_workspace_credentials()
    {
        $this->assertInstanceOf(BusinessProfileService::class, $this->service);
        $this->assertEquals('test_token', $this->service->getAccessToken());
        $this->assertEquals('v18.0', $this->service->getApiVersion());
    }

    /** @test */
    public function it_updates_business_profile_successfully()
    {
        $profileData = [
            'about' => 'Test Business Description',
            'address' => '123 Test Street, Test City',
            'description' => 'We provide excellent services',
            'email' => 'test@business.com',
            'websites' => ['https://example.com'],
            'vertical' => 'PROFESSIONAL_SERVICES'
        ];

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    'about' => 'Test Business Description',
                    'address' => '123 Test Street, Test City',
                    'description' => 'We provide excellent services',
                    'email' => 'test@business.com',
                    'websites' => ['https://example.com'],
                    'vertical' => 'PROFESSIONAL_SERVICES'
                ]
            ])
        ]);

        $result = $this->service->updateBusinessProfile($profileData);

        $this->assertTrue($result->success);
        $this->assertEquals('Test Business Description', $result->data['about']);
        $this->assertEquals('test@business.com', $result->data['email']);

        Http::assertSent(function ($request) use ($profileData) {
            $body = json_decode($request->body(), true);

            return str_contains($request->url(), 'whatsapp_business_profile') &&
                   $body['about'] === $profileData['about'] &&
                   $body['email'] === $profileData['email'];
        });
    }

    /** @test */
    public function it_validates_email_format()
    {
        $invalidEmails = ['invalid-email', 'test@', '@domain.com', 'test.space@domain.com'];

        foreach ($invalidEmails as $email) {
            $profileData = [
                'about' => 'Test Business',
                'email' => $email
            ];

            $result = $this->service->updateBusinessProfile($profileData);
            $this->assertFalse($result->success, "Email '{$email}' should be invalid");
            $this->assertStringContains('Invalid email format', $result->error);
        }
    }

    /** @test */
    public function it_validates_website_urls()
    {
        $invalidUrls = ['not-a-url', 'ftp://example.com', 'http://invalid'];

        foreach ($invalidUrls as $url) {
            $profileData = [
                'about' => 'Test Business',
                'websites' => [$url]
            ];

            $result = $this->service->updateBusinessProfile($profileData);
            $this->assertFalse($result->success, "URL '{$url}' should be invalid");
        }
    }

    /** @test */
    public function it_gets_business_profile_information()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    'about' => 'Current Business Description',
                    'address' => '456 Current Street',
                    'description' => 'Current business description',
                    'email' => 'current@business.com',
                    'websites' => ['https://current-site.com'],
                    'vertical' => 'RETAIL'
                ]
            ])
        ]);

        $result = $this->service->getBusinessProfile();

        $this->assertTrue($result->success);
        $this->assertEquals('Current Business Description', $result->data['about']);
        $this->assertEquals('RETAIL', $result->data['vertical']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'whatsapp_business_profile') &&
                   $request->method() === 'GET';
        });
    }

    /** @test */
    public function it_handles_profile_update_errors()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid parameter',
                    'type' => 'OAuthException',
                    'error_data' => [
                        'messaging_product' => 'whatsapp',
                        'details' => 'Invalid email address format'
                    ]
                ]
            ], 400)
        ]);

        $profileData = [
            'about' => 'Test Business',
            'email' => 'invalid-email'
        ];

        $result = $this->service->updateBusinessProfile($profileData);

        $this->assertFalse($result->success);
        $this->assertStringContains('Invalid parameter', $result->error);
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }
}