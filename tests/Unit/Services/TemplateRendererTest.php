<?php

namespace Tests\Unit\Services;

use App\Enums\CommunicationChannelEnum;
use App\Exceptions\MissingTemplateVariableException;
use App\Models\NotificationTemplate;
use App\Services\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    public function test_replaces_placeholders_in_subject_and_body(): void
    {
        $template = new NotificationTemplate;
        $template->subject = 'Olá {{nome}}';
        $template->body = 'Sua conta no {{empresa}} foi criada.';
        $template->channel = CommunicationChannelEnum::Email;

        $rendered = (new TemplateRenderer)->render($template, [
            'nome' => 'Victor',
            'empresa' => 'Acme',
        ]);

        $this->assertSame('Olá Victor', $rendered['subject']);
        $this->assertSame('Sua conta no Acme foi criada.', $rendered['body']);
    }

    public function test_returns_null_subject_when_template_has_none(): void
    {
        $template = new NotificationTemplate;
        $template->subject = null;
        $template->body = 'Seu código é {{codigo}}.';
        $template->channel = CommunicationChannelEnum::Sms;

        $rendered = (new TemplateRenderer)->render($template, ['codigo' => '123']);

        $this->assertNull($rendered['subject']);
        $this->assertSame('Seu código é 123.', $rendered['body']);
    }

    public function test_throws_when_variable_is_missing(): void
    {
        $template = new NotificationTemplate;
        $template->subject = null;
        $template->body = 'Olá {{nome}}';
        $template->channel = CommunicationChannelEnum::Sms;

        $this->expectException(MissingTemplateVariableException::class);

        (new TemplateRenderer)->render($template, []);
    }

    public function test_serializes_non_scalar_values(): void
    {
        $template = new NotificationTemplate;
        $template->subject = null;
        $template->body = 'Dados: {{payload}}';
        $template->channel = CommunicationChannelEnum::Push;

        $rendered = (new TemplateRenderer)->render($template, [
            'payload' => ['a' => 1],
        ]);

        $this->assertSame('Dados: {"a":1}', $rendered['body']);
    }
}
