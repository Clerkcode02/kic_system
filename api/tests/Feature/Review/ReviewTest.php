<?php

declare(strict_types=1);

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Review\Actions\SubmitReview;
use App\Domain\Review\Models\Review;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ConflictException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

/**
 * @return array{0: User, 1: User, 2: Business, 3: Booking}
 */
function completedBookingWithParties(): array
{
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    $providerUser = User::factory()->provider()->create();
    $providerUser->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $providerUser->id]);

    $booking = Booking::factory()->completed()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
    ]);

    return [$customer, $providerUser, $business, $booking];
}

/**
 * @return array{0: User, 1: User, 2: Project}
 */
function completedProjectWithParties(): array
{
    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);

    $freelancerUser = User::factory()->freelancer()->create();
    $freelancerUser->assignRole(RoleName::Freelancer->value);
    $freelancer = FreelancerProfile::factory()->approved()->create(['user_id' => $freelancerUser->id]);

    $project = Project::factory()->create(['client_id' => $client->id, 'status' => ProjectStatus::Completed]);
    $proposal = Proposal::factory()->accepted()->create(['project_id' => $project->id, 'freelancer_id' => $freelancer->id]);
    Contract::factory()->completed()->create(['project_id' => $project->id, 'proposal_id' => $proposal->id]);

    return [$client, $freelancerUser, $project];
}

it('lets the customer review a completed booking, and syncs the provider rating average', function () {
    // QUEUE_CONNECTION=sync in the test environment (phpunit.xml), so
    // SyncBusinessRatingAverageJob already ran by the time the request
    // returns — no Queue::fake()/manual dispatch needed.
    [$customer, , $business, $booking] = completedBookingWithParties();

    forgetAuthGuards();
    $token = $customer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/bookings/{$booking->id}/reviews", [
            'rating' => 5,
            'comment' => 'Great work.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.reviewable_type', 'booking');

    expect($business->fresh()->rating_avg)->toEqual('5.00');
});

it('rejects a booking review before completion', function () {
    [$customer] = completedBookingWithParties();
    $business = Business::factory()->verified()->create();
    $pendingBooking = Booking::factory()->pending()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
    ]);

    forgetAuthGuards();
    $token = $customer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/bookings/{$pendingBooking->id}/reviews", ['rating' => 4])
        ->assertStatus(409)
        ->assertJsonPath('code', 'booking_not_completed');
});

it('rejects a duplicate review of the same booking', function () {
    [$customer, , , $booking] = completedBookingWithParties();

    forgetAuthGuards();
    $token = $customer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/bookings/{$booking->id}/reviews", ['rating' => 4])
        ->assertCreated();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/bookings/{$booking->id}/reviews", ['rating' => 3])
        ->assertStatus(409)
        ->assertJsonPath('code', 'duplicate_review');
});

it('denies a stranger from reviewing a booking that is not theirs', function () {
    [, , , $booking] = completedBookingWithParties();
    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(RoleName::Customer->value);

    forgetAuthGuards();
    $token = $stranger->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/bookings/{$booking->id}/reviews", ['rating' => 5])
        ->assertForbidden();
});

it('rejects an unauthenticated review request', function () {
    [, , , $booking] = completedBookingWithParties();

    $this->postJson("/api/v1/bookings/{$booking->id}/reviews", ['rating' => 5])->assertUnauthorized();
});

it('rejects an invalid rating', function () {
    [$customer, , , $booking] = completedBookingWithParties();

    forgetAuthGuards();
    $token = $customer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/bookings/{$booking->id}/reviews", ['rating' => 6])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating']);
});

it('rejects a self-review at the Action level', function () {
    [$customer] = completedBookingWithParties();
    $booking = Booking::factory()->completed()->create(['customer_id' => $customer->id]);

    expect(fn () => app(SubmitReview::class)->handle($customer, $customer, $booking, 5, null))
        ->toThrow(ConflictException::class);
});

it('lets the client review a completed project', function () {
    [$client, , $project] = completedProjectWithParties();

    forgetAuthGuards();
    $token = $client->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/projects/{$project->id}/reviews", ['rating' => 4])
        ->assertCreated()
        ->assertJsonPath('data.reviewable_type', 'project');
});

it('lets the reviewee reply to a review once, and rejects a second reply', function () {
    [$customer, $providerUser, , $booking] = completedBookingWithParties();

    forgetAuthGuards();
    $customerToken = $customer->createToken('device')->plainTextToken;
    $reviewId = $this->withHeader('Authorization', "Bearer {$customerToken}")
        ->postJson("/api/v1/bookings/{$booking->id}/reviews", ['rating' => 5])
        ->json('data.id');

    forgetAuthGuards();
    $providerToken = $providerUser->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$providerToken}")
        ->postJson("/api/v1/reviews/{$reviewId}/reply", ['reply' => 'Thank you!'])
        ->assertOk()
        ->assertJsonPath('data.provider_reply', 'Thank you!');

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$providerToken}")
        ->postJson("/api/v1/reviews/{$reviewId}/reply", ['reply' => 'Again?'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'reply_already_exists');
});

it('denies anyone other than the reviewee from replying', function () {
    [$customer, , , $booking] = completedBookingWithParties();

    forgetAuthGuards();
    $customerToken = $customer->createToken('device')->plainTextToken;
    $reviewId = $this->withHeader('Authorization', "Bearer {$customerToken}")
        ->postJson("/api/v1/bookings/{$booking->id}/reviews", ['rating' => 5])
        ->json('data.id');

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$customerToken}")
        ->postJson("/api/v1/reviews/{$reviewId}/reply", ['reply' => 'Replying to myself.'])
        ->assertForbidden();
});

it('lists a business\'s reviews publicly', function () {
    [, , $business, $booking] = completedBookingWithParties();
    $review = Review::factory()->create([
        'reviewee_id' => $business->user_id,
        'reviewable_type' => 'booking',
        'reviewable_id' => $booking->id,
    ]);

    $this->getJson("/api/v1/businesses/{$business->id}/reviews")
        ->assertOk()
        ->assertJsonPath('data.0.id', $review->id);
});
