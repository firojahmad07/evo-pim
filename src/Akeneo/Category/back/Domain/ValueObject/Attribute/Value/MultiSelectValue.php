<?php

declare(strict_types=1);

namespace Akeneo\Category\Domain\ValueObject\Attribute\Value;

use Akeneo\Category\Domain\ValueObject\Attribute\AttributeCode;
use Akeneo\Category\Domain\ValueObject\Attribute\AttributeUuid;
use Webmozart\Assert\Assert;

/**
 * @copyright 2022 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class MultiSelectValue extends AbstractValue
{
    /**
     * @param string[]|null $value
     */
    private function __construct(
        private readonly ?array $value,
        AttributeUuid $uuid,
        AttributeCode $code,
        ?ChannelValue $channel,
        ?LocaleValue $locale,
    ) {
        parent::__construct(
            uuid: $uuid,
            code: $code,
            channel: $channel,
            locale: $locale,
        );
    }

    /**
     * @param string[]|null $value
     */
    public static function fromApplier(?array $value, string $uuid, string $code, ?string $channel, ?string $locale): self
    {
        return new self(
            value: self::sanitize($value),
            uuid: AttributeUuid::fromString($uuid),
            code: new AttributeCode($code),
            channel: !empty($channel) ? new ChannelValue($channel) : null,
            locale: !empty($locale) ? new LocaleValue($locale) : null,
        );
    }

    /**
     * @param array{
     *     data: string[]|null,
     *     type: string,
     *     channel: string|null,
     *     locale: string|null,
     *     attribute_code: string,
     * } $value
     */
    public static function fromArray(array $value): self
    {
        $identifiers = explode(AbstractValue::SEPARATOR, $value['attribute_code']);
        if (count($identifiers) !== 2) {
            throw new \InvalidArgumentException('Cannot find code and uuid.');
        }

        return new self(
            value: self::sanitize($value['data']),
            uuid: AttributeUuid::fromString($identifiers[1]),
            code: new AttributeCode($identifiers[0]),
            channel: !empty($value['channel']) ? new ChannelValue($value['channel']) : null,
            locale: !empty($value['locale']) ? new LocaleValue($value['locale']) : null,
        );
    }

    /**
     * @return string[]|null
     */
    public function getValue(): ?array
    {
        return $this->value;
    }

    /**
     * @return array<string, array{
     *     data: string[]|null,
     *     type: string,
     *     channel: string|null,
     *     locale: string|null,
     *     attribute_code: string,
     * }>
     */
    public function normalize(): array
    {
        return array_replace_recursive(
            parent::normalize(),
            [$this->getKeyWithChannelAndLocale() => [
                'data' => $this->value,
                'type' => AbstractValue::MULTI_SELECT_TYPE,
            ]],
        );
    }

    /**
     * @return string[]|null
     */
    private static function sanitize(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        Assert::isArray($value);
        Assert::allString($value);

        $value = array_values(array_filter($value, static fn (string $optionCode) => $optionCode !== ''));

        return $value === [] ? null : $value;
    }
}
