<?php

namespace Database\Factories;

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationStatusEnum;
use App\Models\Communication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Communication>
 */
class CommunicationFactory extends Factory
{
    protected $model = Communication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $channel = $this->faker->randomElement(CommunicationChannelEnum::cases());

        return [
            'recipient' => match ($channel) {
                CommunicationChannelEnum::Email => $this->faker->safeEmail(),
                CommunicationChannelEnum::Sms => '+55119'.$this->faker->numerify('########'),
                CommunicationChannelEnum::Push => $this->faker->uuid(),
            },
            'channel' => $channel,
            'subject' => $channel === CommunicationChannelEnum::Email ? $this->faker->sentence(4) : null,
            'message' => $this->faker->sentence(),
            'origin_system' => $this->faker->slug(2),
            'notification_template_id' => null,
            'variables' => null,
            'status' => CommunicationStatusEnum::Pending,
            'attempts' => 0,
        ];
    }

    public function email(): static
    {
        return $this->state(fn (): array => [
            'channel' => CommunicationChannelEnum::Email,
            'recipient' => $this->faker->safeEmail(),
            'subject' => 'Bem-vindo',
        ]);
    }

    public function sms(): static
    {
        return $this->state(fn (): array => [
            'channel' => CommunicationChannelEnum::Sms,
            'recipient' => '+5511999999999',
            'subject' => null,
        ]);
    }

    public function push(): static
    {
        return $this->state(fn (): array => [
            'channel' => CommunicationChannelEnum::Push,
            'recipient' => 'device-token-abc',
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => CommunicationStatusEnum::Sent,
            'processed_at' => now(),
            'sent_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function failed(string $reason = 'erro'): static
    {
        return $this->state(fn (): array => [
            'status' => CommunicationStatusEnum::Failed,
            'processed_at' => now(),
            'failure_reason' => $reason,
            'attempts' => 1,
        ]);
    }
}
