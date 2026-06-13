<?php

it('pest is configured correctly', function () {
    expect(true)->toBeTrue();
});

it('kalt application name is correct', function () {
    expect(config('app.name'))->toBe('KALT');
});
