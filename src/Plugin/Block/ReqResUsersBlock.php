<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\reqres_api_user_block\Service\ReqResApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a ReqRes Users block.
 *
 * @Block(
 *   id = "reqres_users_block",
 *   admin_label = @Translation("ReqRes Users"),
 *   category = @Translation("Custom")
 * )
 */
class ReqResUsersBlock extends BlockBase implements
    ContainerFactoryPluginInterface
{
    /**
     * The ReqRes API service.
     */
    protected ReqResApiService $reqresApiService;

    /**
     * Constructs a new ReqResUsersBlock instance.
     *
     * @param array $configuration
     * @param string $plugin_id
     * @param mixed $plugin_definition
     * @param \Drupal\reqres_api_user_block\Service\ReqResApiService $reqres_api_service
     */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        ReqResApiService $reqres_api_service,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->reqresApiService = $reqres_api_service;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(
        ContainerInterface $container,
        array $configuration,
        $plugin_id,
        $plugin_definition,
    ) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get("reqres_api_user_block.reqres_api_service"),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function build(): array
    {
        $users_data = $this->reqresApiService->getUsers();

        $output = '<div class="reqres-users-block">';
        $output .= "<h3>ReqRes Users</h3>";

        if (empty($users_data["data"])) {
            $output .= "<p>No users found or API unavailable.</p>";
        } else {
            $output .=
                "<p>Found " .
                $users_data["total"] .
                " total users (showing page " .
                $users_data["page"] .
                " of " .
                $users_data["total_pages"] .
                "):</p>";
            $output .= "<ul>";

            foreach ($users_data["data"] as $user) {
                $output .= "<li>";
                $output .=
                    '<img src="' .
                    htmlspecialchars($user["avatar"]) .
                    '" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; vertical-align: middle;">';
                $output .=
                    "<strong>" .
                    htmlspecialchars($user["first_name"]) .
                    " " .
                    htmlspecialchars($user["last_name"]) .
                    "</strong><br>";
                $output .=
                    "<small>" . htmlspecialchars($user["email"]) . "</small>";
                $output .= "</li>";
            }

            $output .= "</ul>";
        }

        $output .= "</div>";

        return [
            "#markup" => $output,
        ];
    }
}
