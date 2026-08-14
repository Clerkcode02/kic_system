<?php

declare(strict_types=1);

namespace App\Domain\Notification\Queries;

use App\Domain\User\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Notifications\DatabaseNotification;

final class ListNotificationsQuery
{
    private const PER_PAGE = 20;

    /**
     * @return CursorPaginator<int, DatabaseNotification>
     */
    public function handle(User $user): CursorPaginator
    {
        return $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
