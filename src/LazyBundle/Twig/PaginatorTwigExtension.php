<?php

namespace LazyBundle\Twig;

use Doctrine\ORM\Tools\Pagination\Paginator;
use LazyBundle\Service\PaginationService;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PaginatorTwigExtension extends AbstractExtension {

    /**
     * @var PaginationService
     */
    protected $paginationService;

    /**
     * @var Twig
     */
    protected $twig;

    /**
     * PaginatorTwigExtension constructor.
     *
     * @param PaginationService $paginationService
     * @param Twig $twig
     */
    public function __construct(PaginationService $paginationService, Twig $twig) {
        $this->paginationService = $paginationService;
        $this->twig = $twig;
    }


    public function getFunctions() {
        return [
            new TwigFunction('isPaginatorResult', [$this, 'functionIsPaginatorResult']),
            new TwigFunction('paginator', [$this, 'functionPaginator']),
        ];
    }

    public function functionIsPaginatorResult($variable) {
        return $variable instanceof Paginator;
    }

    /**
     * @param Paginator $paginatedResults
     *
     * @return string
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function functionPaginator(Paginator $paginatedResults): string {
        return $this->twig->render('@Lazy/_pagination.html.twig', [
            'lastPage' => $this->paginationService->lastPage($paginatedResults)
        ]);
    }
}