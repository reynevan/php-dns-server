<?php

namespace Reynevan\PhpDnsServer\Cli;

readonly class Options
{
    /**
     * @param mixed[] $values
     */
    public function __construct(private array $values)
    {
    }

    /**
     * @param string[] $argv
     * @param Option[] $definitions
     * @return self
     * @throws InvalidOptionException
     */
    public static function parse(array $argv, array $definitions): self
    {
        $values = [];
        $longMap = [];
        $shortMap = [];

        foreach ($definitions as $definition) {
            $values[$definition->getLongName()] = $definition->getDefault();
            $longMap[$definition->getLongName()] = $definition;
            if ($definition->getShortName() !== null) {
                $shortMap[$definition->getShortName()] = $definition;
            }
        }

        $args = self::normalizeArgs($argv, $shortMap);
        $count = count($args);

        for ($i = 1; $i < $count; $i++) {
            $arg = $args[$i];
            $definition = self::resolveDefinition($arg, $longMap, $shortMap);

            if ($definition === null) {
                throw new InvalidOptionException($arg);
            }

            if ($definition->isFlag()) {
                $values[$definition->getLongName()] = true;
                continue;
            }

            $value = $args[++$i] ?? null;
            $values[$definition->getLongName()] = self::castValue($value, $definition->getDefault());
        }

        return new self($values);
    }

    /**
     * @param string $arg
     * @param Option[] $longMap
     * @param Option[] $shortMap
     * @return Option|null
     */
    private static function resolveDefinition(string $arg, array $longMap, array $shortMap): ?Option
    {
        if (str_starts_with($arg, '--')) {
            return $longMap[substr($arg, 2)] ?? null;
        }

        if (str_starts_with($arg, '-')) {
            return $shortMap[substr($arg, 1)] ?? null;
        }

        return null;
    }

    private static function castValue(?string $value, mixed $default): mixed
    {
        if ($value === null) {
            return $default;
        }

        return match (true) {
            is_int($default) => (int) $value,
            is_float($default) => (float) $value,
            is_bool($default) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }

    /**
     * @param string[] $argv
     * @param array<string, Option> $shortMap
     * @return string[]
     */
    private static function normalizeArgs(array $argv, array $shortMap): array
    {
        $args = [];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                if (str_contains($arg, '=')) {
                    [$name, $value] = explode('=', $arg, 2);
                    $args[] = $name;
                    $args[] = $value;
                } else {
                    $args[] = $arg;
                }
                continue;
            }

            if (preg_match('/^-[a-zA-Z0-9]{2,}$/', $arg)) {
                foreach (str_split(substr($arg, 1)) as $i => $shortArg) {
                    if (isset($shortMap[$shortArg])) {
                        $args[] = '-' . $shortArg;
                    } else {
                        $args[] = substr($arg, $i + 1);
                        break;
                    }
                }
                continue;
            }

            $args[] = $arg;
        }

        return $args;
    }

    public function get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }
}
