<?php

namespace App\Validator\Constraints;

use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

#[\Attribute]
class ValidPassword extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\Sequentially([
                new Assert\NotBlank(),
                /* Matches Symfony's password hasher limit. DoS protection related.
                 *  @see \Symfony\Component\PasswordHasher\Hasher\CheckPasswordLengthTrait::isPasswordTooLong()
                 */
                new Assert\Length(
                    max: PasswordHasherInterface::MAX_PASSWORD_LENGTH,
                    countUnit: Assert\Length::COUNT_BYTES,
                    maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} octets.',
                ),
                new Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_STRONG),
            ]),
        ];
    }
}
