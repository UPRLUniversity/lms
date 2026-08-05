<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Events\CourseCompleted;
use App\Listeners\IssueCertificateOnCompletion;
use App\Listeners\MergeGuestCart;
use App\Listeners\RecordAuthActivity;
use App\Listeners\RecordCourseGradeSnapshot;
use App\Models\User;
use App\Policies\CourseAnnouncementPolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\ForumPolicy;
use App\Policies\GradebookPolicy;
use App\Policies\MessagingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
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

        $this->guardProductionConfig();
        $this->registerListeners();

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

        // Gradebook abilities (Section 6.5) — same reasoning: subject is a Course.
        Gate::define('viewGradebookMatrix', [GradebookPolicy::class, 'viewMatrix']);
        Gate::define('viewOwnGradebook', [GradebookPolicy::class, 'viewOwn']);

        // Course announcements (Section 8) — same reasoning: subject is a Course.
        Gate::define('viewAnnouncements', [CourseAnnouncementPolicy::class, 'view']);
        Gate::define('manageAnnouncements', [CourseAnnouncementPolicy::class, 'manage']);

        // Re-issuing a completion snapshot is an explicit admin action (never an
        // instructor one) — the super-admin already passes via Gate::before above.
        Gate::define('recompute-gradebook', fn (User $user) => $user->hasRole(Role::Admin->value));

        // Course forums (Section 9) — subject is a Course, so named gates like the
        // roster/announcement abilities above. Instance actions (view/reply/moderate a
        // ForumThread or ForumPost) auto-discover their own policies.
        Gate::define('accessForum', [ForumPolicy::class, 'access']);
        Gate::define('participateInForum', [ForumPolicy::class, 'participate']);
        Gate::define('moderateForum', [ForumPolicy::class, 'moderate']);

        // Messaging (Section 9) — "may this user start a conversation?". Who-may-talk-to
        // -whom is enforced in MessagingService; these gate the capability. messageCourse
        // takes a Course; the others take the actor.
        Gate::define('useMessaging', [MessagingPolicy::class, 'use']);
        Gate::define('createGroupConversation', [MessagingPolicy::class, 'createGroup']);
        Gate::define('messageCourseGroup', [MessagingPolicy::class, 'messageCourse']);

        // Forum moderation queue: only admins review reported posts (super-admin passes
        // via Gate::before above).
        Gate::define('reviewForumReports', fn (User $user) => $user->hasRole(Role::Admin->value));

        /*
        | Administration (Section 15).
        |
        | System settings reach branding, security and money formatting across the
        | whole institution, so they are super-admin only — deliberately narrower
        | than the day-to-day admin role, which manages people and courses. The
        | super-admin arrives here via Gate::before; the explicit permission check
        | is what makes the boundary visible (and testable) rather than implied.
        |
        | The audit trail is read-only for everyone, including the roles that can
        | reach it: there is no ability to write or erase an entry, at any level.
        */
        Gate::define('manage-settings', fn (User $user) => $user->can(Permission::SettingsManage->value));
        Gate::define('view-audit-log', fn (User $user) => $user->can(Permission::AuditView->value));

        $this->brandedAuthMail();
    }

    /**
     * Refuse to run in production with debug on (Section 15 hardening sweep).
     *
     * APP_DEBUG=true in production turns every uncaught exception into a public page
     * listing environment variables — database password, mail credentials, APP_KEY. It
     * is the single highest-severity misconfiguration this stack has, and the usual
     * defence ("remember to set it") is exactly the one that fails on a rushed deploy.
     *
     * .env.example deliberately still ships APP_DEBUG=true, because it is the LOCAL
     * template and a developer needs the error page. This guard is what makes that safe:
     * copying the local template to a production box fails loudly and immediately,
     * rather than running and leaking. See docs/production.md and .env.production.example.
     */
    protected function guardProductionConfig(): void
    {
        if ($this->app->isProduction() && config('app.debug')) {
            throw new \RuntimeException(
                'APP_DEBUG is enabled while APP_ENV=production. Refusing to boot: debug mode '
                .'exposes environment variables, including database and mail credentials, on '
                .'any error page. Set APP_DEBUG=false. See docs/production.md.'
            );
        }
    }

    /**
     * EVERY event listener in the application, in one place.
     *
     * Laravel's automatic listener discovery is switched off in bootstrap/app.php — see
     * the note there — so this method is the whole picture. A listener that is not
     * registered here does not run.
     */
    protected function registerListeners(): void
    {
        // A signed-out visitor's cart follows them into their account on login, so the
        // "add to cart, sign in at checkout" journey does not lose their basket.
        Event::listen(Login::class, MergeGuestCart::class);

        // Finishing a course issues the certificate and freezes the grade snapshot.
        // Previously reached only through discovery; registered explicitly now.
        Event::listen(CourseCompleted::class, IssueCertificateOnCompletion::class);
        Event::listen(CourseCompleted::class, RecordCourseGradeSnapshot::class);

        /*
        | Authentication into the audit trail (Section 15). Listened for rather than
        | hooked into the login controller, so EVERY path that authenticates is
        | covered — the login form, an invitation acceptance, a remembered session,
        | anything added later — not just the one door somebody instrumented.
        */
        Event::listen(Login::class, [RecordAuthActivity::class, 'handleLogin']);
        Event::listen(Logout::class, [RecordAuthActivity::class, 'handleLogout']);
        Event::listen(Failed::class, [RecordAuthActivity::class, 'handleFailed']);
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
