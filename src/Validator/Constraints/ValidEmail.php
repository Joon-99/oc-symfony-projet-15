<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

#[\Attribute]
class ValidEmail extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Email(message: "Merci d'entrer une adresse email valide."),
            // Max length is derived from Doctrine's Column attribute of the Entity, via auto-mapping in validator.yaml
        ];
    }
}
