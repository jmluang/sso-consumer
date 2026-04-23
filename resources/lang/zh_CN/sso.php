<?php

declare(strict_types=1);

return [
    'page_title' => 'SSO 登录失败',
    'error_code_label' => '错误码',
    'action_return_to_portal' => '返回 SSO 重新选择',
    'action_password_login' => '使用账号密码登录',

    // Error messages keyed by error code (see docs/sso/contracts/error-codes.md)
    'generic' => '登录过程中发生了一个意外错误，请稍后重试。',
    'ticket_missing' => '未收到登录凭证，请返回 SSO 入口重试。',
    'ticket_invalid' => '登录凭证无效，可能已被篡改。',
    'ticket_expired' => '登录凭证已过期，请返回 SSO 入口重新登录。',
    'ticket_replayed' => '登录凭证已被使用，请重新发起 SSO 登录。',
    'ticket_version_unsupported' => '当前业务系统不支持该版本的登录凭证，请联系管理员升级。',
    'audience_mismatch' => '登录凭证不属于当前业务系统。',
    'tenant_mismatch' => '登录凭证与当前访问域名不匹配。',
    'user_not_found' => '本系统未录入您的账号，请联系管理员授权。',
    'resolver_failed' => '登录处理失败，请稍后重试或联系管理员。',
];
