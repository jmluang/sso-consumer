{{-- Default SSO consumer error page. Publish with:
     php artisan vendor:publish --tag=sso-consumer-views
     Then override at resources/views/vendor/sso-consumer/error.blade.php
--}}
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('sso-consumer::sso.page_title') }}</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; background: #f5f5f7; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; max-width: 480px; width: 90%; padding: 40px; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        h1 { margin: 0 0 16px; font-size: 20px; color: #111; }
        p { color: #555; line-height: 1.6; }
        .actions { margin-top: 32px; display: flex; gap: 12px; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; }
        .btn-primary { background: #111; color: #fff; }
        .btn-secondary { color: #555; border: 1px solid #ddd; }
        .meta { margin-top: 24px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ __('sso-consumer::sso.page_title') }}</h1>
    <p>{{ $errorMessage ?? __('sso-consumer::sso.' . ($errorCode ?? 'generic')) }}</p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ $portalUrl }}">{{ __('sso-consumer::sso.action_return_to_portal') }}</a>
        <a class="btn btn-secondary" href="{{ $loginUrl }}">{{ __('sso-consumer::sso.action_password_login') }}</a>
    </div>
    <div class="meta">
        <div>{{ __('sso-consumer::sso.error_code_label') }}: <code>{{ $errorCode }}</code></div>
        @isset($requestId)
            <div>Request ID: <code>{{ $requestId }}</code></div>
        @endisset
    </div>
</div>
</body>
</html>
