<?php

use App\Rules\BitcoinAddress;
use App\Support\Btc;

test('satoshis are shown as BTC without float rounding', function (int $sats, string $expected) {
    expect(Btc::format($sats))->toBe($expected);
})->with([
    [0, '0'],
    [1, '0.00000001'],
    [1_250_000, '0.0125'],
    [100_000_000, '1'],
    [2_100_000_000_000_000, '21,000,000'],
    [-50_000, '-0.0005'],
]);

test('typed BTC amounts become exact satoshis', function (string $input, ?int $expected) {
    expect(Btc::toSats($input))->toBe($expected);
})->with([
    ['0.1', 10_000_000],
    ['0.00000001', 1],
    [' 1.23456789 ', 123_456_789],
    ['12', 1_200_000_000],
    ['0.123456789', null],
    ['-1', null],
    ['1e3', null],
    ['', null],
    ['abc', null],
]);

test('valid mainnet addresses pass the checksum check', function (string $address) {
    expect(BitcoinAddress::isValid($address))->toBeTrue();
})->with([
    'legacy' => '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2',
    'script' => '3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy',
    'segwit' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
    'segwit upper case' => 'BC1QW508D6QEJXTDG4Y5R3ZARVARY0C5XW7KV8F3T4',
    'taproot' => 'bc1p5d7rjq7g6rdk2yhzks9smlaqtedr4dekq08ge8ztwac72sfr9rusxg3297',
]);

test('mistyped, testnet and fake addresses are rejected', function (string $address) {
    expect(BitcoinAddress::isValid($address))->toBeFalse();
})->with([
    'legacy typo' => '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN3',
    'segwit typo' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdx',
    'mixed case' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5MDQ',
    'testnet' => 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7kxpjzsx',
    'taproot with old checksum' => 'bc1pw508d6qejxtdg4y5r3zarvary0c5xw7kw508d6qejxtdg4y5r3zarvary0c5xw7k7grplx',
    'demo address' => 'bc1q-demo-address-do-not-send-funds',
    'ethereum' => '0x52908400098527886E0F7030069857D2E4169EE7',
    'empty' => '',
]);
