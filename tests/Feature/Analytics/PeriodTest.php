<?php

use App\Support\Period;
use Carbon\CarbonImmutable;

it('membangun rentang inklusif sepanjang jumlah hari yang diminta', function () {
    CarbonImmutable::setTestNow('2026-08-20');

    $period = Period::fromKey('7');

    expect($period->fromDate())->toBe('2026-08-14')
        ->and($period->untilDate())->toBe('2026-08-20')
        ->and($period->days())->toBe(7);
});

it('menggeser periode pembanding tepat sepanjang periode itu sendiri', function () {
    CarbonImmutable::setTestNow('2026-08-20');

    $previous = Period::fromKey('7')->previous();

    expect($previous->fromDate())->toBe('2026-08-07')
        ->and($previous->untilDate())->toBe('2026-08-13');
});

it('membalik rentang kustom yang tertukar urutannya', function () {
    $period = Period::fromKey('custom', '2026-08-20', '2026-08-01');

    expect($period->fromDate())->toBe('2026-08-01')
        ->and($period->untilDate())->toBe('2026-08-20');
});

it('menolak rentang kustom tanpa tanggal', function () {
    Period::fromKey('custom');
})->throws(InvalidArgumentException::class);

it('jatuh ke 30 hari untuk kunci yang tidak dikenal', function () {
    expect(Period::fromKey('sembarang')->key)->toBe('30');
});

afterEach(fn () => CarbonImmutable::setTestNow());
