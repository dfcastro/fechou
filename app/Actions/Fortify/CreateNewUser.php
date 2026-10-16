<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\TurnstileVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private SubscriptionService $subscriptionService,
        private TurnstileVerifier $turnstileVerifier
    ) {
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        // Verify bot challenge before creating a user, company or subscription.
        if (! $this->turnstileVerifier->passes(
            $input['cf-turnstile-response'] ?? null,
            request()->ip()
        )) {
            throw ValidationException::withMessages([
                'turnstile' => 'Não foi possível validar o cadastro. Tente novamente.',
            ]);
        }

        $user = DB::transaction(function () use ($input) {

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $business = $user
                ->business()
                ->create([
                    'name' => $user->name,
                ]);

            $this->subscriptionService
                ->ensureDefaultSubscription(
                    $business
                );

            /*
             * Deixa a relação disponível imediatamente
             * durante a mesma requisição.
             */
            $user->setRelation(
                'business',
                $business
            );

            return $user;
        });

        // Only a completed registration should be eligible for an Ads conversion.
        // The onboarding page consumes this one-time token after the redirect.
        if (filled(config('services.google_ads.id'))
            && filled(config('services.google_ads.registration_label'))) {
            session()->put('google_ads_registration_pending', (string) Str::uuid());
        }

        return $user;
    }
}