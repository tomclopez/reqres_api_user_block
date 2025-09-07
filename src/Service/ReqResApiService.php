<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Service;

use Drupal\reqres_api_user_block\Data\User;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for fetching users from ReqRes API.
 */
class ReqResApiService implements UserProviderInterface
{
    private const string BASE_URL = "https://reqres.in/api";

    private const string USERS_ENDPOINT = "/users";

    private ClientInterface $httpClient;

    public function __construct(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getUsers(
        int $page = 1,
        int $perPage = 6,
    ): UserProviderResultInterface {
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

            return new UserProviderResult(
                $data["page"],
                $data["per_page"],
                $data["total"],
                $data["total_pages"],
                $users,
            );
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
