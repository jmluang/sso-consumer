<?php

declare(strict_types=1);

return [
    'page_title' => 'SSO Login Failed',
    'error_code_label' => 'Error code',
    'action_return_to_portal' => 'Return to SSO portal',
    'action_password_login' => 'Sign in with password',

    'generic' => 'An unexpected error occurred during login. Please try again.',
    'ticket_missing' => 'No login ticket received. Please return to the SSO portal.',
    'ticket_invalid' => 'The login ticket is invalid or has been tampered with.',
    'ticket_expired' => 'The login ticket has expired. Please sign in again.',
    'ticket_replayed' => 'This login ticket has already been used. Please start a new SSO session.',
    'ticket_version_unsupported' => 'This ticket version is not supported. Please ask an administrator to upgrade.',
    'audience_mismatch' => 'This login ticket is not for the current system.',
    'tenant_mismatch' => 'This login ticket does not match the current domain.',
    'user_not_found' => 'Your account is not registered in this system. Please contact an administrator.',
    'resolver_failed' => 'Login processing failed. Please try again or contact an administrator.',
];
