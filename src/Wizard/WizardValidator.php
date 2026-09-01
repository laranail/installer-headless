<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Installer\Headless\Wizard;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Installer\Headless\Contracts\Step;

/**
 * The single validation path for the wizard.
 *
 * Runs a step's own Laravel rule arrays (the one source of truth) through the
 * framework validator. Both the CLI and the web UI validate through here — the
 * web FormRequest/Livewire components reuse the same `Step::rules()`, declaring
 * none of their own. Throws Illuminate's {@see ValidationException} so front ends
 * can surface per-field errors natively.
 */
final class WizardValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> the validated subset
     *
     * @throws ValidationException
     */
    public function validate(Step $step, array $input): array
    {
        $rules = $step->rules($input);

        if ($rules === []) {
            return $input;
        }

        return Validator::make($input, $rules)->validate();
    }
}
