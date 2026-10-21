<?php

declare(strict_types=1);

namespace Akeneo\Category\Application\Command\UpdateAttributeCommand;

use Akeneo\Category\Domain\ValueObject\LabelCollection;
use Webmozart\Assert\Assert;

/**
 * @copyright 2023 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @phpstan-import-type LocalizedLabels from LabelCollection
 */
final class UpdateAttributeCommand
{
    /**
     * @phpstan-param LocalizedLabels $labels
     * @param array<int, array{code: string, labels: array<string, string>}>|null $options
     */
    private function __construct(
        public readonly string $attributeUuid,
        public readonly ?bool $isRichTextArea,
        public readonly ?array $labels,
        public readonly ?array $options = null,
        public readonly bool $updateDefaultValue = false,
        public readonly ?string $defaultValue = null,
    ) {
        if ($labels !== null) {
            Assert::isMap($labels);
            Assert::allNullOrString($labels);
        }
    }

    /**
     * @phpstan-param LocalizedLabels $labels
     * @param array<int, array{code: string, labels: array<string, string>}>|null $options
     */
    public static function create(
        string $attributeUuid,
        ?bool $isRichTextArea,
        ?array $labels,
        ?array $options = null,
        bool $updateDefaultValue = false,
        ?string $defaultValue = null,
    ): self {
        return new self(
            attributeUuid: $attributeUuid,
            isRichTextArea: $isRichTextArea,
            labels: $labels,
            options: $options,
            updateDefaultValue: $updateDefaultValue,
            defaultValue: $defaultValue,
        );
    }
}
