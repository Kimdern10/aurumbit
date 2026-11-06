<?php

namespace App\Http\Controllers;

use App\Models\LedgerEntry;
use App\Support\Usdt;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementController extends Controller
{
    /**
     * Download the signed-in user's full transaction history as a CSV file.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $filename = 'statement-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($user): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Date', 'Type', 'Details', 'Amount (USDT)', 'Balance after (USDT)']);

            $user->ledgerEntries()->oldest('id')->lazyById()->each(function (LedgerEntry $entry) use ($out): void {
                fputcsv($out, [
                    $entry->created_at->format('Y-m-d H:i:s'),
                    $entry->label(),
                    $entry->description,
                    str_replace(',', '', Usdt::format($entry->amount_micros, 6)),
                    str_replace(',', '', Usdt::format($entry->balance_after_micros, 6)),
                ]);
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
