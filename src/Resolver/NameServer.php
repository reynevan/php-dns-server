<?php

namespace Reynevan\PhpDnsServer\Resolver;

readonly class NameServer
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $addressA = null,
        private readonly ?string $addressAaaa = null
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddressA(): ?string
    {
        return $this->addressA;
    }

    public function getAddressAaaa(): ?string
    {
        return $this->addressAaaa;
    }
}