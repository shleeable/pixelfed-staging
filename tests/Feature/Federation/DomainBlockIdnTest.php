<?php

use App\Models\User;
use App\Models\UserDomainBlock;
use App\Services\AccountService;
use App\Util\ActivityPub\Helpers;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;

uses(LazilyRefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| IDN domain block matching
|--------------------------------------------------------------------------
|
| A domain block stored in one wire form (Unicode U-label or punycode A-label)
| must still filter an actor whose profiles.domain was stored in the other
| form. Enforcement compares against equivalent forms so the block does not
| silently no-op for IDN instances.
|
*/

const IDN_UNICODE = 'президент.рф';
const IDN_PUNYCODE = 'xn--d1abbgf6aiiy.xn--p1ai';

it('expands a host into equivalent unicode and punycode forms symmetrically', function () {
    $fromUnicode = Helpers::domainEquivalentForms(IDN_UNICODE);
    $fromPuny = Helpers::domainEquivalentForms(IDN_PUNYCODE);

    expect($fromUnicode)->toContain(IDN_UNICODE)
        ->and($fromUnicode)->toContain(IDN_PUNYCODE)
        ->and($fromPuny)->toContain(IDN_UNICODE)
        ->and($fromPuny)->toContain(IDN_PUNYCODE);
});

it('matches a punycode block row against a unicode actor domain', function () {
    $user = User::factory()->create();
    $user->refresh();

    // Block stored as punycode (canonical form).
    UserDomainBlock::create([
        'profile_id' => $user->profile_id,
        'domain' => IDN_PUNYCODE,
    ]);

    // Actor domain stored as the Unicode wire form.
    expect(AccountService::blocksDomain($user->profile_id, IDN_UNICODE))->toBeTrue();
    // And the punycode form still matches.
    expect(AccountService::blocksDomain($user->profile_id, IDN_PUNYCODE))->toBeTrue();
});

it('matches a unicode block row against a punycode actor domain', function () {
    $user = User::factory()->create();
    $user->refresh();

    // A pre-existing block row stored in the Unicode form (the buggy old form).
    UserDomainBlock::create([
        'profile_id' => $user->profile_id,
        'domain' => IDN_UNICODE,
    ]);

    expect(AccountService::blocksDomain($user->profile_id, IDN_PUNYCODE))->toBeTrue();
    expect(AccountService::blocksDomain($user->profile_id, IDN_UNICODE))->toBeTrue();
});

it('does not match an unrelated domain', function () {
    $user = User::factory()->create();
    $user->refresh();

    UserDomainBlock::create([
        'profile_id' => $user->profile_id,
        'domain' => IDN_PUNYCODE,
    ]);

    expect(AccountService::blocksDomain($user->profile_id, 'example.com'))->toBeFalse();
});

it('stores an IDN block in canonical punycode form via the API', function () {
    // Pre-seed the DNS resolution cache so validateUrl treats the host as
    // publicly resolvable without a real lookup.
    Cache::put(
        'helpers:url:public-ips:'.hash('xxh128', IDN_PUNYCODE),
        ['203.0.113.30'],
        3600
    );

    $user = User::factory()->create();
    $user->refresh();
    Passport::actingAs($user, ['write', 'follow']);

    $this->postJson('/api/v1/domain_blocks', ['domain' => 'https://'.IDN_UNICODE])
        ->assertOk();

    expect(UserDomainBlock::whereProfileId($user->profile_id)->value('domain'))->toBe(IDN_PUNYCODE);
});
