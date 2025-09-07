<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Service;

interface UserProviderInterface
{
    /**
     * @param int $page
     * @param int $perPage
     *
     * @return UserProviderResultInterface
     */
    public function getUsers(int $page = 1, int $perPage = 6): UserProviderResultInterface;
}
