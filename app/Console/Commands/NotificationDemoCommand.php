<?php

namespace App\Console\Commands;

use App\Models\NotificationTemplate;
use App\Services\CommunicationService;
use Illuminate\Console\Command;

class NotificationDemoCommand extends Command
{
    protected $signature = 'notification:demo
                            {--origin=cli-demo : Sistema de origem a registrar nas comunicacoes}
                            {--email=demo@example.com : Destinatario do e-mail de demonstracao}
                            {--phone=+5511999999999 : Destinatario do SMS de demonstracao}
                            {--token=device-token-demo : Device token para o push de demonstracao}';

    protected $description = 'Dispara uma comunicacao de demonstracao em cada canal (email, sms, push) para visualizar o processamento.';

    public function handle(CommunicationService $service): int
    {
        $this->info('Disparando comunicacoes de demonstracao...');

        $email = $service->createAndDispatch([
            'recipient' => $this->option('email'),
            'channel' => 'email',
            'subject' => 'Demonstracao Notification Service',
            'message' => 'Esta mensagem foi disparada pelo comando notification:demo.',
            'origin_system' => $this->option('origin'),
        ]);

        $smsTemplate = NotificationTemplate::query()
            ->where('slug', 'codigo-otp')
            ->where('channel', 'sms')
            ->first();

        $sms = $service->createAndDispatch(
            payload: [
                'recipient' => $this->option('phone'),
                'channel' => 'sms',
                'origin_system' => $this->option('origin'),
                'variables' => ['codigo' => (string) random_int(100000, 999999)],
            ],
            template: $smsTemplate,
        );

        $pushTemplate = NotificationTemplate::query()
            ->where('slug', 'promo-novidades')
            ->where('channel', 'push')
            ->first();

        $push = $service->createAndDispatch(
            payload: [
                'recipient' => $this->option('token'),
                'channel' => 'push',
                'origin_system' => $this->option('origin'),
                'variables' => [
                    'titulo' => 'Novidade',
                    'mensagem' => 'Confira nosso novo recurso!',
                ],
            ],
            template: $pushTemplate,
        );

        $this->table(
            headers: ['Canal', 'ID', 'Destinatario', 'Status'],
            rows: [
                ['email', $email->id, $email->recipient, $email->status->value],
                ['sms', $sms->id, $sms->recipient, $sms->status->value],
                ['push', $push->id, $push->recipient, $push->status->value],
            ],
        );

        $this->newLine();
        $this->line('Acompanhe o processamento com:');
        $this->line('  - <info>docker compose logs -f queue</info> (ou make logs)');
        $this->line('  - <info>GET /api/communications/{id}</info> para o detalhe + logs');
        $this->line('  - <info>http://localhost:8026</info> (Mailhog) para o e-mail enviado');

        return self::SUCCESS;
    }
}
