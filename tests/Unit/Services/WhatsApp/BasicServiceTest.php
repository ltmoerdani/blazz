<?php

namespace Tests\Unit\Services\WhatsApp;

use Tests\TestCase;

class BasicServiceTest extends TestCase
{
    /** @test */
    public function it_validates_service_classes_exist()
    {
        $this->assertTrue(class_exists('App\Services\WhatsApp\MessageSendingService'));
        $this->assertTrue(class_exists('App\Services\WhatsApp\TemplateManagementService'));
        $this->assertTrue(class_exists('App\Services\WhatsApp\BusinessProfileService'));
        $this->assertTrue(class_exists('App\Services\WhatsApp\MediaProcessingService'));
        $this->assertTrue(class_exists('App\Services\WhatsApp\WhatsAppHealthService'));
    }

    /** @test */
    public function it_validates_helper_class_exists()
    {
        $this->assertTrue(class_exists('App\Helpers\WorkspaceHelper'));
    }

    /** @test */
    public function it_validates_service_provider_classes_exist()
    {
        $this->assertTrue(class_exists('App\Providers\WhatsAppServiceProvider'));
        $this->assertTrue(class_exists('App\Providers\BusinessServiceProvider'));
        $this->assertTrue(class_exists('App\Providers\AppServiceProvider'));
        $this->assertTrue(class_exists('App\Providers\UtilityServiceProvider'));
    }
}