<?php


namespace LazyBundle\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\ConversionException;

class BlobTextType extends BlobType {

    /**
     * @return string
     */
    public function getName() {
        return 'blob_text';
    }

    /**
     * @param $value
     * @param AbstractPlatform $platform
     *
     * @return bool|mixed|resource|null
     *
     * @throws ConversionException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform) {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw ConversionException::conversionFailed($value, self::BLOB);
        }
        return $value;
    }
}
