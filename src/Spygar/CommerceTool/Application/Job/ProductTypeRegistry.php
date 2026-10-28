<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Spygar\CommerceTool\Domain\Model\Credential;

final class ProductTypeRegistry
{
    public const KEY = 'akeneo';

    public function __construct(private CommerceToolGateway $gateway)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(Credential $credential, string $token): array
    {
        $existing = $this->gateway->getOne($credential, $token, 'product-types/key='.self::KEY);
        if (null !== $existing) {
            return $existing;
        }

        $created = $this->gateway->post($credential, $token, 'product-types', [
            'json' => [
                'key' => self::KEY,
                'name' => 'Akeneo',
                'description' => 'Attributes synchronized from Akeneo',
            ],
        ]);
        if (!\in_array($created['status'], [200, 201], true)) {
            throw new \RuntimeException($this->gateway->failure($credential, $created, 'CommerceTool could not create the product type.'));
        }

        return $created['body'];
    }

    /**
     * @param array<string, mixed>        $type
     * @param list<array<string, mixed>>  $actions
     *
     * @return array<string, mixed>
     */
    public function apply(Credential $credential, string $token, array $type, array $actions): array
    {
        foreach (array_chunk($actions, 20) as $chunk) {
            $type = $this->send($credential, $token, $type, $chunk, 0);
        }

        return $type;
    }

    /**
     * @param array<string, mixed> $type
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(array $type): array
    {
        $definitions = [];
        foreach ($type['attributes'] ?? [] as $attribute) {
            if (\is_array($attribute) && isset($attribute['name']) && \is_string($attribute['name'])) {
                $definitions[$attribute['name']] = $attribute;
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed>       $type
     * @param list<array<string, mixed>> $actions
     *
     * @return array<string, mixed>
     */
    private function send(Credential $credential, string $token, array $type, array $actions, int $retries): array
    {
        if ([] === $actions) {
            return $type;
        }

        $response = $this->gateway->post($credential, $token, 'product-types/key='.self::KEY, [
            'json' => [
                'version' => (int) ($type['version'] ?? 0),
                'actions' => array_values($actions),
            ],
        ]);
        if (\in_array($response['status'], [200, 201], true)) {
            return $response['body'];
        }
        if (409 === $response['status'] && $retries < 1) {
            $reloaded = $this->gateway->getOne($credential, $token, 'product-types/key='.self::KEY);
            if (null !== $reloaded) {
                return $this->send($credential, $token, $reloaded, $actions, $retries + 1);
            }
        }
        if (1 === \count($actions)) {
            throw new \RuntimeException($this->gateway->failure($credential, $response, 'CommerceTool rejected a product type update.'));
        }

        $mid = intdiv(\count($actions), 2);
        $type = $this->send($credential, $token, $type, \array_slice($actions, 0, $mid), 0);

        return $this->send($credential, $token, $type, \array_slice($actions, $mid), 0);
    }
}
