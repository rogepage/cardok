<?php

namespace App\Services;

use App\Data\MockVehicleDebtData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SoapDebtService
{
    public function extractPlate(Request $request): string
    {
        $content = $request->getContent();

        if (!empty($content) && preg_match('/<plate>(.*?)<\/plate>/is', $content, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        if ($request->has('plate')) {
            return strtoupper(trim((string) $request->input('plate')));
        }

        return '';
    }

    public function handleRequest(Request $request, ?string $mode = null): Response
    {
        $mode = $mode ?? config('services.provider_mode') ?? env('PROVIDER_MODE', 'success');
        $plate = $this->extractPlate($request);

        return match ($mode) {
            'error' => response(
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error>Simulated external provider error</error>",
                500,
                ['Content-Type' => 'application/xml; charset=utf-8']
            ),

            'timeout' => $this->handleTimeout($plate),

            'invalid_response' => response(
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<invalid><malformed>true</malformed></invalid>",
                200,
                ['Content-Type' => 'application/xml; charset=utf-8']
            ),

            default => $this->buildSuccessResponse($plate),
        };
    }

    private function handleTimeout(string $plate): Response
    {
        sleep(5);

        return $this->buildSuccessResponse($plate);
    }

    public function buildSuccessResponse(string $plate): Response
    {
        $debts = MockVehicleDebtData::findByPlate($plate);

        if (empty($debts)) {
            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<response>\n"
                . "    <plate>{$plate}</plate>\n"
                . "    <debts/>\n"
                . "</response>";
        } else {
            $debtItemsXml = [];
            foreach ($debts as $debt) {
                $debtItemsXml[] = "        <debt>\n"
                    . "            <category>{$debt['category']}</category>\n"
                    . "            <value>{$debt['value']}</value>\n"
                    . "            <expiration>{$debt['expiration']}</expiration>\n"
                    . "        </debt>";
            }

            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<response>\n"
                . "    <plate>{$plate}</plate>\n"
                . "    <debts>\n"
                . implode("\n", $debtItemsXml) . "\n"
                . "    </debts>\n"
                . "</response>";
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}
