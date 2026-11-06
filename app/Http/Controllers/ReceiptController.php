<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    /**
     * A printable receipt for one deposit or withdrawal. Customers only see their
     * own; admins (on the admin route) can open any.
     */
    public function __invoke(Request $request, string $kind, int $id): View
    {
        $model = $kind === 'deposit'
            ? Deposit::query()->with(['user', 'wallet'])->findOrFail($id)
            : Withdrawal::query()->with('user')->findOrFail($id);

        abort_unless($request->user()->is_admin || $model->user_id === $request->user()->id, 404);

        $balanceAfter = $model->user->ledgerEntries()
            ->where('subject_type', $model->getMorphClass())
            ->where('subject_id', $model->id)
            ->latest('id')
            ->value('balance_after_micros');

        return view('receipt', [
            'item' => $model,
            'isDeposit' => $model instanceof Deposit,
            'balanceAfter' => $balanceAfter,
            'backUrl' => $request->user()->is_admin
                ? route($model instanceof Deposit ? 'admin.deposits' : 'admin.withdrawals')
                : route('transactions', ['view' => 'requests']),
        ]);
    }
}
