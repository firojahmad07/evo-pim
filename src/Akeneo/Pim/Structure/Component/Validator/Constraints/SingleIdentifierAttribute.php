<?php

declare(strict_types=1);

namespace Akeneo\Pim\Structure\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class SingleIdentifierAttribute extends Constraint
{
    public $message = 'An identifier attribute already exists.';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy()
    {
        return 'pim_structure_single_identifier_attribute_validator';
    }
}
