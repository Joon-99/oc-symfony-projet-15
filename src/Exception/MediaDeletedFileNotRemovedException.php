<?php

namespace App\Exception;

use Symfony\Component\Filesystem\Exception\IOException;

class MediaDeletedFileNotRemovedException extends \RuntimeException
{
    public const DEFAULT_MESSAGE = "Le Media a été supprimé mais le fichier n'a pas pu être effacé : ";

    public function __construct(string $message = '', int $code = 0, ?IOException $previous = null)
    {
        $fullMessage = self::DEFAULT_MESSAGE.$message;
        parent::__construct($fullMessage, $code, $previous);
    }
}
