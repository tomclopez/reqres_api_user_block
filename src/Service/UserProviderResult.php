<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Service;

class UserProviderResult implements UserProviderResultInterface
{
    /**
     * @param int $page
     * @param int $perPage
     * @param int $total
     * @param int $totalPages
     * @param \Drupal\reqres_api_user_block\Data\UserInterface[] $users
     */
    public function __construct(
        private readonly int $page,
        private readonly int $perPage,
        private readonly int $total,
        private readonly int $totalPages,
        private readonly array $users,
    ) {}

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    public function isEmpty(): bool
    {
        return empty($this->users);
    }
}
