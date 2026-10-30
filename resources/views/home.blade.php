@php
    use App\Enums\PlanCategory;
    use App\Support\Btc;

    $faqs = [
        ['q' => 'What can I invest in?', 'a' => 'Bitcoin, gold, Ethereum, Solana and Crypto Mix plans. Every plan is funded from your BTC balance, and you can see the rate, term and exact earnings before you invest.'],
        ['q' => 'How much will I earn?', 'a' => 'Each plan has a rate for its full term. Your earnings build up every day and are paid with your capital at the end of the term. Use the calculator to see the exact figures for any plan and amount.'],
        ['q' => 'How do I deposit?', 'a' => 'Send Bitcoin to the deposit address shown in your dashboard, then enter the amount and the transaction ID. Your balance is credited once the transaction is confirmed on the blockchain.'],
        ['q' => 'How do withdrawals work?', 'a' => 'Request a withdrawal to your own Bitcoin address. We check the address, then send your BTC and show you the transaction ID so you can follow the payment on the blockchain.'],
        ['q' => 'Why do you ask for identity documents?', 'a' => 'Identity checks keep accounts safe and help prevent fraud. Your documents are stored privately and only seen by our verification team.'],
        ['q' => 'How are gold plans priced?', 'a' => 'Gold plans are funded in BTC. When you start a plan, we show how much gold it is worth at that moment using the live PAX Gold price (one token is backed by one troy ounce of gold).'],
    ];

    $assets = [
        'btc' => ['Bitcoin', 'BTC', '₿', '#f7931a'],
        'eth' => ['Ethereum', 'ETH', 'Ξ', '#627eea'],
        'sol' => ['Solana', 'SOL', 'S', '#14b8a6'],
        'gold' => ['Gold', 'PAXG · 1 oz', 'Au', '#c9a227'],
    ];

    $icons = [
        'bitcoin' => 'currency-dollar',
        'gold' => 'cube',
        'ethereum' => 'bolt',
        'solana' => 'sparkles',
        'crypto_index' => 'chart-pie',
    ];
@endphp

