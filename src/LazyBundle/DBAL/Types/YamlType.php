<?php

namespace Virgo\CoreBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;

/**
 * Yaml Formátumú mezőhöz
 */
class YamlType extends Type {

    /**
     * A Parser objektum, hogy ne kelljen példányosítani állandóan
     *
     * @var Parser
     */
    private static $parser;

    /**
     * A Dumper objektum, hogy ne kelljen példányosítani állandóan
     *
     * @var Dumper
     */
    private static $dumper;

    /**
     * A típus neve
     */
    const YAMLTYPE = 'yaml';

    /**
     * Mekkora mélység legyen az, ahol inline YAML formátumra vált
     */
    const INLINE = 2;

    /**
     * Mennyi space legyen a behúzás
     */
    const INDENT = 4;

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) {
        return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function getName() {
        return self::YAMLTYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform) {
        if (empty($value)) {
            return NULL;
        }

        /**
         * Ha még nincs meg staticban, akkor példányosítjuk
         */
        if(NULL === static::$dumper) {
            static::$dumper = new Dumper();
            static::$dumper->setIndentation(self::INDENT);
        }

        return static::$dumper->dump($value, self::INLINE);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform) {
        if ($value === NULL || $value == '') {
            return [];
        }

        /**
         * Ha még nincs meg staticban, akkor példányosítjuk
         */
        if(NULL === static::$parser) {
            static::$parser = new Parser();
        }

        $value = (is_resource($value)) ? stream_get_contents($value) : $value;

        return static::$parser->parse($value);
    }
}