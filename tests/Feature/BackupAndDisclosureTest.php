<?php

use App\Models\Plan;
use App\Models\User;
use App\Support\DatabaseBackup;
use Illuminate\Support\Facades\Storage;

test('the backup command saves every table of this site to a zip and skips temporary tables', function () {
    Storage::fake('local');
    User::factory()->create(['email' => 'saved@example.com']);

    $this->artisan('app:backup-database')->assertSuccessful();

    $backup = DatabaseBackup::list()[0];
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($backup['path']));
    $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

    expect($manifest['tables'])->toHaveKeys(['users', 'ledger_entries', 'deposits', 'withdrawals', 'investments'])
        ->and($manifest['tables'])->not->toHaveKeys(['cache', 'sessions', 'jobs'])
        ->and($zip->getFromName('users.json'))->toContain('saved@example.com');
});

test('old backups are deleted so only the newest are kept', function () {
    Storage::fake('local');

    foreach (range(1, 4) as $day) {
        $this->travel(1)->days();
        DatabaseBackup::create();
    }

    expect(DatabaseBackup::prune(2))->toBe(2)
        ->and(DatabaseBackup::list())->toHaveCount(2);
});

test('plan pages say rates are targets, not guarantees, and link the risk disclosure', function () {
    Plan::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('invest'))
        ->assertSee('Rates are targets, not guarantees')
        ->assertSee(route('legal.risk'))
        ->assertSee('They are not guaranteed: you could earn less or lose money.');

    $this->get(route('home'))->assertSee('Rates shown are targets, not guarantees.');
});
