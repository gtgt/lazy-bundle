<?php

namespace LazyBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController as BaseController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\UndefinedEntityException;
use LazyBundle\Manager\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class EasyAdminController extends BaseController {
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    public function __construct(ManagerRegistry $registry) {
        $this->registry = $registry;
    }

    protected function createNewEntity() {
        $entityFullyQualifiedClassName = $this->entity['class'];
        $manager = $this->registry->getManagerForClass($entityFullyQualifiedClassName);
        if ($manager) {
            return $manager->createNew();
        }
        return new $entityFullyQualifiedClassName();
    }

    /**
     * Resorts an item using it's doctrine sortable property
     *
     * @Route("/sort/{entityClassName}/{id}/{position}", name="easyadmin_dragndrop_sort_sort")
     * @param String $entityClassName
     * @param Integer $id
     * @param Integer $position
     * @return Response
     *
     * @throws \ReflectionException
     * @throws NotFoundHttpException
     */
    public function sortAction($entityClassName, $id, $position) {
        try {
            $entityConfig = $this->container->get('easyadmin.config.manager')->getEntityConfig($entityClassName);
        } catch (UndefinedEntityException $e) {
            throw new \ReflectionException('The class name '.$entityClassName.'  not found.');
        }
        $entityClass = $entityConfig['class'];

        try {
            $rc = new \ReflectionClass($entityClass);
        } catch (\ReflectionException $error) {
            throw new \ReflectionException('The class name '.$entityClass.'  cannot be reflected.');
        }

        $em = $this->getDoctrine()->getManager();
        $e = $em->getRepository($rc->getName())->find($id);
        if ($e === null) {
            throw new NotFoundHttpException('The entity was not found');
        }
        $e->setPosition($position);
        $em->persist($e);
        $em->flush();
        return $this->redirectToRoute(
            'easyadmin',
            array(
                'action' => 'list',
                'entity' => $entityClassName,
                'sortField' => 'position',
                'sortDirection' => 'ASC',
            )
        );
    }
}