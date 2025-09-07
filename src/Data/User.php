<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Data;

class User implements UserInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly string $avatarUrl,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getAvatarUrl(): string
    {
        return $this->avatarUrl;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}
