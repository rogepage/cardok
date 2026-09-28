<div class="space-y-8">
    <!-- Form Card -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80">
        <div class="text-center max-w-xl mx-auto space-y-2">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">
                Consulta de Débitos Veiculares
            </h1>
            <p class="text-sm sm:text-base text-slate-500">
                Informe a placa do veículo para consultar seus débitos, simulações de PIX e parcelamento.
            </p>
        </div>

        <form wire:submit.prevent="consultar" class="mt-8 max-w-md mx-auto space-y-4">
            <div>
                <label for="placa" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">
                    Placa do Veículo
                </label>
                <div class="relative">
                    <input 
                        type="text" 
                        id="placa" 
                        wire:model="placa"
                        wire:loading.attr="disabled"
                        maxlength="7"
                        placeholder="ABC1234"
                        class="w-full text-center tracking-widest text-2xl font-mono uppercase font-bold px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition disabled:bg-slate-100 disabled:cursor-not-allowed placeholder:text-slate-300"
                    />
                </div>
                <p class="mt-1 text-center text-xs text-slate-400">
                    Padrão tradicional (ABC1234) ou Mercosul (ABC1D23)
                </p>
            </div>

            <!-- Error Banner -->
            @if ($erro)
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-3">
                    <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-medium">{{ $erro }}</p>
                    </div>
                </div>
            @endif

            <div class="pt-2">
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove>Consultar débitos</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Consultando...
                    </span>
                </button>
            </div>
        </form>
    </div>

    <!-- Zero Debits State -->
    @if ($resultado && count($resultado['debitos']) === 0)
        <div class="bg-white rounded-2xl p-8 sm:p-12 shadow-sm border border-emerald-200 text-center space-y-4">
            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="space-y-1">
                <h3 class="text-xl font-bold text-slate-900">Nenhum débito encontrado.</h3>
                <p class="text-slate-500 text-sm sm:text-base">
                    Seu veículo está sem débitos disponíveis para consulta.
                </p>
            </div>
            <div class="pt-4">
                <button 
                    type="button" 
                    wire:click="limpar" 
                    class="inline-flex items-center text-sm font-semibold text-brand-600 hover:text-brand-700"
                >
                    &larr; Realizar nova consulta
                </button>
            </div>
        </div>
    @endif

    <!-- Results Section -->
    @if ($resultado && count($resultado['debitos']) > 0)
        <div class="space-y-8">
            <!-- Header Result -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80">
                <div>
                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-400">Resultado da Consulta</span>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-2xl font-mono font-bold text-slate-900">{{ $resultado['placa'] }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                            {{ count($resultado['debitos']) }} {{ count($resultado['debitos']) === 1 ? 'débito' : 'débitos' }}
                        </span>
                    </div>
                </div>
                <button 
                    type="button" 
                    wire:click="limpar" 
                    class="text-xs font-semibold text-slate-500 hover:text-slate-700 border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-50 transition"
                >
                    Nova consulta
                </button>
            </div>

            <!-- Débitos Cards -->
            <div class="space-y-4">
                <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <span>Débitos do Veículo</span>
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($resultado['debitos'] as $debito)
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 flex flex-col justify-between hover:border-slate-300 transition">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wide {{ $debito['tipo'] === 'IPVA' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                        {{ $debito['tipo'] }}
                                    </span>
                                    <span class="text-xs text-rose-600 font-semibold flex items-center gap-1">
                                        {{ $debito['dias_atraso'] }} dias em atraso
                                    </span>
                                </div>

                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between text-slate-500">
                                        <span>Valor Original</span>
                                        <span class="font-medium text-slate-700">R$ {{ number_format((float) $debito['valor_original'], 2, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between text-slate-500">
                                        <span>Vencimento</span>
                                        <span class="font-medium text-slate-700">{{ \Carbon\Carbon::parse($debito['vencimento'])->format('d/m/Y') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100 flex justify-between items-baseline">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Valor Atualizado</span>
                                <span class="text-xl font-bold text-slate-900">R$ {{ number_format((float) $debito['valor_atualizado'], 2, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Resumo Financeiro -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl p-6 sm:p-8 text-white shadow-md">
                <span class="text-xs uppercase tracking-wider font-semibold text-slate-400">Resumo Consolidado</span>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <span class="text-xs text-slate-400 block">Total Original</span>
                        <span class="text-xl sm:text-2xl font-semibold text-slate-300">
                            R$ {{ number_format((float) $resultado['resumo']['total_original'], 2, ',', '.') }}
                        </span>
                    </div>
                    <div class="sm:text-right border-t sm:border-t-0 border-slate-700 pt-4 sm:pt-0">
                        <span class="text-xs text-slate-400 block">Total Atualizado (com juros)</span>
                        <span class="text-2xl sm:text-3xl font-extrabold text-emerald-400">
                            R$ {{ number_format((float) $resultado['resumo']['total_atualizado'], 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Opções de Pagamento -->
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Opções de Pagamento</h2>
                    <p class="text-xs text-slate-500">Simulação de pagamento total ou parcial por modalidade de débito.</p>
                </div>

                <div class="space-y-6">
                    @foreach ($resultado['pagamentos']['opcoes'] as $opcao)
                        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border {{ $opcao['tipo'] === 'TOTAL' ? 'border-brand-300 ring-1 ring-brand-100' : 'border-slate-200' }}">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-100 gap-2">
                                <div>
                                    <span class="text-xs uppercase tracking-wider font-bold {{ $opcao['tipo'] === 'TOTAL' ? 'text-brand-600' : 'text-slate-500' }}">
                                        {{ str_replace('_', ' ', $opcao['tipo']) }}
                                    </span>
                                    <h3 class="text-xl font-bold text-slate-900 mt-0.5">
                                        {{ $opcao['tipo'] === 'TOTAL' ? 'Pagamento Total dos Débitos' : 'Pagamento Parcial — ' . str_replace('SOMENTE_', '', $opcao['tipo']) }}
                                    </h3>
                                </div>
                                <div class="sm:text-right">
                                    <span class="text-xs text-slate-400 block">Valor Base</span>
                                    <span class="text-xl font-extrabold text-slate-900">
                                        R$ {{ number_format((float) $opcao['valor_base'], 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- PIX -->
                                <div class="rounded-xl p-5 bg-emerald-50/60 border border-emerald-200/80 flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="font-bold text-emerald-900 flex items-center gap-1.5 text-sm">
                                                <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                </svg>
                                                PIX à Vista
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                                5% OFF
                                            </span>
                                        </div>
                                        <p class="text-xs text-emerald-700/80 mb-4">Economize com o desconto aplicado no valor base.</p>
                                    </div>
                                    <div class="pt-2 border-t border-emerald-100">
                                        <span class="text-xs text-emerald-800/80 block">Total com desconto</span>
                                        <span class="text-2xl font-extrabold text-emerald-700">
                                            R$ {{ number_format((float) $opcao['pix']['total_com_desconto'], 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Cartão de Crédito -->
                                <div class="rounded-xl p-5 bg-slate-50 border border-slate-200 flex flex-col justify-between">
                                    <div>
                                        <span class="font-bold text-slate-800 flex items-center gap-1.5 text-sm mb-2">
                                            <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <rect x="2" y="5" width="20" height="14" rx="2"/>
                                                <line x1="2" y1="10" x2="22" y2="10"/>
                                            </svg>
                                            Cartão de Crédito
                                        </span>
                                        <p class="text-xs text-slate-500 mb-4">Parcelamento em até 12x via Tabela Price (2,5% a.m.).</p>
                                    </div>

                                    <div class="space-y-2 text-sm">
                                        @foreach ($opcao['cartao_credito']['parcelas'] as $parcela)
                                            <div class="flex justify-between items-center py-1.5 border-b border-slate-200/60 last:border-0">
                                                <span class="text-slate-600 font-medium">
                                                    {{ $parcela['quantidade'] }}x
                                                    @if ($parcela['quantidade'] === 1)
                                                        <span class="text-xs text-slate-400 font-normal">(à vista sem juros)</span>
                                                    @endif
                                                </span>
                                                <span class="font-bold text-slate-900">
                                                    R$ {{ number_format((float) $parcela['valor_parcela'], 2, ',', '.') }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
