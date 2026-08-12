<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Booking\Enums\BookingPaymentStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessDocument;
use App\Domain\Business\Models\ProviderAvailability;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use App\Domain\Freelance\Enums\ProposalStatus;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\FreelancerSkill;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\PortfolioItem;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Payment\Models\Payment;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Quotation\Enums\QuotationStatus;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    // Toronto — Canada-only market for launch (CLAUDE.md §5).
    private const CITY_LAT = 43.6532;

    private const CITY_LNG = -79.3832;

    /**
     * Seed the application's database with a coherent demo dataset.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.com',
        ]);

        $this->seedPlatformSettings();

        $categories = $this->seedCategories();
        $businesses = $this->seedBusinesses($categories);
        $customers = User::factory()->customer()->count(20)->create();
        $customers->each(fn (User $customer) => Address::factory()->for($customer, 'user')->create());

        $freelancers = $this->seedFreelancers();

        $this->seedBookingsAcrossEveryStatus($customers, $businesses);
        $this->seedQuotationsInEveryState($customers, $businesses);
        $this->seedFreelanceProjects($customers, $freelancers, $categories);

        User::all()->each(function (User $user) {
            NotificationPreference::factory()->create([
                'user_id' => $user->id,
                'category' => 'booking.created',
            ]);
        });
    }

    private function seedPlatformSettings(): void
    {
        PlatformSetting::factory()->create([
            'key' => 'quotation.reminder_window_hours',
            'value' => '24',
            'type' => 'integer',
            'description' => 'Hours before valid_until to send the first customer reminder.',
        ]);

        PlatformSetting::factory()->create([
            'key' => 'booking.auto_expire_days',
            'value' => '5',
            'type' => 'integer',
            'description' => 'Days without a quotation before a booking auto-expires.',
        ]);

        PlatformSetting::factory()->create([
            'key' => 'platform.default_fee_percentage',
            'value' => '10',
            'type' => 'integer',
            'description' => 'Default platform fee percentage applied to bookings and milestones.',
        ]);
    }

    /**
     * @return Collection<int, Category>
     */
    private function seedCategories(): Collection
    {
        // 4 parents x 1 child = 8 total, two levels.
        $tree = [
            'Home Services' => ['Plumbing'],
            'Cleaning' => ['Deep Cleaning'],
            'Landscaping' => ['Lawn Care'],
            'Digital Services' => ['Web Development'],
        ];

        $leaves = collect();

        foreach ($tree as $parentName => $children) {
            $parent = Category::factory()->create(['name' => $parentName]);

            foreach ($children as $childName) {
                $leaves->push(Category::factory()->child($parent)->create(['name' => $childName]));
            }
        }

        return $leaves;
    }

    /**
     * @param  Collection<int, Category>  $leafCategories
     * @return Collection<int, Business>
     */
    private function seedBusinesses(Collection $leafCategories): Collection
    {
        return collect(range(1, 10))->map(function () use ($leafCategories) {
            $business = Business::factory()
                ->verified()
                ->nearCity(self::CITY_LAT, self::CITY_LNG)
                ->create();

            BusinessDocument::factory()->verified()->create(['business_id' => $business->id]);

            foreach (range(0, 4) as $dayOfWeek) {
                ProviderAvailability::factory()->create([
                    'business_id' => $business->id,
                    'day_of_week' => $dayOfWeek,
                ]);
            }

            Service::factory()
                ->count(fake()->numberBetween(2, 4))
                ->create([
                    'business_id' => $business->id,
                    'category_id' => fn () => $leafCategories->random()->id,
                ]);

            return $business;
        });
    }

    /**
     * @return Collection<int, FreelancerProfile>
     */
    private function seedFreelancers(): Collection
    {
        return collect(range(1, 5))->map(function () {
            $user = User::factory()->freelancer()->create();
            $profile = FreelancerProfile::factory()->approved()->create(['user_id' => $user->id]);

            FreelancerSkill::factory()
                ->count(fake()->numberBetween(2, 4))
                ->create(['freelancer_profile_id' => $profile->id]);

            PortfolioItem::factory()
                ->count(fake()->numberBetween(1, 3))
                ->create(['freelancer_profile_id' => $profile->id]);

            return $profile;
        });
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, Business>  $businesses
     */
    private function seedBookingsAcrossEveryStatus(Collection $customers, Collection $businesses): void
    {
        $paidStatuses = [
            BookingStatus::Accepted, BookingStatus::Scheduled,
            BookingStatus::InProgress, BookingStatus::Completed,
        ];

        foreach (BookingStatus::cases() as $status) {
            $provider = $businesses->random();

            $booking = Booking::factory()->create([
                'customer_id' => $customers->random()->id,
                'provider_id' => $provider->id,
                'service_id' => $provider->services()->inRandomOrder()->first()->id,
                'status' => $status,
                'payment_status' => match (true) {
                    $status === BookingStatus::Refunded => BookingPaymentStatus::Refunded,
                    in_array($status, $paidStatuses, true) => BookingPaymentStatus::Paid,
                    default => BookingPaymentStatus::Unpaid,
                },
            ]);

            if (in_array($status, $paidStatuses, true)) {
                Payment::factory()->succeeded()->create([
                    'payable_type' => 'booking',
                    'payable_id' => $booking->id,
                ]);
            }

            if ($status === BookingStatus::Completed) {
                Review::factory()->create([
                    'reviewer_id' => $booking->customer_id,
                    'reviewee_id' => $provider->user_id,
                    'reviewable_type' => 'booking',
                    'reviewable_id' => $booking->id,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, Business>  $businesses
     */
    private function seedQuotationsInEveryState(Collection $customers, Collection $businesses): void
    {
        foreach (QuotationStatus::cases() as $status) {
            $provider = $businesses->random();

            $booking = Booking::factory()->quotationSent()->create([
                'customer_id' => $customers->random()->id,
                'provider_id' => $provider->id,
                'service_id' => $provider->services()->inRandomOrder()->first()->id,
            ]);

            Quotation::factory()->state(['status' => $status])->create(['booking_id' => $booking->id]);
        }
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, FreelancerProfile>  $freelancers
     * @param  Collection<int, Category>  $leafCategories
     */
    private function seedFreelanceProjects(Collection $customers, Collection $freelancers, Collection $leafCategories): void
    {
        $digitalCategory = $leafCategories->firstWhere('name', 'Web Development') ?? $leafCategories->random();

        $projects = collect(range(1, 3))->map(fn () => Project::factory()->create([
            'client_id' => $customers->random()->id,
            'category_id' => $digitalCategory->id,
        ]));

        $projects->each(function (Project $project) use ($freelancers) {
            $freelancers->random(min(3, $freelancers->count()))->each(
                fn (FreelancerProfile $freelancer) => Proposal::factory()->create([
                    'project_id' => $project->id,
                    'freelancer_id' => $freelancer->id,
                ])
            );
        });

        // Hire on the first project only — a single active contract with milestones.
        $hiredProject = $projects->first();

        if (! $hiredProject instanceof Project) {
            return;
        }

        $hiredProposal = $hiredProject->proposals()->first();

        if (! $hiredProposal instanceof Proposal) {
            return;
        }

        $hiredProposal->update(['status' => ProposalStatus::Accepted]);

        $contract = Contract::factory()->active()->create([
            'project_id' => $hiredProject->id,
            'proposal_id' => $hiredProposal->id,
        ]);

        Milestone::factory()->paid()->create(['contract_id' => $contract->id, 'title' => 'Discovery & wireframes']);
        Milestone::factory()->approved()->create(['contract_id' => $contract->id, 'title' => 'Core build']);
        Milestone::factory()->pending()->create(['contract_id' => $contract->id, 'title' => 'Launch & handoff']);
    }
}
