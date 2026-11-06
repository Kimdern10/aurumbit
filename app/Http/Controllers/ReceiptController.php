<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentStatus;
use App\Models\Deposit;
use App\Models\Investment;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    /**
     * A printable receipt for one deposit, withdrawal or plan. Customers only
     * see their own; admins (on the admin route) can open any.
     */
    public function __invoke(Request $request, string $kind, int $id): View
    {
        $model = match ($kind) {
            'deposit' => Deposit::query()->with(['user', 'wallet'])->findOrFail($id),
            'withdrawal' => Withdrawal::query()->with('user')->findOrFail($id),
            default => Investment::query()->with(['user', 'plan'])->findOrFail($id),
        };

        abort_unless($request->user()->is_admin || $model->user_id === $request->user()->id, 404);

        $balanceAfter = $model instanceof Investment && $model->status === InvestmentStatus::Active
            ? null
            : $model->user->ledgerEntries()
                ->where('subject_type', $model->getMorphClass())
                ->where('subject_id', $model->id)
                ->latest('id')
                ->value('balance_after_micros');

        $backRoute = match ($kind) {
            'deposit' => $request->user()->is_admin ? route('admin.deposits') : route('transactions', ['view' => 'requests']),
            'withdrawal' => $request->user()->is_admin ? route('admin.withdrawals') : route('transactions', ['view' => 'requests']),
            default => $request->user()->is_admin ? route('admin.investments') : route('investments'),
        };

        return view('receipt', [
            'item' => $model,
            'kind' => $kind,
            'balanceAfter' => $balanceAfter,
            'backUrl' => $backRoute,
        ]);
    }
}
