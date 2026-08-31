<?php

namespace Reynevan\PhpDnsServer\Cli;

readonly class Option
{
    public function __construct(
        private string $longName,
        private ?string $shortName = null,
        private mixed $default = null,
        private ?string $description = null,
        private bool $isFlag = false,
    ) {
    }

    public function getLongName(): string
    {
        return $this->longName;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isFlag(): bool
    {
        return $this->isFlag;
    }
}
