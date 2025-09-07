<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\reqres_api_user_block\Service\UserProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
     * The user provider service.
     */
    protected UserProviderInterface $userProvider;

    /**
     * The request stack service.
     */
    protected RequestStack $requestStack;

    /**
     * The pager manager service.
     */
    protected PagerManagerInterface $pagerManager;

    /**
     * Constructs a new ReqResUsersBlock instance.
     *
     * @param array $configuration
     * @param string $plugin_id
     * @param mixed $plugin_definition
     * @param \Drupal\reqres_api_user_block\Service\UserProviderInterface $user_provider
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
     */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        UserProviderInterface $user_provider,
        RequestStack $request_stack,
        PagerManagerInterface $pager_manager,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->userProvider = $user_provider;
        $this->requestStack = $request_stack;
        $this->pagerManager = $pager_manager;
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
            $container->get("reqres_api_user_block.user_provider"),
            $container->get("request_stack"),
            $container->get("pager.manager"),
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

        $request = $this->requestStack->getCurrentRequest();
        $page = (int) $request->query->get("page", 0);
        $current_page = $page + 1; // Drupal uses 0-based, API uses 1-based

        $result = $this->userProvider->getUsers($current_page, $items_per_page);

        $total_items = $result->getTotal();
        $this->pagerManager->createPager($total_items, $items_per_page);

        $build = [];
        $build["content"] = [
            "#markup" => $this->buildUserList($result, $config),
        ];

        if ($result->getTotalPages() > 1) {
            $build["pager"] = [
                "#type" => "pager",
            ];
        }

        return $build;
    }

    private function buildUserList($result, $config): string
    {
        $output = '<div class="reqres-users-block">';
        $output .= "<h3>ReqRes Users</h3>";

        if ($result->isEmpty()) {
            $output .= "<p>No users found or API unavailable.</p>";
        } else {
            $output .=
                "<p>Found " .
                $result->getTotal() .
                " total users (showing page " .
                $result->getPage() .
                " of " .
                $result->getTotalPages() .
                "):</p>";
            $output .= "<ul>";

            foreach ($result->getUsers() as $user) {
                $output .= "<li>";
                $output .=
                    '<img src="' .
                    htmlspecialchars($user->getAvatarUrl()) .
                    '" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; vertical-align: middle;">';
                $output .=
                    "<strong>" .
                    htmlspecialchars($user->getFirstName()) .
                    " " .
                    htmlspecialchars($user->getLastName()) .
                    "</strong><br>";
                $output .=
                    "<small>" .
                    htmlspecialchars($user->getEmail()) .
                    "</small>";
                $output .= "</li>";
            }

            $output .= "</ul>";
        }

        $output .= "</div>";

        return $output;
    }
}
