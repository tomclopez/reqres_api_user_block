<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
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
    public function defaultConfiguration(): array
    {
        return [
            "items_per_page" => 6,
            "email_label" => "Email",
            "forename_label" => "First Name",
            "surname_label" => "Last Name",
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state): array
    {
        $config = $this->getConfiguration();

        $form["items_per_page"] = [
            "#type" => "number",
            "#title" => $this->t("Items per page"),
            "#description" => $this->t("Number of users to display per page."),
            "#default_value" => $config["items_per_page"],
            "#min" => 1,
            "#max" => 50,
            "#required" => true,
        ];

        $form["email_label"] = [
            "#type" => "textfield",
            "#title" => $this->t("Email field label"),
            "#description" => $this->t(
                "The text to display as the column heading for the email field.",
            ),
            "#default_value" => $config["email_label"],
            "#required" => true,
            "#maxlength" => 255,
        ];

        $form["forename_label"] = [
            "#type" => "textfield",
            "#title" => $this->t("Forename field label"),
            "#description" => $this->t(
                "The text to display as the column heading for the forename field.",
            ),
            "#default_value" => $config["forename_label"],
            "#required" => true,
            "#maxlength" => 255,
        ];

        $form["surname_label"] = [
            "#type" => "textfield",
            "#title" => $this->t("Surname field label"),
            "#description" => $this->t(
                "The text to display as the column heading for the surname field.",
            ),
            "#default_value" => $config["surname_label"],
            "#required" => true,
            "#maxlength" => 255,
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state): void
    {
        $this->configuration["items_per_page"] = (int) $form_state->getValue(
            "items_per_page",
        );
        $this->configuration["email_label"] = $form_state->getValue(
            "email_label",
        );
        $this->configuration["forename_label"] = $form_state->getValue(
            "forename_label",
        );
        $this->configuration["surname_label"] = $form_state->getValue(
            "surname_label",
        );
    }

    /**
     * {@inheritdoc}
     */
    public function build(): array
    {
        $config = $this->getConfiguration();
        $items_per_page = $config["items_per_page"];

        $users_data = $this->reqresApiService->getUsers(1, $items_per_page);

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
