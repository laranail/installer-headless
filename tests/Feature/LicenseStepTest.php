<?php

declare(strict_types=1);

use Simtabi\Laranail\Installer\Headless\Contracts\Step;
use Simtabi\Laranail\Installer\Headless\Exceptions\LicenseException;
use Simtabi\Laranail\Installer\Headless\Licensing\LicenseStepAdapter;
use Simtabi\Laranail\Installer\Headless\Steps\LicenseStep;
use Simtabi\Laranail\Installer\Headless\Steps\StepRegistry;
use Simtabi\Laranail\Installer\Headless\Support\InstallationState;
use Simtabi\Laranail\Installer\Headless\Support\InstallerContext;
use Simtabi\Laranail\Installer\Headless\Tests\Fixtures\FailingDriver;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsDomainBinding;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\Drivers\EnvatoDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\NullDriver;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\Providers\LicenceVerifierServiceProvider;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

beforeEach(function (): void {
    $this->app->register(LicenceVerifierServiceProvider::class);
    $this->state = new InstallationState;
    $this->state->clear();
});

afterEach(fn () => $this->state->clear());

it('omits the license step from the default pipeline', function (): void {
    config()->set('installer.steps.license.enabled', false);

    $keys = array_map(fn (Step $step): string => $step->key(), app(StepRegistry::class)->enabled());

    expect($keys)->not->toContain('license');
});

it('activates through license-verifier (null driver passes)', function (): void {
    config()->set('installer.license.enabled', true);
    app(LicenseManager::class)->configure(['default' => 'null', 'drivers.null.allow_in_production' => true]);

    $context = InstallerContext::fromInput([
        'purchase_code' => 'CODE-123',
        'buyer' => 'Ada',
        'app_url' => 'http://test.local',
    ]);

    new LicenseStep($this->state)->run($context);

    expect($this->state->recall('license'))->toBe('Ada')
        ->and($context->get('license'))->toBeInstanceOf(VerificationResult::class);
});

it('throws when the driver reports an invalid license', function (): void {
    config()->set('installer.license.enabled', true);
    app(DriverManager::class)->extend('failing', fn (): FailingDriver => new FailingDriver);
    app(LicenseManager::class)->configure(['default' => 'failing']);

    $run = fn () => new LicenseStep($this->state)->run(
        InstallerContext::fromInput(['purchase_code' => 'bad', 'buyer' => 'y']),
    );

    expect($run)->toThrow(LicenseException::class);
});

it('skips activation when the user opts out', function (): void {
    config()->set('installer.license.enabled', true);
    config()->set('installer.license.skippable', true);

    new LicenseStep($this->state)->run(InstallerContext::fromInput(['skip_license' => true]));

    expect($this->state->recall('license'))->toBe('skipped');
});

it('maps the transfer capability via SupportsDomainBinding (instanceof)', function (): void {
    expect(is_a(EnvatoDriver::class, SupportsDomainBinding::class, true))->toBeTrue()
        ->and(is_a(NullDriver::class, SupportsDomainBinding::class, true))->toBeFalse();

    app(LicenseManager::class)->configure(['default' => 'null', 'drivers.null.allow_in_production' => true]);

    expect(app(LicenseStepAdapter::class)->supportsTransfer())->toBeFalse();
});
