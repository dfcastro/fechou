<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerifyEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_uses_fechou_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()
            ->unverified()
            ->create();

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class
        );
    }
}
