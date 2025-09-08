<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Event;

use Drupal\reqres_api_user_block\Data\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when user list is being prepared for display.
 *
 * This event allows other modules to filter, modify, or replace the list
 * of users before they are rendered in the block.
 */
class UserListEvent extends Event
{
    public const string NAME = "reqres_api_user_block.users.pre_render";

    /**
     * @param UserInterface[] $users
     * @param array $context
     */
    public function __construct(
        private array $users,
        private readonly array $context,
    ) {}

    /**
     * @return UserInterface[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * @param UserInterface[] $users
     */
    public function setUsers(array $users): void
    {
        $this->users = $users;
    }

    public function addUser(UserInterface $user): void
    {
        $this->users[] = $user;
    }

    public function removeUserById(int $id): void
    {
        $this->users = array_filter(
            $this->users,
            static fn(UserInterface $user): bool => $user->getId() !== $id,
        );
    }

    public function filterUsers(callable $callback): void
    {
        $this->users = array_filter($this->users, $callback);
    }

    /**
     * Context includes: page, per_page, total, cache_lifetime, block_config
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    public function getPage(): int
    {
        return $this->getContextValue("page", 1);
    }

    public function getPerPage(): int
    {
        return $this->getContextValue("per_page", 6);
    }

    public function getTotal(): int
    {
        return $this->getContextValue("total", 0);
    }

    public function getBlockConfig(): array
    {
        return $this->getContextValue("block_config", []);
    }

    public function getUserCount(): int
    {
        return count($this->users);
    }

    public function isEmpty(): bool
    {
        return empty($this->users);
    }
}
