<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a ReqRes Users block.
 *
 * @Block(
 *   id = "reqres_users_block",
 *   admin_label = @Translation("ReqRes Users"),
 *   category = @Translation("Custom")
 * )
 */
class ReqResUsersBlock extends BlockBase
{
    /**
     * {@inheritdoc}
     * @return array<string, mixed>
     *   The render array for the block.
     */
    public function build(): array
    {
        return [
            '#markup' => '<div class="reqres-users-block">
                <h3>ReqRes Users</h3>
                <p>This is a placeholder block.</p>
            </div>',
        ];
    }
}