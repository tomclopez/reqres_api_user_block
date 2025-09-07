<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Service;

interface UserProviderResultInterface
{
    public function getPage(): int;

    public function getPerPage(): int;

    public function getTotal(): int;

    public function getTotalPages(): int;

    /**
     * @return \Drupal\reqres_api_user_block\Data\UserInterface[]
     */
    public function getUsers(): array;

    public function isEmpty(): bool;
}
