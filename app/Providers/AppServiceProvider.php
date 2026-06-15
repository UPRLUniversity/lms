<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Policies\EnrollmentPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The super-admin passes every authorization check. Returning null for
        // everyone else lets the normal policy/permission logic run.
        //
        // Exception: a super-admin must not be able to deactivate their own
        // account (self-lockout). For that self-targeted ability we defer to the
        // policy, which forbids acting on self. (Self role-change is blocked in the
        // user controller / update request, since assignRoles carries no model.)
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if (! $user->hasRole(Role::SuperAdmin->value)) {
                return null;
            }

            $target = $arguments[0] ?? null;
            if ($ability === 'setActiveStatus' && $target instanceof User && $user->is($target)) {
                return null;
            }

            return true;
        });

        // Ability for "may this user grant the named role?" — backed by the policy
        // so the privilege-escalation rule lives in one place.
        Gate::define('grantRole', [UserPolicy::class, 'grantRole']);

        // Course-scoped enrolment abilities live in EnrollmentPolicy but take a
        // Course (whose own policy is CoursePolicy), so they're registered as named
        // gates. Enrollment-instance abilities (approve/reject/withdraw) resolve to
        // EnrollmentPolicy through normal auto-discovery and need no registration.
        Gate::define('viewRoster', [EnrollmentPolicy::class, 'viewRoster']);
        Gate::define('manageRoster', [EnrollmentPolicy::class, 'manageRoster']);
        Gate::define('enrollOthers', [EnrollmentPolicy::class, 'enrollOthers']);
        Gate::define('approveEnrollments', [EnrollmentPolicy::class, 'approveEnrollments']);

        $this->brandedAuthMail();
    }

    /**
     * Warm, on-brand copy for the framework's auth e-mails. The visual branding
     * (crimson header, serif headings, gold motto) comes from the markdown theme;
     * here we set the voice. Buttons inherit the theme's primary (crimson) colour.
     */
    protected function brandedAuthMail(): void
    {
        VerifyEmail::toMailUsing(function ($notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Confirm your email · '.config('brand.name'))
                ->greeting('Welcome to '.config('brand.short').'!')
                ->line('You\'re one step away from your '.config('brand.university').
                    ' account. Please confirm this is your email address so we can keep it secure.')
                ->action('Verify email address', $url)
                ->line('This link expires shortly. If you didn\'t create an account, no action is needed — you can safely ignore this email.');
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Reset your password · '.config('brand.name'))
                ->greeting('Hello,')
                ->line('We received a request to reset the password for your '.config('brand.short').' account.')
                ->action('Reset password', $url)
                ->line('This link expires in '.$minutes.' minutes.')
                ->line('If you didn\'t request a password reset, you can ignore this email — your password stays unchanged.');
        });
    }
}
