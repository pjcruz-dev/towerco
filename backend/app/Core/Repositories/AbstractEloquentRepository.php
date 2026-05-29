<?php

declare(strict_types=1);

namespace App\Core\Repositories;

use App\Core\Contracts\Repositories\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 * @implements RepositoryInterface<TModel>
 */
abstract class AbstractEloquentRepository implements RepositoryInterface
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function modelClass(): string;

    public function find(string $id): ?Model
    {
        /** @var TModel|null */
        return $this->query()->find($id);
    }

    public function findOrFail(string $id): Model
    {
        /** @var TModel */
        return $this->query()->findOrFail($id);
    }

    public function all(): Collection
    {
        /** @var Collection<int, TModel> */
        return $this->query()->get();
    }

    public function findBy(array $criteria): Collection
    {
        /** @var Collection<int, TModel> */
        return $this->query()->where($criteria)->get();
    }

    public function create(array $data): Model
    {
        /** @var TModel */
        $model = $this->query()->create($data);

        return $model;
    }

    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function paginate(int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $query = $this->query();

        foreach ($filters as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query->where($column, $value);
        }

        /** @var LengthAwarePaginator<int, TModel> */
        return $query->paginate($perPage);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     */
    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        /** @var TModel $model */
        $model = $this->queryModel();

        return $model->newQuery();
    }

    /**
     * @return TModel
     */
    protected function queryModel(): Model
    {
        $class = $this->modelClass();

        return new $class;
    }
}
