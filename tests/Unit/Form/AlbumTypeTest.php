<?php

namespace App\Tests\Unit\Form;

use App\Entity\Album;
use App\Form\AlbumType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

// Symfony's TypeTestCase/ValidatorExtensionTrait internally use createMock()
// without configuring expectations (see symfony/symfony#62781), which PHPUnit
// 12.5+ flags as a notice.
#[AllowMockObjectsWithoutExpectations]
class AlbumTypeTest extends TypeTestCase
{
    /**
     * @return array<int, ValidatorExtension>
     */
    protected function getExtensions(): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping() // so that the validator can read constraints from attributes
            ->getValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData(): void
    {
        $formData = [
            'name' => 'Test Album',
        ];

        $model = new Album();
        $form = $this->factory->create(AlbumType::class, $model);

        $expectedAlbum = new Album();
        $expectedAlbum->setName($formData['name']);

        $form->submit($formData);

        // Check that no transformer failed during submission
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($expectedAlbum, $model);
    }

    /**
     * @param array<string, string> $formData
     */
    #[DataProvider('invalidDataProvider')]
    public function testSubmitInvalidData(array $formData): void
    {
        $form = $this->factory->create(AlbumType::class, new Album());

        $form->submit($formData);

        $this->assertFalse($form->isValid());
    }

    /**
     * @return array<string, array<array<string, string>>>
     */
    public static function invalidDataProvider(): array
    {
        return [
            'empty value' => [['name' => '']],
            'whitespace value' => [['name' => '  ']],
            // non-string values are cast as strings -> no constraint violation
        ];
    }

    public function testFieldsHaveExpectedLabels(): void
    {
        $form = $this->factory->create(AlbumType::class, new Album());
        $view = $form->createView();
        $this->assertArrayHasKey('name', $view->children);
        $this->assertSame('Nom', $view->children['name']->vars['label']);
    }

    public function testExistingAlbumHasExpectedData(): void
    {
        $albumName = 'Existing Album';
        $album = new Album();
        $album->setName($albumName);

        $form = $this->factory->create(AlbumType::class, $album);
        $view = $form->createView();

        $this->assertArrayHasKey('name', $view->children);
        $this->assertSame($albumName, $view->children['name']->vars['value']);
    }
}
