<?php

namespace Database\Factories;

use App\Enums\CommunicationChannelEnum;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->sentence(3);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->randomNumber(5),
            'channel' => $this->faker->randomElement(CommunicationChannelEnum::cases()),
            'subject' => $this->faker->sentence(4),
            'body' => 'Olá {{nome}}, sua conta foi atualizada.',
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function email(): static
    {
        return $this->state(fn (): array => [
            'channel' => CommunicationChannelEnum::Email,
            'subject' => 'Bem-vindo, {{nome}}',
            'body' => 'Olá {{nome}}, seja bem-vindo ao {{empresa}}.',
        ]);
    }

    public function sms(): static
    {
        return $this->state(fn (): array => [
            'channel' => CommunicationChannelEnum::Sms,
            'subject' => null,
            'body' => 'Seu código de verificação é {{codigo}}.',
        ]);
    }

    public function push(): static
    {
        return $this->state(fn (): array => [
            'channel' => CommunicationChannelEnum::Push,
            'subject' => 'Notificação',
            'body' => '{{titulo}}: {{mensagem}}',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
