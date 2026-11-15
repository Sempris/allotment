<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Loads a model and locks it until the surrounding transaction ends, on every
 * supported database. Must be the first statement of the transaction.
 *
 * MySQL and PostgreSQL lock the row (SELECT … FOR UPDATE): a second
 * transaction waits here until the first one has committed.
 *
 * SQLite has no row locks and ignores FOR UPDATE. There, a write as the first
 * statement takes the database's write lock, and a second writer waits for it
 * (busy_timeout). Without this, two transactions would both read first and the
 * second one would fail with "database is locked" when it tries to write.
 *
 * @mixin Model
 */
trait LocksForUpdate
{
    public static function findAndLock(int|string $key): static
    {
        $model = new static;
        $connection = $model->getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            // Changes nothing, but starts the write transaction.
            $connection->table($model->getTable())
                ->where($model->getKeyName(), $key)
                ->update([$model->getKeyName() => $key]);
        }

        return static::query()->lockForUpdate()->findOrFail($key);
    }
}
