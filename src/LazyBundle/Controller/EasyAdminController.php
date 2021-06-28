<?php

namespace LazyBundle\Controller;

use AlterPHP\EasyAdminExtensionBundle\Security\AdminAuthorizationChecker;
use EasyCorp\Bundle\EasyAdminBundle\Exception\UndefinedEntityException;
use LazyBundle\Manager\AbstractManager;
use LazyBundle\Manager\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

if (class_exists('AlterPHP\EasyAdminExtensionBundle\Controller\EasyAdminController')) {
    class BaseController extends \AlterPHP\EasyAdminExtensionBundle\Controller\EasyAdminController {
    }
} else {
    class BaseController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController {
    }
}

class EasyAdminController extends BaseController {
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    /**
     * @var \ReflectionProperty
     */
    protected $configManagerReflection;

    public function getManagerRegistry() {
        if ($this->managerRegistry === null) {
            $this->managerRegistry = $this->get(ManagerRegistry::class);
        }
        return $this->managerRegistry;
    }

    public static function getSubscribedServices(): array {
        return \array_merge(parent::getSubscribedServices(), [ManagerRegistry::class]);
    }

    protected function initialize(Request $request) {
        parent::initialize($request);
        $easyadmin = $request->attributes->get('easyadmin');
        if (!is_array($easyadmin)) {
            return;
        }
        $entity = $easyadmin['item'];
        if ($entity == null) {
            return null;
        }
        $entityFullyQualifiedClassName = $this->entity['class'];
        $manager = $this->getManagerRegistry()->getManagerForClass($entityFullyQualifiedClassName);
        if ($manager instanceof AbstractManager) {
            $manager->injectDependency($entity);
        }
    }

    /**
     * @param array $backendConfig
     */
    protected function setBackendConfig(array $backendConfig): void {
        if ($this->configManagerReflection === null) {
            $configManager = $this->container->get('easyadmin.config.manager');
            try {
                $this->configManagerReflection = new \ReflectionProperty($configManager, 'backendConfig');
            } catch (\ReflectionException $e) {
                return;
            }
            $this->configManagerReflection->setAccessible(true);
            $this->configManagerReflection->object = $configManager;
        }
        // It's a hack I know... not really nice
        $this->configManagerReflection->setValue($this->configManagerReflection->object, $backendConfig);
    }

    /**
     * @param string $view
     * @param array $parameters
     * @param Response|null $response
     *
     * @return Response
     */
    protected function render(string $view, array $parameters = [], Response $response = null): Response {
        $action = $this->request->query->get('action', 'list');
        if (\in_array($action, ['list', 'show', 'edit', 'new'])) {
            $assets = $this->entity[$action]['assets'] ?? null;
            if (\is_array($assets)) {
                $this->config['design']['assets'] = array_merge_recursive($this->config['design']['assets'], $assets);
                $this->setBackendConfig($this->config);

            }
        }
        return parent::render($view, $parameters, $response);
    }

    protected function createNewEntity() {
        $entityFullyQualifiedClassName = $this->entity['class'];
        $manager = $this->getManagerRegistry()->getManagerForClass($entityFullyQualifiedClassName);
        if ($manager instanceof AbstractManager) {
            return $manager->createNew();
        }
        return new $entityFullyQualifiedClassName();
    }

    protected function persistEntity($entity) {
        parent::persistEntity($entity);
    }

    protected function updateEntity($entity) {
        parent::updateEntity($entity);
    }

    protected function removeEntity($entity) {
        parent::removeEntity($entity);
    }

    /**
     * Resorts an item using it's doctrine sortable property
     *
     * @Route("/sort/{entityClassName}/{id}/{position}", name="easyadmin_dragndrop_sort_sort")
     * @param String $entityClassName
     * @param Integer $id
     * @param Integer $position
     *
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
            [
                'action' => 'list',
                'entity' => $entityClassName,
                'sortField' => 'position',
                'sortDirection' => 'ASC',
            ]
        );
    }
}