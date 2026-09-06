<?php

namespace Reynevan\PhpDnsServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Record\RecordSet;
use Reynevan\PhpDnsServer\Resolver\ResolutionResult;
use Reynevan\PhpDnsServer\Resolver\Resolver;

class ResolverChainTest extends TestCase
{
    public function testUsesFirstResolverThatSupportsQuery()
    {
        $resolverA = self::notSupportingResolver();
        $resolverB = self::supportingResolver();
        $resolverC = self::supportingResolver();
    }

    public static function supportingResolver(): Resolver
    {
        return new class implements Resolver {
            public function supports(Query $query): bool
            {
                return true;
            }

            public function resolve(Query $query): ResolutionResult
            {
                ResolutionResult::answer(new RecordSet());
            }
        };
    }

    public static function notSupportingResolver(): Resolver
    {
        return new class implements Resolver {
            public function supports(Query $query): bool
            {
                return false;
            }

            public function resolve(Query $query): ResolutionResult
            {
                ResolutionResult::answer(new RecordSet());
            }
        };
    }
}
