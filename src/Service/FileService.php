<?php

namespace App\Service;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class FileService
{
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function deleteFile(string $filePath): void
    {
        $this->filesystem->remove($filePath);
    }

    /**
     * @param list<string> $filePaths
     *
     * @throws IOException
     */
    public function deleteFiles(array $filePaths): void
    {
        $errors = [];
        foreach ($filePaths as $filePath) {
            try {
                $this->filesystem->remove($filePath);
            } catch (IOException $e) {
                $errors[] = ['file' => $filePath, 'errorMsg' => $e->getMessage()];
            }
        }
        if (!empty($errors)) {
            throw new IOException('Failed to delete some files: '.json_encode($errors));
        }
    }
}
