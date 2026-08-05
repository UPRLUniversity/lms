<?php

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| The first three keys are Laravel's own — the framework looks them up by these
| exact names, so they must keep them. Everything below is this application's.
|
*/

return [

    // Framework keys. Do not rename.
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // Sign in.
    'sign_in' => 'Sign in',
    'sign_in_heading' => 'Welcome back',
    'email' => 'Email address',
    'password_label' => 'Password',
    'remember_me' => 'Remember me',
    'forgot_password' => 'Forgot your password?',
    'new_here' => 'New to :short?',
    'create_account' => 'Create an account',

    // Register.
    'register' => 'Create your account',
    'register_subtitle' => 'Join :short and start learning.',
    'name' => 'Full name',
    'confirm_password' => 'Confirm password',
    'already_registered' => 'Already registered?',

    // Password reset.
    'forgot_heading' => 'Reset your password',
    'forgot_intro' => 'Tell us the email address on your account and we will send you a reset link.',
    'send_reset_link' => 'Email password reset link',
    'reset_heading' => 'Choose a new password',
    'reset_password' => 'Reset password',

    // Email verification.
    'verify_heading' => 'Confirm your email address',
    'verify_intro' => 'We sent a confirmation link to your email. Please open it to finish setting up your account.',
    'resend_verification' => 'Resend the confirmation email',
    'verification_sent' => 'A new confirmation link has been sent to your email address.',

    // Invitation.
    'invitation_heading' => 'Accept your invitation',
    'invitation_intro' => 'You have been invited to join :short as a :role.',
    'invitation_invalid' => 'This invitation is no longer valid',
    'invitation_invalid_hint' => 'It may have been used already or expired. Please ask for a new one.',
    'set_password_continue' => 'Set password and continue',

    // Session.
    'confirm_password_heading' => 'Confirm your password',
    'confirm_password_intro' => 'Please confirm your password before continuing.',
    'log_out' => 'Log out',
];
