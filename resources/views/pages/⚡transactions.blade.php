<?php

use App\Enums\LedgerType;
use App\Enums\RequestStatus;
use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\Withdrawal;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Transactions')] class extends Component {
    use WithPagination;

    /**
     * Which tab is open: "requests" (deposits and withdrawals) or "balance" (every balance change).
     */
    #[Url]
    public string $view = 'requests';

    /**
     * Ledger type filter on the balance tab.
     */
    #[Url]
    public string $type = '';

    /**
     * Deposits, withdrawals or both, on the requests tab.
     */
    #[Url]
    public string $kind = 'all';

    #[Url]
    public string $status = 'all';

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'type', 'kind', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, LedgerEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return Auth::user()->ledgerEntries()
            ->when(LedgerType::tryFrom($this->type), fn ($query, LedgerType $type) => $query->where('type', $type))
            ->latest('id')
            ->paginate(15);
    }

    /**
     * Deposits and withdrawals in one list, newest first, 15 per page.
     *
     * @return LengthAwarePaginator<int, Deposit|Withdrawal>
     */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        $userId = Auth::id();
        $status = RequestStatus::tryFrom($this->status);

        $deposits = DB::table('deposits')->where('user_id', $userId)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->selectRaw("'deposit' as kind, id, created_at");

        $withdrawals = DB::table('withdrawals')->where('user_id', $userId)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->selectRaw("'withdrawal' as kind, id, created_at");

        $rows = match ($this->kind) {
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            default => $deposits->unionAll($withdrawals),
        };

        $page = DB::query()->fromSub($rows, 'requests')->orderByDesc('created_at')->orderByDesc('id')->paginate(15);

        $models = [
            'deposit' => Deposit::query()->with('wallet')->findMany($page->getCollection()->where('kind', 'deposit')->pluck('id'))->keyBy('id'),
            'withdrawal' => Withdrawal::query()->findMany($page->getCollection()->where('kind', 'withdrawal')->pluck('id'))->keyBy('id'),
        ];

        return $page->setCollection($page->getCollection()->map(fn (object $row): Deposit|Withdrawal => $models[$row->kind][$row->id]));
    }
};
?>

<div wire:poll.20s data-live-page class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Transactions') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Your deposits, withdrawals and every change to your balance. Open a receipt to print or save it.') }}</flux:text>
        </div>
        <flux:button :href="route('transactions.export')" icon="arrow-down-tray">{{ __('Download statement (CSV)') }}</flux:button>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:radio.group wire:model.live="view" variant="segmented">
            <flux:radio value="requests" :label="__('Deposits & withdrawals')" />
            <flux:radio value="balance" :label="__('All balance changes')" />
        </flux:radio.group>

        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            @if ($view === 'balance')
                <flux:select wire:model.live="type" class="min-w-0 flex-1 sm:w-56 sm:flex-none">
                    <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                    @foreach (LedgerType::cases() as $case)
                        <flux:select.option :value="$case->value">{{ $case === LedgerType::Adjustment ? __('Wallet funded / debited') : $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model.live="kind" class="min-w-0 flex-1 sm:w-44 sm:flex-none">
                    <flux:select.option value="all">{{ __('Deposits and withdrawals') }}</flux:select.option>
                    <flux:select.option value="deposits">{{ __('Deposits only') }}</flux:select.option>
                    <flux:select.option value="withdrawals">{{ __('Withdrawals only') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="status" class="min-w-0 flex-1 sm:w-36 sm:flex-none">
                    <flux:select.option value="all">{{ __('Any status') }}</flux:select.option>
                    @foreach (RequestStatus::cases() as $case)
                        <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        @if ($view === 'balance')
            <flux:table :paginate="$this->entries" class="[&_td]:px-4! [&_th]:px-4!">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Details') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Balance after') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->entries as $entry)
                        <flux:table.row :key="'e'.$entry->id">
                            <flux:table.cell class="whitespace-nowrap">{{ $entry->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$entry->amount_micros >= 0 ? 'green' : 'zinc'">{{ $entry->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="max-w-72">
                                <p class="truncate">{{ $entry->description }}</p>
                                @if ($receipt = $entry->receiptUrl())
                                    <a href="{{ $receipt }}" class="text-xs text-brand-600 underline dark:text-brand-400">{{ __('View receipt') }}</a>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end"><x-usdt :micros="$entry->amount_micros" signed @class(['font-medium', 'text-green-600 dark:text-green-400' => $entry->amount_micros > 0]) /></flux:table.cell>
                            <flux:table.cell align="end"><x-usdt :micros="$entry->balance_after_micros" /></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center">{{ __('No transactions yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            <flux:table :paginate="$this->requests" class="[&_td]:px-4! [&_th]:px-4!">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Receipt') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->requests as $item)
                        @php($isDeposit = $item instanceof Deposit)
                        <flux:table.row :key="($isDeposit ? 'd' : 'w').$item->id" data-history-row>
                            <flux:table.cell class="whitespace-nowrap">
                                {{ $item->created_at->format('M j, Y g:i A') }}
                                <p class="font-mono text-[11px] text-zinc-400">{{ $item->code() }}</p>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span @class(['inline-flex items-center gap-1.5 font-medium', 'text-green-700 dark:text-green-400' => $isDeposit, 'text-zinc-900 dark:text-white' => ! $isDeposit])>
                                    <flux:icon :name="$isDeposit ? 'arrow-down-tray' : 'arrow-up-tray'" variant="micro" />
                                    {{ $isDeposit ? __('Deposit') : __('Withdrawal') }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                @if ($isDeposit)
                                    <span class="tabular-nums">{{ $item->coinLabel() }}</span>
                                    <p class="text-xs text-zinc-500">
                                        @if ($item->credited_micros !== null)
                                            {{ __('Credited :amount USDT', ['amount' => \App\Support\Usdt::format($item->credited_micros)]) }}
                                        @else
                                            {{ $item->network }}
                                        @endif
                                    </p>
                                @else
                                    <span class="tabular-nums">{{ $item->payoutLabel() }}</span>
                                    <p class="text-xs text-zinc-500">{{ $item->network->label() }}</p>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$item->status->color()">{{ $item->status->label() }}</flux:badge>
                                @if ($item->admin_note) <p class="mt-1 max-w-56 truncate text-xs text-zinc-500" title="{{ $item->admin_note }}">{{ $item->admin_note }}</p> @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" icon="document-text" :href="$item->receiptUrl()" data-receipt-link>{{ __('Receipt') }}</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center">{{ __('No deposits or withdrawals yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
