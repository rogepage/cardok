<?php

namespace App\Http\Controllers;

use App\Services\SoapDebtService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SoapDebtController extends Controller
{
    public function __construct(
        private readonly SoapDebtService $soapDebtService
    ) {}

    public function handle(Request $request): Response
    {
        return $this->soapDebtService->handleRequest($request);
    }
}
