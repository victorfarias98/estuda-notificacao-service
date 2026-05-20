<?php

namespace Database\Seeders;

use App\Enums\CommunicationChannelEnum;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Boas-vindas (e-mail)',
                'slug' => 'boas-vindas',
                'channel' => CommunicationChannelEnum::Email,
                'subject' => 'Bem-vindo, {{nome}}',
                'body' => "Olá {{nome}},\n\nSua conta no {{empresa}} foi criada com sucesso.\n\nAbraços!",
                'description' => 'Template de boas-vindas para novos usuários por e-mail.',
                'is_active' => true,
            ],
            [
                'name' => 'Código OTP (SMS)',
                'slug' => 'codigo-otp',
                'channel' => CommunicationChannelEnum::Sms,
                'subject' => null,
                'body' => 'Seu codigo de verificacao e {{codigo}}. Valido por 5 minutos.',
                'description' => 'Envio de código OTP via SMS.',
                'is_active' => true,
            ],
            [
                'name' => 'Promoção (Push)',
                'slug' => 'promo-novidades',
                'channel' => CommunicationChannelEnum::Push,
                'subject' => 'Novidade para você',
                'body' => '{{titulo}}: {{mensagem}}',
                'description' => 'Push de promoções e novidades.',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::query()->updateOrCreate(
                ['slug' => $template['slug'], 'channel' => $template['channel']],
                $template,
            );
        }
    }
}
