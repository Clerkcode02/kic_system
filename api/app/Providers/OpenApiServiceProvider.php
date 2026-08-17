<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

/**
 * SRS §6.1: documents the guest surface's own credential.
 *
 * Scramble's `security_strategy` (config/scramble.php) infers bearer auth
 * from the `api.protected` / `auth:sanctum` middleware, which the guest
 * routes deliberately don't use — they're authorized by a booking access
 * token in a header instead. Without this, the `/guest/*` operations would
 * be published as fully public, and a generated client would omit the one
 * header that makes them work.
 */
class OpenApiServiceProvider extends ServiceProvider
{
    private const SCHEME_NAME = 'bookingToken';

    private const HEADER = 'X-Booking-Token';

    /**
     * Segment identifying the token-authorized surface. Matched rather than
     * anchored because Scramble emits paths with the API version prefix
     * (`/v1/guest/...`), which would break a `str_starts_with` check the
     * moment the prefix changes.
     */
    private const GUEST_SEGMENT = '/guest/';

    public function boot(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            $scheme = SecurityScheme::apiKey('header', self::HEADER)
                ->setDescription(
                    'A guest booking access token (SRS §6.1). Issued once when a guest booking is '
                    .'created and emailed as a tracking link; only its sha256 is stored server-side. '
                    .'Scoped to a single booking — presenting it for any other booking, or after it '
                    .'expires or is revoked by an account claim, returns 404 (never 403).'
                );

            // Register the scheme without making it a global requirement:
            // secure() would apply it to every operation in the document.
            $openApi->components->addSecurityScheme(self::SCHEME_NAME, $scheme);

            foreach ($openApi->paths as $path) {
                if (! $this->isGuestPath($path)) {
                    continue;
                }

                foreach ($path->operations as $operation) {
                    $this->requireBookingToken($operation);
                }
            }
        });
    }

    private function isGuestPath(Path $path): bool
    {
        // The lookup endpoint is genuinely public — it is what a guest uses
        // when they have *lost* their token — so it must stay unsecured.
        return str_contains('/'.ltrim($path->path, '/'), self::GUEST_SEGMENT)
            && ! str_contains($path->path, 'bookings/lookup');
    }

    private function requireBookingToken(Operation $operation): void
    {
        // Replaces rather than appends: the middleware strategy may have
        // marked these public (`security: []`), and leaving that in place
        // would tell a client the header is optional.
        $operation->security = [new SecurityRequirement([self::SCHEME_NAME => []])];
    }
}
