<?php

namespace App\Repository;

use App\Message\Message;
use App\Model\Model;
use DI\NotFoundException;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\AbstractPaginator as Paginator;
use Illuminate\Support\Collection;
use Slim\Flash\Messages;

/**
 * Class Repository
 * @package App\Repository
 * @author  Jerfeson Guerreiro <jerfeson_guerreiro@hotmail.com>
 * @since   1.0.0
 * @version 1.0.0
 */
abstract class Repository
{
    /**
     * @var String
     */
    protected $modelClass;

    /**
     * @return String
     */
    public function getModel(): string
    {
        return $this->modelClass;
    }

    /**
     * @param $item
     *
     * @return mixed
     * @throws Exception
     */
    public function insert($item)
    {
        $qb = $this->newQuery();
        return $qb->create($item);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function newQuery()
    {
        if ($model = $this->getModel()) {
            return (new $model())->newQuery();
        }

        throw new Exception(Message::MODEL_CLASS_NOT_DEFINED);
    }

    /**
     * Returns all records.
     * If $take is false then brings all records
     * If $paginate is true returns Paginator instance.
     *
     * @param int $take
     * @param bool $paginate
     *
     * @return EloquentCollection|Paginator
     * @throws Exception
     */
    public function getAll($take = 15, $paginate = true)
    {
        return $this->doQuery(null, $take, $paginate);
    }

    /**
     * @param EloquentQueryBuilder|QueryBuilder $query
     * @param int $take
     * @param bool $paginate
     *
     * @return LengthAwarePaginator|EloquentQueryBuilder[]|EloquentCollection|Collection
     * @throws Exception
     */
    protected function doQuery($query = null, $take = 15, $paginate = true)
    {
        if (is_null($query)) {
            $query = $this->newQuery();
        }

        if (true == $paginate) {
            return $query->paginate($take, ['*'], 'page', $page = null);
        }

        if ($take > 0 || false !== $take) {
            $query->take($take);
        }

        return $query->get();
    }

    /**
     * @param string $column
     * @param string|null $key
     *
     * @return Collection
     * @throws Exception
     */
    public function lists($column, $key = null)
    {
        return $this->newQuery()->lists($column, $key);
    }

    /**
     * Retrieves a record by his id
     * If fail is true $ fires ModelNotFoundException.
     *
     * @param int $id
     * @param bool $fail
     *
     * @return Model
     * @throws NotFoundException
     * @throws Exception
     */
    public function findById($id, $fail = true)
    {
        if ($fail) {
            $response = $this->newQuery()->find($id);
            if (!$response) {
                $message = app()->getContainer()->get(Messages::class);
                $message->addMessage(Message::STATUS_ERROR, Message::REGISTER_NOT_FOUND);
                throw new NotFoundException();
            }

            return $response;
        }

        return $this->newQuery()->find($id);
    }
}
