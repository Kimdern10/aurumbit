@php
    use App\Enums\PlanCategory;
    use App\Support\Btc;

    $btcPlans = $plans->where('category', PlanCategory::Bitcoin);
    $goldPlans = $plans->where('category', PlanCategory::Gold);

    $faqs = [
        ['q' => 'Is this a real investment platform?', 'a' => 'No. This is a demo. No Bitcoin is received, held or sent. Every balance, deposit, earning and withdrawal is simulated so you can see how the platform works.'],
        ['q' => 'Are returns guaranteed?', 'a' => 'No. Each plan shows an indicative target used for the demo simulation. Real mining income depends on prices, network difficulty, energy costs and operations, and can be lower than the target or nothing at all.'],
        ['q' => 'How do withdrawals work?', 'a' => 'You request a withdrawal to your own Bitcoin address. We check the address format and checksum, hold the amount from your balance, and an admin reviews it. In this demo nothing is sent on-chain.'],
        ['q' => 'Why do you ask for identity documents?', 'a' => 'Know-your-customer (KYC) checks help prevent fraud and money laundering. Your documents are stored privately and only reviewed by an admin.'],
        ['q' => 'How are gold plans priced?', 'a' => 'Gold plans are funded in BTC. When you start a plan, we show the gold equivalent at that moment using the live PAX Gold price (one token is backed by one troy ounce of gold).'],
        ['q' => 'Can I lose money?', 'a' => 'With real crypto and commodity investments, yes, you can lose some or all of your money. Please read the risk disclosure before investing anywhere.'],
    ];
@endphp

