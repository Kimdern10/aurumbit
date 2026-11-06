<?php

use App\Enums\WithdrawalNetwork;
use App\Rules\UsdtAddress;
use App\Support\Usdt;

test('micros are shown as USDT rounded down to cents', function (int $micros, string $expected) {
    expect(Usdt::format($micros))->toBe($expected);
})->with([
    [0, '0.00'],
    [1, '0.00'],
    [9_999, '0.00'],
    [10_000, '0.01'],
    [1_234_567_890, '1,234.56'],
    [-50_000_000, '-50.00'],
]);

test('typed USDT amounts become exact micros', function (string $input, ?int $expected) {
    expect(Usdt::toMicros($input))->toBe($expected);
})->with([
    ['100', 100_000_000],
    ['0.01', 10_000],
    [' 1,250.5 ', 1_250_500_000],
    ['0.001', null],
    ['-1', null],
    ['1e3', null],
    ['', null],
]);

test('coin amounts and satoshis convert to USDT without float rounding', function () {
    expect(Usdt::fromSats(100_000_000, 7_672_800))->toBe(76_728_000_000)
        ->and(Usdt::fromSats(1, 7_672_800))->toBe(767)
        ->and(Usdt::fromCoin('0.5', 250_000))->toBe(1_250_000_000)
        ->and(Usdt::fromCoin('0.123456789', 250_000))->toBeNull()
        ->and(Usdt::toUsdCents(1_234_567_890))->toBe(123_456);
});

test('USDT addresses are checked for their network', function (WithdrawalNetwork $network, string $address, bool $valid) {
    expect(UsdtAddress::isValid($address, $network))->toBe($valid);
})->with([
    [WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', true],
    [WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6u', false],
    [WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6', false],
    [WithdrawalNetwork::Trc20, '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2', false],
    [WithdrawalNetwork::Erc20, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', true],
    [WithdrawalNetwork::Erc20, '0x742d35Cc6634C0532925a3b844Bc454e4438f44', false],
    [WithdrawalNetwork::Erc20, '742d35Cc6634C0532925a3b844Bc454e4438f44e00', false],
]);
