<?php

namespace App\Http\Middleware;

use App\Exceptions\EscrowException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mock authentication: the caller identifies itself with an `X-User-Id` header
 * (or `?user_id=` query param). The resolved user is available as $request->user().
 * Swap for Sanctum/JWT when integrating into the real marketplace.
 */
class ResolveActingUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-User-Id', $request->query('user_id'));

        if (! $id || ! ctype_digit((string) $id)) {
            throw new EscrowException('unauthenticated', 'Provide the acting user id via the X-User-Id header.', 401);
        }

        $user = User::find($id) ?? throw new EscrowException('unauthenticated', "Acting user #{$id} does not exist.", 401);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
