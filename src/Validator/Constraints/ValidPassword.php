<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\PasswordStrength;

#[\Attribute]
class ValidPassword extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\Sequentially([
                new Assert\NotBlank(),
                new Assert\PasswordStrength(minScore: PasswordStrength::STRENGTH_STRONG),
            ]),
        ];
    }
}
