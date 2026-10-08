<?php

namespace Akeneo\Pim\Structure\Component\Validator\Constraints;

use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SingleIdentifierAttributeValidator extends ConstraintValidator
{
    /** @var AttributeRepositoryInterface */
    protected $attributeRepository;

    public function __construct(AttributeRepositoryInterface $attributeRepository)
    {
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($attribute, Constraint $constraint)
    {
        if (!$attribute instanceof AttributeInterface) {
            return;
        }

        if (AttributeTypes::IDENTIFIER !== $attribute->getType()) {
            return;
        }

        $identifier = $this->attributeRepository->getIdentifier();

        if (null === $identifier) {
            return;
        }

        if ($identifier->getId() === $attribute->getId()) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->addViolation();
    }
}
