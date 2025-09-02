<?php

namespace App;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App as AppFacade;
use App\Http\Controllers\ApiController;

class E2EHelper
{
    /**
     * Auto-login a user for each request using config('e2e.user_id').
     */
    public static function autoLogin(): void
    {
        $userId = (int) (Config::get('e2e.user_id') ?? 0);
        if ($userId > 0) {
            Auth::loginUsingId($userId);
        }
    }

    /**
     * Swap the external API controller with a fake implementation for E2E tests.
     */
    public static function swapApiController(): void
    {
        AppFacade::bind(ApiController::class, \App\Http\Controllers\FakeApiController::class);
    }

    /**
     * Return minimal estimate data (status + approval_flow) as an array for E2E assertions.
     *
     * @return array{status:string, approval_flow:array|null}
     */
    public static function findEstimate(int $id): array
    {
        /** @var \App\Models\Estimate|null $e */
        $e = \App\Models\Estimate::find($id);
        if (!$e) return ['status' => 'missing', 'approval_flow' => null];
        // Eloquent casts approval_flow to array
        return [
            'status' => (string) $e->status,
            'approval_flow' => is_array($e->approval_flow) ? $e->approval_flow : null,
        ];
    }
}