<x-layouts::site>
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-white dark:bg-ink-950" data-prices-url="{{ route('prices') }}">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60rem_40rem_at_85%_-10%,rgb(22_82_240/0.14),transparent),radial-gradient(40rem_30rem_at_0%_100%,rgb(6_182_212/0.10),transparent)]" aria-hidden="true"></div>
        <canvas data-hero-canvas class="pointer-events-none absolute inset-0 -z-10 size-full opacity-60" aria-hidden="true"></canvas>

        <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 pt-14 pb-20 sm:px-6 lg:grid-cols-12 lg:px-8 lg:pt-24 lg:pb-28">
            <div class="lg:col-span-7">
                <p data-reveal class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:text-brand-200 dark:ring-brand-500/20">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-brand-500"></span></span>
                    {{ __('Bitcoin · Gold · Ethereum · Solana') }}
                </p>

                <h1 data-reveal style="--reveal-delay: 80ms" class="mt-6 font-display text-[2.6rem] leading-[1.05] font-extrabold text-ink-950 sm:text-6xl lg:text-7xl dark:text-white">
                    {{ __('Grow your crypto with') }}
                    <span class="text-brand-gradient animate-shine">{{ __('fixed-term plans.') }}</span>
                </h1>

                <p data-reveal style="--reveal-delay: 160ms" class="mt-6 max-w-xl text-lg leading-relaxed text-ink-500 dark:text-ink-300">
                    {{ __('Choose Bitcoin, gold, Ethereum and Solana plans from one account. See your daily earnings and payout date before you invest, and withdraw to your own wallet.') }}
                </p>

                <div data-reveal style="--reveal-delay: 240ms" class="mt-9 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="inline-flex h-12 items-center gap-2 rounded-full bg-brand-600 px-7 text-base font-semibold text-white shadow-lg shadow-brand-600/25 transition hover:bg-brand-700">
                        {{ __('Get started') }} <flux:icon.arrow-right variant="mini" />
                    </a>
                    <a href="#plans" class="inline-flex h-12 items-center rounded-full border border-ink-200 bg-white px-7 text-base font-semibold text-ink-900 transition hover:border-ink-300 dark:border-white/15 dark:bg-white/5 dark:text-white">{{ __('View plans') }}</a>
                </div>

                <ul data-reveal style="--reveal-delay: 320ms" class="mt-10 grid max-w-xl grid-cols-1 gap-3 text-sm text-ink-600 sm:grid-cols-3 dark:text-ink-300">
                    @foreach ([['shield-check', 'Two-step login'], ['lock-closed', 'Private ID storage'], ['chat-bubble-left-right', 'Live support']] as [$icon, $text])
                        <li class="flex items-center gap-2"><flux:icon :name="$icon" variant="mini" class="text-brand-600 dark:text-brand-400" /> {{ __($text) }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Live markets card --}}
            <div class="lg:col-span-5">
                <div data-reveal style="--reveal-delay: 200ms" class="rounded-3xl border border-ink-100 bg-white/90 p-6 shadow-2xl shadow-brand-900/10 backdrop-blur dark:border-white/10 dark:bg-ink-900/80">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold text-ink-950 dark:text-white">{{ __('Live markets') }}</p>
                        <span class="flex items-center gap-1.5 text-xs text-ink-400"><span class="size-1.5 rounded-full bg-green-500"></span>{{ __('Updated') }} <span data-price-updated>{{ $prices['updated_at'] ? \Illuminate\Support\Carbon::parse($prices['updated_at'])->format('g:i A') : '…' }}</span></span>
                    </div>
                    <ul class="mt-4 divide-y divide-ink-100 dark:divide-white/10">
                        @foreach ($assets as $asset => [$label, $pair, $symbol, $color])
                            <li class="flex items-center justify-between gap-3 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-10 items-center justify-center rounded-full font-display text-sm font-bold" style="background: {{ $color }}1f; color: {{ $color }}">{{ $symbol }}</span>
                                    <div>
                                        <p class="font-semibold text-ink-950 dark:text-white">{{ $label }}</p>
                                        <p class="text-xs text-ink-400">{{ $pair }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p data-price="{{ $asset }}" class="rounded px-1 font-semibold text-ink-950 tabular-nums dark:text-white">{{ $prices[$asset.'_cents'] !== null ? Btc::usd($prices[$asset.'_cents']) : '—' }}</p>
                                    <p data-change="{{ $asset }}" class="text-xs font-medium text-ink-400">
                                        @if ($prices[$asset.'_change'] !== null)
                                            {{ $prices[$asset.'_change'] > 0 ? '+' : '' }}{{ number_format($prices[$asset.'_change'], 2) }}%
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('register') }}" class="mt-4 flex h-11 items-center justify-center rounded-xl bg-ink-950 text-sm font-semibold text-white transition hover:bg-ink-800 dark:bg-white dark:text-ink-950">{{ __('Start investing') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Key figures --}}
    <section class="border-y border-ink-100 bg-ink-50/60 dark:border-white/10 dark:bg-ink-900/40">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 py-10 sm:px-6 md:grid-cols-4 lg:px-8">
            @foreach ([
                ['value' => $plans->count(), 'label' => 'Plans to choose from'],
                ['value' => $plans->pluck('category')->unique()->count(), 'label' => 'Ways to invest'],
                ['value' => $plans->min('duration_days') ?? 0, 'label' => 'Days, shortest term'],
                ['value' => (int) floor(($plans->max('target_return_bps') ?? 0) / 100), 'label' => '% top plan rate'],
            ] as $figure)
                <div data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms" class="text-center">
                    <p class="font-display text-4xl font-extrabold text-ink-950 dark:text-white"><span data-count="{{ $figure['value'] }}">{{ $figure['value'] }}</span></p>
                    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ __($figure['label']) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Products --}}
    <section class="bg-white py-24 dark:bg-ink-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="max-w-2xl">
                <p class="text-sm font-semibold text-brand-600 dark:text-brand-400">{{ __('Ways to invest') }}</p>
                <h2 class="mt-2 font-display text-4xl font-extrabold text-ink-950 sm:text-5xl dark:text-white">{{ __('One account. Five ways to grow.') }}</h2>
                <p class="mt-4 text-lg text-ink-500 dark:text-ink-300">{{ __('Fund your account in Bitcoin once, then spread it across the plans that suit you.') }}</p>
            </div>

            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                @foreach (PlanCategory::cases() as $category)
                    @php
                        $group = $plans->where('category', $category);
                        $best = $group->sortByDesc('target_return_bps')->first();
                    @endphp
                    <a href="#plans" data-reveal style="--reveal-delay: {{ $loop->index * 70 }}ms" class="group flex flex-col rounded-3xl border border-ink-100 bg-white p-6 transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl hover:shadow-brand-900/5 dark:border-white/10 dark:bg-ink-900">
                        <span class="flex size-12 items-center justify-center rounded-2xl" style="background: {{ $category->color() }}1a; color: {{ $category->color() }}">
                            <flux:icon :name="$icons[$category->value]" />
                        </span>
                        <h3 class="mt-5 font-semibold text-ink-950 dark:text-white">{{ __($category->label()) }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-ink-500 dark:text-ink-400">{{ __($category->description()) }}</p>
                        <p class="mt-5 text-sm font-semibold text-brand-600 dark:text-brand-400">
                            @if ($best)
                                {{ __('Up to :rate% in :days days', ['rate' => $best->targetPercent(), 'days' => $best->duration_days]) }}
                            @else
                                {{ __('Coming soon') }}
                            @endif
                        </p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Calculator --}}
    @if ($plans->isNotEmpty())
        <section class="bg-ink-50/60 py-24 dark:bg-ink-900/40">
            <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-5 lg:px-8">
                <div data-reveal class="lg:col-span-2">
                    <p class="text-sm font-semibold text-brand-600 dark:text-brand-400">{{ __('Earnings calculator') }}</p>
                    <h2 class="mt-2 font-display text-4xl font-extrabold text-ink-950 sm:text-5xl dark:text-white">{{ __('How much will I earn?') }}</h2>
                    <p class="mt-4 text-lg text-ink-500 dark:text-ink-300">{{ __('Pick a plan and an amount. See what you earn every day, your total profit and the exact date you get paid.') }}</p>
                    <ul class="mt-6 space-y-3 text-ink-600 dark:text-ink-300">
                        @foreach (['Earnings added every day', 'Capital and profit paid at the end of the term', 'Withdraw to your own Bitcoin wallet'] as $point)
                            <li class="flex items-center gap-3"><flux:icon.check-circle class="size-5 text-brand-600 dark:text-brand-400" /> {{ __($point) }}</li>
                        @endforeach
                    </ul>
                </div>
                <div data-reveal style="--reveal-delay: 120ms" class="lg:col-span-3">
                    <x-returns-calculator :plans="$plans" class="!rounded-3xl !border-ink-100 shadow-2xl shadow-brand-900/10 dark:!border-white/10" />
                </div>
            </div>
        </section>
    @endif

    {{-- Plans --}}
    <section id="plans" class="scroll-mt-24 bg-white py-24 dark:bg-ink-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="max-w-2xl">
                <p class="text-sm font-semibold text-brand-600 dark:text-brand-400">{{ __('Plans') }}</p>
                <h2 class="mt-2 font-display text-4xl font-extrabold text-ink-950 sm:text-5xl dark:text-white">{{ __('Choose your plan') }}</h2>
            </div>

            @foreach (PlanCategory::cases() as $category)
                @php
                    $group = $plans->where('category', $category);
                @endphp
                @if ($group->isNotEmpty())
                    <div data-reveal class="mt-14 flex flex-wrap items-end justify-between gap-2">
                        <h3 class="flex items-center gap-3 text-xl font-bold text-ink-950 dark:text-white">
                            <span class="flex size-9 items-center justify-center rounded-xl font-display text-sm font-bold" style="background: {{ $category->color() }}1a; color: {{ $category->color() }}">{{ $category->symbol() }}</span>
                            {{ __($category->label()) }}
                        </h3>
                    </div>
                    <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($group as $plan)
                            <article data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms" class="flex flex-col rounded-3xl border border-ink-100 bg-white p-7 transition hover:border-brand-200 hover:shadow-xl hover:shadow-brand-900/5 dark:border-white/10 dark:bg-ink-900">
                                <h4 class="text-lg font-bold text-ink-950 dark:text-white">{{ $plan->name }}</h4>
                                <p class="mt-1 text-sm leading-relaxed text-ink-500 dark:text-ink-400">{{ $plan->summary }}</p>

                                <p class="mt-6 flex items-baseline gap-2">
                                    <span class="font-display text-5xl font-extrabold text-ink-950 dark:text-white">{{ $plan->targetPercent() }}%</span>
                                    <span class="text-sm text-ink-500">{{ __('in :days days', ['days' => $plan->duration_days]) }}</span>
                                </p>
                                <p class="text-sm text-brand-600 dark:text-brand-400">{{ __(':rate% a day', ['rate' => $plan->dailyPercent()]) }}</p>

                                <dl class="mt-6 space-y-2.5 border-t border-ink-100 pt-5 text-sm dark:border-white/10">
                                    <div class="flex justify-between"><dt class="text-ink-500">{{ __('Minimum') }}</dt><dd class="font-semibold text-ink-950 dark:text-white"><x-btc :sats="$plan->min_sats" /></dd></div>
                                    <div class="flex justify-between"><dt class="text-ink-500">{{ __('Maximum') }}</dt><dd class="font-semibold text-ink-950 dark:text-white">@if ($plan->max_sats)<x-btc :sats="$plan->max_sats" />@else{{ __('No limit') }}@endif</dd></div>
                                    <div class="flex justify-between"><dt class="text-ink-500">{{ __(':amount BTC returns', ['amount' => Btc::format($plan->min_sats)]) }}</dt><dd class="font-semibold text-green-600 dark:text-green-400"><x-btc :sats="$plan->min_sats + $plan->profitFor($plan->min_sats)" /></dd></div>
                                </dl>

                                <div class="mt-auto pt-7">
                                    <a href="{{ auth()->check() ? route('invest') : route('register') }}" class="flex h-11 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white transition hover:bg-brand-700">{{ __('Invest now') }}</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            @endforeach

            @if ($plans->isEmpty())
                <p class="mt-10 text-ink-500">{{ __('Plans will appear here soon.') }}</p>
            @endif
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="scroll-mt-24 bg-ink-50/60 py-24 dark:bg-ink-900/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold text-brand-600 dark:text-brand-400">{{ __('How it works') }}</p>
                <h2 class="mt-2 font-display text-4xl font-extrabold text-ink-950 sm:text-5xl dark:text-white">{{ __('Start in four simple steps') }}</h2>
            </div>

            <ol class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['icon' => 'user-plus', 'title' => 'Create your account', 'text' => 'Sign up in a minute, then verify your identity.'],
                    ['icon' => 'arrow-down-tray', 'title' => 'Fund with Bitcoin', 'text' => 'Send BTC to your deposit address and enter the transaction ID.'],
                    ['icon' => 'chart-bar', 'title' => 'Choose a plan', 'text' => 'Pick a Bitcoin, gold or crypto plan and watch your earnings grow daily.'],
                    ['icon' => 'arrow-up-tray', 'title' => 'Withdraw anytime', 'text' => 'Send your balance to your own Bitcoin wallet when a plan ends.'],
                ] as $step)
                    <li data-reveal style="--reveal-delay: {{ $loop->index * 90 }}ms" class="relative rounded-3xl bg-white p-7 ring-1 ring-ink-100 dark:bg-ink-900 dark:ring-white/10">
                        <span class="text-sm font-bold text-brand-600 dark:text-brand-400">{{ __('Step :n', ['n' => $loop->iteration]) }}</span>
                        <span class="mt-4 flex size-12 items-center justify-center rounded-2xl bg-brand-600 text-white"><flux:icon :name="$step['icon']" /></span>
                        <h3 class="mt-5 text-lg font-bold text-ink-950 dark:text-white">{{ __($step['title']) }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-500 dark:text-ink-400">{{ __($step['text']) }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Security --}}
    <section id="security" class="scroll-mt-24 bg-white py-24 dark:bg-ink-950">
        <div class="mx-auto grid max-w-7xl gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8">
            <div data-reveal>
                <p class="text-sm font-semibold text-brand-600 dark:text-brand-400">{{ __('Security') }}</p>
                <h2 class="mt-2 font-display text-4xl font-extrabold text-ink-950 sm:text-5xl dark:text-white">{{ __('Your account, protected at every step') }}</h2>
                <p class="mt-4 text-lg text-ink-500 dark:text-ink-300">{{ __('Every amount is tracked to the satoshi, every change is recorded, and no withdrawal leaves without a check.') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['icon' => 'shield-check', 'title' => 'Two-step login', 'text' => 'Protect your account with an authenticator app or a passkey.'],
                    ['icon' => 'identification', 'title' => 'Identity checks', 'text' => 'ID documents are stored privately and reviewed by our team.'],
                    ['icon' => 'check-badge', 'title' => 'Checked addresses', 'text' => 'Withdrawal addresses are validated to catch typos.'],
                    ['icon' => 'document-magnifying-glass', 'title' => 'Full history', 'text' => 'See every deposit, earning and withdrawal, and download it as CSV.'],
                ] as $feature)
                    <div data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms" class="rounded-3xl bg-ink-50 p-6 dark:bg-ink-900">
                        <flux:icon :name="$feature['icon']" class="text-brand-600 dark:text-brand-400" />
                        <h3 class="mt-4 font-bold text-ink-950 dark:text-white">{{ __($feature['title']) }}</h3>
                        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ __($feature['text']) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="scroll-mt-24 bg-ink-50/60 py-24 dark:bg-ink-900/40">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="text-center">
                <p class="text-sm font-semibold text-brand-600 dark:text-brand-400">{{ __('FAQ') }}</p>
                <h2 class="mt-2 font-display text-4xl font-extrabold text-ink-950 sm:text-5xl dark:text-white">{{ __('Questions? Answers.') }}</h2>
            </div>
            <div class="mt-12 space-y-3">
                @foreach ($faqs as $faq)
                    <details data-reveal class="group rounded-2xl bg-white p-6 ring-1 ring-ink-100 dark:bg-ink-900 dark:ring-white/10" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink-950 dark:text-white">
                            {{ __($faq['q']) }}
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-ink-100 transition group-open:rotate-45 dark:bg-white/10"><flux:icon.plus variant="micro" /></span>
                        </summary>
                        <p class="mt-3 leading-relaxed text-ink-500 dark:text-ink-300">{{ __($faq['a']) }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Call to action --}}
    <section class="bg-white py-20 dark:bg-ink-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="relative overflow-hidden rounded-[2rem] bg-linear-to-br from-brand-600 via-brand-700 to-brand-900 px-8 py-14 text-center text-white sm:px-16">
                <div class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-cyan-400/30 blur-3xl" aria-hidden="true"></div>
                <h2 class="relative font-display text-4xl font-extrabold sm:text-5xl">{{ __('Start growing your crypto today') }}</h2>
                <p class="relative mx-auto mt-4 max-w-xl text-lg text-brand-100">{{ __('Open your free account in minutes and pick the plan that fits you.') }}</p>
                <a href="{{ route('register') }}" class="relative mt-8 inline-flex h-12 items-center gap-2 rounded-full bg-white px-8 font-semibold text-brand-700 transition hover:bg-brand-50">{{ __('Create free account') }} <flux:icon.arrow-right variant="mini" /></a>
            </div>
        </div>
    </section>
</x-layouts::site>
