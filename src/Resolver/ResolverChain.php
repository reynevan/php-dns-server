<?php

namespace Reynevan\PhpDnsServer\Resolver;

use Reynevan\PhpDnsServer\Message\Query;
use Reynevan\PhpDnsServer\Message\ResponseCode;

readonly class ResolverChain implements Resolver
{
    /**
     * @param Resolver[] $resolvers
     */
    public function __construct(private array $resolvers)
    {
    }

    public function supports(Query $query): bool
    {
        return array_filter($this->resolvers, function (Resolver $resolver) use ($query) {
            return $resolver->supports($query);
        }) !== [];
    }

    public function resolve(Query $query): ResolutionResult
    {
        foreach ($this->resolvers as $resolver) {
            if (!$resolver->supports($query)) {
                continue;
            }
            return $resolver->resolve($query);
        }
        return ResolutionResult::failure(ResponseCode::REFUSED);
    }
}
