<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckServicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cardok:check-services';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica a conectividade e resolução de nomes dos serviços externos (REST, SOAP, Payment)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Cardok Core Status: SAUDÁVEL (Aplicação operacional)');
        $this->line('Verificando conectividade e disponibilidade das dependências externas...');

        $services = [
            'provider-rest' => [
                'name' => 'Provider REST',
                'url' => config('services.providers.rest_url') . '/api/health',
            ],
            'provider-soap' => [
                'name' => 'Provider SOAP',
                'url' => config('services.providers.soap_url') . '/health',
            ],
            'payment-provider' => [
                'name' => 'Payment Provider',
                'url' => config('services.payment.url') . '/health',
            ],
        ];

        $rows = [];
        $hasFailure = false;
        $timeout = (int) config('services.providers.timeout', 2);

        foreach ($services as $serviceKey => $service) {
            $url = $service['url'];
            $start = microtime(true);

            try {
                $response = Http::timeout($timeout)->get($url);
                $elapsed = round((microtime(true) - $start) * 1000, 2);

                if ($response->successful()) {
                    $rows[] = [
                        $service['name'],
                        $url,
                        '<fg=green>ONLINE</>',
                        $response->status(),
                        "{$elapsed}ms",
                        json_encode($response->json(), JSON_UNESCAPED_SLASHES),
                    ];
                } else {
                    $hasFailure = true;
                    $rows[] = [
                        $service['name'],
                        $url,
                        '<fg=red>FAIL</>',
                        $response->status(),
                        "{$elapsed}ms",
                        $response->body(),
                    ];
                }
            } catch (\Throwable $e) {
                $hasFailure = true;
                $elapsed = round((microtime(true) - $start) * 1000, 2);
                $rows[] = [
                    $service['name'],
                    $url,
                    '<fg=red>UNREACHABLE</>',
                    '0',
                    "{$elapsed}ms",
                    $e->getMessage(),
                ];
            }
        }

        $this->table(
            ['Serviço', 'URL Alvo', 'Status', 'HTTP Code', 'Latência', 'Resposta'],
            $rows
        );

        if ($hasFailure) {
            $this->warn('Cardok permanece saudável, mas uma ou mais dependências externas estão inacessíveis ou instáveis.');
            return Command::FAILURE;
        }

        $this->info('Todos os serviços externos estão acessíveis e saudáveis!');
        return Command::SUCCESS;
    }
}
