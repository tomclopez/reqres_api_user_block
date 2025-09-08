<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\reqres_api_user_block\Data\User;
use Drupal\reqres_api_user_block\Event\UserListEvent;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Service for fetching users from ReqRes API.
 */
class ReqResApiService implements UserProviderInterface
{
    private const string BASE_URL = "https://reqres.in/api";

    private const string USERS_ENDPOINT = "/users";

    private ClientInterface $httpClient;

    private CacheBackendInterface $cache;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ClientInterface $httpClient,
        CacheBackendInterface $cache,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getUsers(
        int $page = 1,
        int $perPage = 6,
        int $cacheLifetime = 300,
        array $blockConfig = [],
    ): UserProviderResultInterface {
        $cacheKey = "reqres_api_users:page_{$page}:per_page_{$perPage}";

        if ($cacheLifetime > 0) {
            $cached = $this->cache->get($cacheKey);
            if ($cached && $cached->valid) {
                return $cached->data;
            }
        }

        try {
            $response = $this->httpClient->request(
                "GET",
                self::BASE_URL . self::USERS_ENDPOINT,
                [
                    "query" => [
                        "page" => $page,
                        "per_page" => $perPage,
                    ],
                    "headers" => [
                        "x-api-key" => "reqres-free-v1",
                    ],
                    "timeout" => 10,
                ],
            );

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                return $this->getEmptyResponse($page, $perPage);
            }

            if (
                !isset(
                    $data["page"],
                    $data["per_page"],
                    $data["total"],
                    $data["total_pages"],
                    $data["data"],
                ) ||
                !is_array($data["data"])
            ) {
                return $this->getEmptyResponse($page, $perPage);
            }

            $users = [];
            foreach ($data["data"] as $userData) {
                if (
                    isset(
                        $userData["id"],
                        $userData["email"],
                        $userData["first_name"],
                        $userData["last_name"],
                        $userData["avatar"],
                    )
                ) {
                    $users[] = new User(
                        $userData["id"],
                        $userData["email"],
                        $userData["first_name"],
                        $userData["last_name"],
                        $userData["avatar"],
                    );
                }
            }

            $context = [
                "page" => $page,
                "per_page" => $perPage,
                "total" => $data["total"],
                "total_pages" => $data["total_pages"],
                "cache_lifetime" => $cacheLifetime,
                "block_config" => $blockConfig,
            ];

            $event = new UserListEvent($users, $context);
            $this->eventDispatcher->dispatch($event, UserListEvent::NAME);

            $filteredUsers = $event->getUsers();

            $result = new UserProviderResult(
                $data["page"],
                $data["per_page"],
                $data["total"],
                $data["total_pages"],
                $filteredUsers,
            );

            if ($cacheLifetime > 0) {
                $this->cache->set($cacheKey, $result, time() + $cacheLifetime, [
                    "reqres_api_users",
                ]);
            }

            return $result;
        } catch (GuzzleException $e) {
            return $this->getEmptyResponse($page, $perPage);
        }
    }

    private function getEmptyResponse(
        int $page,
        int $perPage,
    ): UserProviderResultInterface {
        return new UserProviderResult($page, $perPage, 0, 0, []);
    }
}