<x-layouts::site>
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-navy-950 text-white" data-prices-url="{{ route('prices') }}">
        <canvas data-hero-canvas class="pointer-events-none absolute inset-0 -z-10 size-full opacity-70" aria-hidden="true"></canvas>
        <div class="pointer-events-none absolute -top-40 -right-32 -z-10 size-[34rem] rounded-full bg-gold-400/20 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-48 -left-40 -z-10 size-[30rem] rounded-full bg-navy-500/40 blur-3xl" aria-hidden="true"></div>

        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 pt-14 pb-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pt-24 lg:pb-28">
            <div>
                <p data-reveal class="inline-flex items-center gap-2 rounded-full border border-gold-300/30 bg-white/5 px-3 py-1 text-xs font-medium text-gold-200">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-gold-300 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-gold-400"></span></span>
                    {{ __('Bitcoin & gold mining plans · Demo mode') }}
                </p>

                <h1 data-reveal style="--reveal-delay: 80ms" class="mt-6 font-display text-4xl leading-[1.05] font-bold sm:text-5xl lg:text-6xl">
                    {{ __('Mining-backed plans,') }}
                    <span class="text-gold-gradient animate-shine">{{ __('every term in plain sight.') }}</span>
                </h1>

                <p data-reveal style="--reveal-delay: 160ms" class="mt-6 max-w-xl text-lg text-zinc-300">
                    {{ __('Explore Bitcoin and gold mining plans with clear terms, live market prices, admin-reviewed withdrawals and a full audit trail of every balance change.') }}
                </p>

                <div data-reveal style="--reveal-delay: 240ms" class="mt-8 flex flex-wrap gap-3">
                    <flux:button :href="route('register')" variant="primary" icon-trailing="arrow-right">{{ __('Open a demo account') }}</flux:button>
                    <a href="#plans" class="inline-flex h-10 items-center rounded-lg border border-white/20 px-4 text-sm font-medium text-white hover:bg-white/10">{{ __('See the plans') }}</a>
                </div>

                <p data-reveal style="--reveal-delay: 300ms" class="mt-5 text-xs text-zinc-400">
                    {{ __('Returns are not guaranteed. Capital is at risk.') }}
                    <a href="{{ route('legal.risk') }}" class="underline hover:text-gold-200">{{ __('Read the risk disclosure') }}</a>
                </p>
            </div>

            {{-- Live prices and a preview card --}}
            <div class="relative">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['btc' => ['Bitcoin', 'BTC / USD'], 'gold' => ['Gold', 'PAXG (1 oz) / USD']] as $asset => [$label, $pair])
                        <div data-reveal style="--reveal-delay: {{ $loop->index * 120 + 200 }}ms" class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="flex size-9 items-center justify-center rounded-full {{ $asset === 'btc' ? 'bg-[#f7931a]/20 text-[#f7931a]' : 'bg-gold-400/20 text-gold-300' }} font-display text-sm font-bold">{{ $asset === 'btc' ? '₿' : 'Au' }}</span>
                                    <div>
                                        <p class="text-sm font-semibold">{{ $label }}</p>
                                        <p class="text-xs text-zinc-400">{{ $pair }}</p>
                                    </div>
                                </div>
                                <span data-change="{{ $asset }}" class="text-xs font-semibold text-zinc-400">
                                    @if ($prices[$asset.'_change'] !== null)
                                        {{ $prices[$asset.'_change'] > 0 ? '+' : '' }}{{ number_format($prices[$asset.'_change'], 2) }}%
                                    @endif
                                </span>
                            </div>
                            <p data-price="{{ $asset }}" class="mt-4 rounded font-display text-2xl font-semibold tabular-nums">
                                {{ $prices[$asset.'_cents'] !== null ? Btc::usd($prices[$asset.'_cents']) : '—' }}
                            </p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-right text-[11px] text-zinc-500">{{ __('Live prices from CoinGecko, updated') }} <span data-price-updated>{{ $prices['updated_at'] ? \Illuminate\Support\Carbon::parse($prices['updated_at'])->format('H:i') : '…' }}</span></p>

                <div data-reveal style="--reveal-delay: 450ms" class="mt-6 animate-float rounded-2xl border border-gold-300/20 bg-linear-to-br from-navy-800 to-navy-900 p-6 shadow-2xl shadow-black/40">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-400">{{ __('Demo wallet') }}</span>
                        <span class="rounded-full bg-gold-400/15 px-2 py-0.5 text-xs font-medium text-gold-200">{{ __('Simulated') }}</span>
                    </div>
                    <p class="mt-3 font-display text-3xl font-bold tabular-nums">0.2500 <span class="text-base text-zinc-400">BTC</span></p>
                    <svg viewBox="0 0 300 70" class="mt-4 h-16 w-full" aria-hidden="true">
                        <defs>
                            <linearGradient id="hero-area" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0" stop-color="#e0b434" stop-opacity="0.45" />
                                <stop offset="1" stop-color="#e0b434" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path d="M0 55 L30 50 L60 52 L90 40 L120 44 L150 30 L180 34 L210 22 L240 26 L270 14 L300 18 L300 70 L0 70 Z" fill="url(#hero-area)" />
                        <path d="M0 55 L30 50 L60 52 L90 40 L120 44 L150 30 L180 34 L210 22 L240 26 L270 14 L300 18" fill="none" stroke="#e0b434" stroke-width="2.5" stroke-linejoin="round" />
                    </svg>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-lg bg-white/5 p-3"><p class="text-zinc-400">{{ __('BTC mining') }}</p><p class="mt-1 font-semibold">0.1500 BTC</p></div>
                        <div class="rounded-lg bg-white/5 p-3"><p class="text-zinc-400">{{ __('Gold mining') }}</p><p class="mt-1 font-semibold">0.1000 BTC</p></div>
                    </div>
                    <p class="mt-3 text-[11px] text-zinc-500">{{ __('Example figures for illustration only.') }}</p>
                </div>
            </div>
        </div>

        {{-- Scrolling facts strip --}}
        <div class="relative border-y border-white/10 bg-navy-900/80 py-3 text-sm text-zinc-300" aria-hidden="true">
            <div class="flex w-max animate-marquee gap-10 whitespace-nowrap">
                @foreach (range(1, 2) as $copy)
                    @foreach (['Demo platform, no real funds', 'Returns are not guaranteed', 'Withdrawals reviewed by an admin', 'Bitcoin address checksum validation', 'Two-step login available', 'Every balance change logged', 'Gold priced via PAX Gold', 'Amounts stored to the satoshi'] as $fact)
                        <span class="flex items-center gap-10"><span>{{ __($fact) }}</span><span class="text-gold-400">◆</span></span>
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>

    {{-- Key figures --}}
    <section class="border-b border-zinc-200 bg-white dark:border-white/10 dark:bg-navy-950">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-10 sm:px-6 md:grid-cols-4 lg:px-8">
            @foreach ([
                ['value' => $plans->count(), 'label' => 'Plans to compare', 'decimals' => 0],
                ['value' => $plans->min('duration_days') ?? 0, 'label' => 'Days, shortest term', 'decimals' => 0],
                ['value' => 8, 'label' => 'Decimal places, to the satoshi', 'decimals' => 0],
                ['value' => 100, 'label' => '% of balance changes audited', 'decimals' => 0],
            ] as $figure)
                <div data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms" class="text-center">
                    <p class="font-display text-3xl font-bold text-navy-900 sm:text-4xl dark:text-white"><span data-count="{{ $figure['value'] }}" data-decimals="{{ $figure['decimals'] }}">{{ $figure['value'] }}</span></p>
                    <p class="mt-1 text-sm">{{ __($figure['label']) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="scroll-mt-28 bg-zinc-50 py-20 dark:bg-navy-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold tracking-wide text-gold-600 uppercase dark:text-gold-300">{{ __('How it works') }}</p>
                <h2 class="mt-2 font-display text-3xl font-bold text-navy-900 sm:text-4xl dark:text-white">{{ __('Four steps, all visible in your dashboard') }}</h2>
            </div>

            <ol class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['icon' => 'user-plus', 'title' => 'Create & verify', 'text' => 'Sign up, confirm your email, turn on two-step login and send your ID for review.'],
                    ['icon' => 'qr-code', 'title' => 'Fund your demo wallet', 'text' => 'Record a simulated BTC deposit. An admin credits it after review.'],
                    ['icon' => 'chart-bar', 'title' => 'Choose a plan', 'text' => 'Compare Bitcoin and gold mining plans by term, minimum, risk level and indicative target.'],
                    ['icon' => 'arrow-up-tray', 'title' => 'Withdraw to your wallet', 'text' => 'Request a withdrawal to your own BTC address. Each request is checked and approved by an admin.'],
                ] as $step)
                    <li data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms" class="group relative rounded-2xl border border-zinc-200 bg-white p-6 transition hover:-translate-y-1 hover:border-gold-300 hover:shadow-xl hover:shadow-gold-500/10 dark:border-white/10 dark:bg-navy-900 dark:hover:border-gold-400/40">
                        <span class="absolute top-5 right-5 font-display text-4xl font-bold text-zinc-100 dark:text-white/5">0{{ $loop->iteration }}</span>
                        <span class="flex size-11 items-center justify-center rounded-xl bg-navy-900 text-gold-300 transition group-hover:bg-gold-400 group-hover:text-navy-950 dark:bg-white/10">
                            <flux:icon :name="$step['icon']" />
                        </span>
                        <h3 class="mt-5 font-display text-lg font-semibold text-navy-900 dark:text-white">{{ __($step['title']) }}</h3>
                        <p class="mt-2 text-sm leading-relaxed">{{ __($step['text']) }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Plans --}}
    <section id="plans" class="scroll-mt-28 bg-white py-20 dark:bg-navy-900/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold tracking-wide text-gold-600 uppercase dark:text-gold-300">{{ __('Plans') }}</p>
                <h2 class="mt-2 font-display text-3xl font-bold text-navy-900 sm:text-4xl dark:text-white">{{ __('Bitcoin and gold mining plans') }}</h2>
                <p class="mt-4">{{ __('Targets are indicative and used to simulate earnings in this demo. They are not promises. Real results can be lower, or a loss.') }}</p>
            </div>

            @foreach ([['Bitcoin mining', $btcPlans, '₿'], ['Gold mining', $goldPlans, 'Au']] as [$heading, $group, $symbol])
                @if ($group->isNotEmpty())
                    <h3 data-reveal class="mt-14 flex items-center gap-3 font-display text-xl font-semibold text-navy-900 dark:text-white">
                        <span class="flex size-8 items-center justify-center rounded-full bg-gold-100 text-sm font-bold text-gold-700 dark:bg-gold-400/15 dark:text-gold-300">{{ $symbol }}</span>
                        {{ __($heading) }}
                    </h3>
                    <div class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($group as $plan)
                            <article data-reveal style="--reveal-delay: {{ $loop->index * 90 }}ms" class="flex flex-col rounded-2xl border border-zinc-200 bg-white p-6 transition hover:-translate-y-1 hover:shadow-xl dark:border-white/10 dark:bg-navy-900">
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="font-display text-lg font-semibold text-navy-900 dark:text-white">{{ $plan->name }}</h4>
                                    <flux:badge size="sm" :color="$plan->risk_level === 'medium' ? 'amber' : 'red'">{{ __(':level risk', ['level' => $plan->riskLabel()]) }}</flux:badge>
                                </div>
                                <p class="mt-2 text-sm leading-relaxed">{{ $plan->summary }}</p>

                                <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-zinc-100 pt-5 text-sm dark:border-white/10">
                                    <div>
                                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Term') }}</dt>
                                        <dd class="mt-0.5 font-semibold text-navy-900 dark:text-white">{{ trans_choice(':count day|:count days', $plan->duration_days) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Indicative target') }}</dt>
                                        <dd class="mt-0.5 font-semibold text-navy-900 dark:text-white">{{ $plan->targetPercent() }}% <span class="font-normal text-zinc-500">{{ __('over term') }}</span></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Minimum') }}</dt>
                                        <dd class="mt-0.5 font-semibold text-navy-900 dark:text-white"><x-btc :sats="$plan->min_sats" /></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Maximum') }}</dt>
                                        <dd class="mt-0.5 font-semibold text-navy-900 dark:text-white">@if ($plan->max_sats) <x-btc :sats="$plan->max_sats" /> @else {{ __('No limit') }} @endif</dd>
                                    </div>
                                </dl>

                                <p class="mt-5 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:bg-white/5 dark:text-zinc-400">{{ __('Not guaranteed. Capital at risk. Principal is locked for the full term.') }}</p>

                                <div class="mt-auto pt-5">
                                    <flux:button :href="auth()->check() ? route('invest') : route('register')" variant="filled" class="w-full">{{ __('Try this plan in demo') }}</flux:button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            @endforeach

            @if ($plans->isEmpty())
                <p class="mt-10 text-center text-sm">{{ __('Plans will appear here once the admin publishes them.') }}</p>
            @endif
        </div>
    </section>

    {{-- Security --}}
    <section id="security" class="scroll-mt-28 relative overflow-hidden bg-navy-950 py-20 text-white">
        <div class="pointer-events-none absolute top-0 left-1/2 -z-0 h-px w-3/4 -translate-x-1/2 bg-linear-to-r from-transparent via-gold-400/60 to-transparent" aria-hidden="true"></div>
        <div class="relative mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-5 lg:px-8">
            <div data-reveal class="lg:col-span-2">
                <p class="text-sm font-semibold tracking-wide text-gold-300 uppercase">{{ __('Security') }}</p>
                <h2 class="mt-2 font-display text-3xl font-bold sm:text-4xl">{{ __('Built so money cannot go missing') }}</h2>
                <p class="mt-4 text-zinc-300">{{ __('Every amount is stored as whole satoshis, every balance change runs in a locked database transaction, and nothing leaves a wallet without an admin looking at it first.') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:col-span-3">
                @foreach ([
                    ['icon' => 'shield-check', 'title' => 'Two-step login', 'text' => 'Protect your account with an authenticator app code or a passkey.'],
                    ['icon' => 'identification', 'title' => 'Identity checks', 'text' => 'KYC documents are stored privately and reviewed by an admin before withdrawals.'],
                    ['icon' => 'check-badge', 'title' => 'Checked addresses', 'text' => 'Withdrawal addresses are validated, including their checksum, to catch typos.'],
                    ['icon' => 'document-magnifying-glass', 'title' => 'Full audit log', 'text' => 'Each change records the amount, the balance after it, the reason and who made it.'],
                ] as $feature)
                    <div data-reveal style="--reveal-delay: {{ $loop->index * 90 }}ms" class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <flux:icon :name="$feature['icon']" class="text-gold-300" />
                        <h3 class="mt-3 font-semibold">{{ __($feature['title']) }}</h3>
                        <p class="mt-1 text-sm text-zinc-300">{{ __($feature['text']) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Testimonials (placeholders) --}}
    <section class="bg-zinc-50 py-20 dark:bg-navy-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold tracking-wide text-gold-600 uppercase dark:text-gold-300">{{ __('Reviews') }}</p>
                <h2 class="mt-2 font-display text-3xl font-bold text-navy-900 sm:text-4xl dark:text-white">{{ __('What users say') }}</h2>
                <p class="mt-3 text-sm">{{ __('Placeholders: genuine, verified reviews will be shown here after launch. We never publish invented testimonials.') }}</p>
            </div>
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                @foreach (range(1, 3) as $i)
                    <figure data-reveal style="--reveal-delay: {{ $loop->index * 90 }}ms" class="rounded-2xl border border-dashed border-zinc-300 bg-white p-6 dark:border-white/15 dark:bg-navy-900">
                        <div class="flex gap-1 text-zinc-300 dark:text-white/20" aria-hidden="true">
                            @foreach (range(1, 5) as $star)
                                <flux:icon.star variant="mini" />
                            @endforeach
                        </div>
                        <blockquote class="mt-4 space-y-2" aria-label="{{ __('Review placeholder') }}">
                            <div class="h-3 w-full rounded bg-zinc-100 dark:bg-white/10"></div>
                            <div class="h-3 w-11/12 rounded bg-zinc-100 dark:bg-white/10"></div>
                            <div class="h-3 w-2/3 rounded bg-zinc-100 dark:bg-white/10"></div>
                        </blockquote>
                        <figcaption class="mt-6 flex items-center gap-3">
                            <span class="size-10 rounded-full bg-zinc-100 dark:bg-white/10"></span>
                            <span class="text-sm text-zinc-400">{{ __('Verified review :number', ['number' => $i]) }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="scroll-mt-28 bg-white py-20 dark:bg-navy-900/40">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="text-center">
                <p class="text-sm font-semibold tracking-wide text-gold-600 uppercase dark:text-gold-300">{{ __('FAQ') }}</p>
                <h2 class="mt-2 font-display text-3xl font-bold text-navy-900 sm:text-4xl dark:text-white">{{ __('Questions people ask') }}</h2>
            </div>
            <div class="mt-10 divide-y divide-zinc-200 rounded-2xl border border-zinc-200 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-navy-900">
                @foreach ($faqs as $faq)
                    <details data-reveal class="group p-5" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-navy-900 dark:text-white">
                            {{ __($faq['q']) }}
                            <flux:icon.chevron-down variant="mini" class="shrink-0 transition group-open:rotate-180" />
                        </summary>
                        <p class="mt-3 text-sm leading-relaxed">{{ __($faq['a']) }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Risk + call to action --}}
    <section class="bg-zinc-50 pb-20 dark:bg-navy-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="relative overflow-hidden rounded-3xl bg-linear-to-br from-navy-800 to-navy-950 p-8 text-white sm:p-12">
                <div class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-gold-400/25 blur-3xl" aria-hidden="true"></div>
                <div class="relative grid gap-8 lg:grid-cols-2 lg:items-center">
                    <div>
                        <h2 class="font-display text-3xl font-bold">{{ __('See the whole platform with zero risk') }}</h2>
                        <p class="mt-3 text-zinc-300">{{ __('Open a free demo account to try deposits, plans and withdrawals with simulated balances.') }}</p>
                        <div class="mt-6"><flux:button :href="route('register')" variant="primary" icon-trailing="arrow-right">{{ __('Open a demo account') }}</flux:button></div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5 text-sm text-zinc-300">
                        <p class="flex items-center gap-2 font-semibold text-gold-200"><flux:icon.exclamation-triangle variant="mini" /> {{ __('Risk disclaimer') }}</p>
                        <p class="mt-2">{{ __('Mining and crypto-asset investments are high risk and largely unregulated in many countries. Prices are volatile, mining output varies, and returns are not guaranteed. Only invest money you can afford to lose, and seek independent advice.') }}</p>
                        <a href="{{ route('legal.risk') }}" class="mt-3 inline-block font-medium text-gold-200 underline">{{ __('Full risk disclosure') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts::site>
