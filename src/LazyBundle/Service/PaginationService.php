<?php

namespace LazyBundle\Service;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PaginationService {
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * PaginationService constructor.
     *
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack) {
        $this->requestStack = $requestStack;
    }


    /**
     * @param QueryBuilder|Query $query
     * @param int $limit
     * @param bool $fetchJoinCollection Don't use this if the query is already has joins or the entity has composite keys.
     * @param Request|null $request
     *
     * @return Paginator
     */
    public function paginate($query, int $limit, $fetchJoinCollection = false, Request $request = NULL): Paginator {
        if ($request === null) {
            $request = $this->requestStack->getCurrentRequest();
        }
        $currentPage = $request->query->getInt('p') ?: 1;
        $paginator = new Paginator($query, $fetchJoinCollection);
        $paginator
            ->getQuery()
            ->setFirstResult($limit * ($currentPage - 1))
            ->setMaxResults($limit);

        return $paginator;
    }

    /**
     * @param Paginator $paginator
     *
     * @return int
     */
    public function lastPage(Paginator $paginator): int {
        return ceil($paginator->count() / $paginator->getQuery()->getMaxResults());
    }

    /**
     * @param Paginator $paginator
     *
     * @return int
     */
    public function total(Paginator $paginator): int {
        return $paginator->count();
    }

    /**
     * @param Paginator $paginator
     *
     * @return bool
     */
    public function currentPageHasNoResult(Paginator $paginator): bool {
        return !$paginator->getIterator()->count();
    }
}