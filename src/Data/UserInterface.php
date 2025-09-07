<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Data;

interface UserInterface
{
    public function getId(): int;

    public function getEmail(): string;

    public function getFirstName(): string;

    public function getLastName(): string;

    public function getAvatarUrl(): string;

    public function toArray(): array;
}
