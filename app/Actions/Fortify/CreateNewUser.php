<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private SubscriptionService $subscriptionService
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

        return DB::transaction(function () use ($input) {

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
    }
}