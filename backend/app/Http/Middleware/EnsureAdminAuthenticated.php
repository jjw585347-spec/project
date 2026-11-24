<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    /**
     * بررسی ورود صاحب آرایشگاه برای تمام درخواست‌های مدیریتی
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = (string) $request->bearerToken();

        if ($plainToken === '') {
            return response()->json(
                [
                    'message' => 'برای دسترسی به این بخش، ابتدا باید وارد پنل مدیریت شوید.',
                ],
                401,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $hashedToken = hash('sha256', $plainToken);
        $admin = Admin::where('api_token', $hashedToken)->first();

        if (!$admin) {
            return response()->json(
                [
                    'message' => 'نشست مدیریتی شما معتبر نیست یا منقضی شده است. لطفاً دوباره وارد شوید.',
                ],
                401,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $request->attributes->set('authenticated_admin', $admin);

        return $next($request);
    }
}
