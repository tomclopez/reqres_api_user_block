<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Service;

interface UserProviderInterface
{
    /**
     * @param int $page
     * @param int $perPage
     * @param int $cacheLifetime
     * @param array $blockConfig
     *
     * @return UserProviderResultInterface
     */
    public function getUsers(
        int $page = 1,
        int $perPage = 6,
        int $cacheLifetime = 300,
        array $blockConfig = [],
    ): UserProviderResultInterface;
}
