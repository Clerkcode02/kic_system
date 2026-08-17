<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Domain\Booking\Models\Booking;
use App\Domain\Platform\Services\CaptchaVerifier;
use App\Support\ValueObjects\BookingActor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One endpoint, two actor kinds (SRS §6.1). An authenticated caller books
 * as themselves; anyone else books as a guest and must supply the contact
 * triple. Sending guest fields *as* an authenticated user is a 422, not a
 * silently-ignored field — the two shapes are mutually exclusive, mirroring
 * the `bookings_exactly_one_actor` CHECK constraint.
 */
class StoreBookingRequest extends FormRequest
{
    /**
     * Canada-only launch (CLAUDE.md §5 "Market scope") — ANA NAN, with or
     * without the conventional space.
     */
    private const CANADIAN_POSTAL_CODE = '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ -]?\d[ABCEGHJ-NPRSTV-Z]\d$/i';

    /**
     * @var list<string>
     */
    private const CANADIAN_PROVINCES = [
        'AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT',
    ];

    public function authorize(): bool
    {
        $user = $this->user('sanctum');

        // Anonymous callers are authorized by the endpoint being public;
        // abuse is handled by throttle:guest-booking, the per-email open
        // booking cap, and the captcha seam — not by a Policy, since there
        // is no identity for one to check.
        if ($user === null) {
            return true;
        }

        return $user->can('create', Booking::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isGuest = $this->user('sanctum') === null;

        return [
            'service_id' => ['required', 'uuid', 'exists:services,id'],

            // Saved addresses are a registered-user feature; a guest has no
            // addresses row and must supply the address inline.
            'address_id' => [$isGuest ? 'prohibited' : 'nullable', 'uuid', 'exists:addresses,id'],

            'service_address' => [$this->requiresInlineAddress() ? 'required' : 'nullable', 'array'],
            'service_address.line1' => ['required_with:service_address', 'string', 'max:255'],
            'service_address.line2' => ['nullable', 'string', 'max:255'],
            'service_address.city' => ['required_with:service_address', 'string', 'max:120'],
            'service_address.province' => ['required_with:service_address', 'string', 'in:'.implode(',', self::CANADIAN_PROVINCES)],
            'service_address.postal_code' => ['required_with:service_address', 'string', 'regex:'.self::CANADIAN_POSTAL_CODE],
            'service_address.lat' => ['required_with:service_address', 'numeric', 'between:41,84'],
            'service_address.lng' => ['required_with:service_address', 'numeric', 'between:-142,-52'],

            // Mirrors CreateBookingRequest::assertNotInThePast — the Action
            // is still the source of truth (it also checks the time slot
            // against "now" when the date is today).
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot_start' => ['required', 'date_format:H:i:s'],
            'time_slot_end' => ['required', 'date_format:H:i:s', 'after:time_slot_start'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Guest contact triple — required together for an anonymous
            // caller, prohibited outright for an authenticated one.
            'guest_name' => [$isGuest ? 'required' : 'prohibited', 'string', 'max:120'],
            // Plain `email`, matching every other email field in the app.
            // Not `dns`: a guest's tracking link is their only way back to
            // the booking, and a DNS lookup failing at submit time would
            // reject a legitimate booking over a transient resolver issue.
            'guest_email' => [$isGuest ? 'required' : 'prohibited', 'string', 'email', 'max:255'],
            'guest_phone' => [$isGuest ? 'required' : 'prohibited', 'string', 'max:32'],

            'captcha_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guest_name.prohibited' => 'Guest contact details cannot be supplied on an authenticated booking.',
            'guest_email.prohibited' => 'Guest contact details cannot be supplied on an authenticated booking.',
            'guest_phone.prohibited' => 'Guest contact details cannot be supplied on an authenticated booking.',
            'address_id.prohibited' => 'A saved address requires an account. Provide the service address directly.',
            'service_address.postal_code.regex' => 'Enter a valid Canadian postal code.',
            'service_address.province.in' => 'Enter a valid Canadian province or territory.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->isEmpty()) {
                return;
            }

            $captcha = app(CaptchaVerifier::class);

            if ($this->user('sanctum') === null
                && $captcha->isEnabled()
                && ! $captcha->verify($this->input('captcha_token'), $this->ip())) {
                $validator->errors()->add('captcha_token', 'Captcha verification failed. Please try again.');
            }
        });
    }

    /**
     * The one place the request decides which actor kind it carries.
     */
    public function actor(): BookingActor
    {
        $user = $this->user('sanctum');

        if ($user !== null) {
            return BookingActor::user($user);
        }

        return BookingActor::guest(
            (string) $this->validated('guest_name'),
            (string) $this->validated('guest_email'),
            (string) $this->validated('guest_phone'),
        );
    }

    private function requiresInlineAddress(): bool
    {
        return $this->user('sanctum') === null || blank($this->input('address_id'));
    }
}
