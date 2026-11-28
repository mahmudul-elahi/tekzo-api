<?php

declare(strict_types=1);

namespace App\Services\Customer\Social;

use App\Enums\SocialProvider;

/**
 * A person the provider vouched for, read from a token the provider signed or confirmed.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public SocialProvider $provider,
        public string $id,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}
}
