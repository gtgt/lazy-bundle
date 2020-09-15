<?php
namespace LazyBundle\DBAL\Types;

use LazyBundle\Enum\Enum;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Class for MySQL (use enum type field)
 *
 * @package LazyBundle\DBAL\Types
 */
class MyPhpEnumType extends PhpEnumType {

    /**
     * @var string
     */
    protected $enumClass = Enum::class;

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string {
        $values = \call_user_func([$this->enumClass, $this->useKey ? 'keys' : 'toArray']);
        return \sprintf('ENUM(\'%s\')', \implode('\', \'', $values));
    }
}
