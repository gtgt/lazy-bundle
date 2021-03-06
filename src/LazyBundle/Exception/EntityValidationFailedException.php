<?php
namespace LazyBundle\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidatorException;

class EntityValidationFailedException extends ValidatorException implements ExceptionInterface {
    /**
     * @var object
     */
    protected $entity;

    /**
     * @var ConstraintViolationListInterface
     */
    protected $violations;

    /**
     * EntityValidationFailedException constructor.
     *
     * @param object $entity
     * @param ConstraintViolationListInterface $violations
     */
    public function __construct($entity, ConstraintViolationListInterface $violations) {
        $this->entity = $entity;
        $this->violations = $violations;
        try {
            $violation = $violations->get(0);
            parent::__construct(sprintf('Validation failed on entity%s->%s: %s', method_exists($entity, '__toString') ? '['.$entity.']' : '', $violation->getPropertyPath(), $violation->getMessage()), (int)$violation->getCode());
        } catch (\OutOfBoundsException $e) {
            parent::__construct(sprintf('Validation failed on entity (%s)', method_exists($entity, '__toString') ? ' ('.$entity.')' : ''));
        }

    }

}
