<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\UserChangeRejected;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates, changes and removes users, and keeps two promises:
 * the club always has an active board member, and nobody who appears in the
 * reading history disappears from it.
 */
class UserAdministration
{
    /**
     * @param  array{name: string, email: string, role: Role, plot_id: int|null, active: bool, password: string}  $data
     */
    public function create(array $data): User
    {
        return User::create($this->withoutPlotUnlessTenant($data));
    }

    /**
     * @param  array{name: string, email: string, role: Role, plot_id: int|null, active: bool, password?: string|null}  $data
     *
     * @throws UserChangeRejected
     */
    public function update(User $user, array $data, User $actingUser): User
    {
        return DB::transaction(function () use ($user, $data, $actingUser) {
            $user = User::findAndLock($user->getKey());
            $data = $this->withoutPlotUnlessTenant($data);

            if (! $data['active'] && $user->is($actingUser)) {
                throw new UserChangeRejected('active', 'Den eigenen Zugang können Sie nicht sperren.');
            }

            $staysActiveBoard = $data['active'] && $data['role'] === Role::Board;

            if ($this->isLastActiveBoardMember($user) && ! $staysActiveBoard) {
                throw new UserChangeRejected(
                    $data['active'] ? 'role' : 'active',
                    'Das ist der letzte aktive Vorstandszugang. Bitte zuerst einen weiteren Vorstand anlegen.',
                );
            }

            // An empty password field means: keep the current password.
            if (($data['password'] ?? '') === '' || $data['password'] === null) {
                unset($data['password']);
            }

            $user->update($data);

            return $user;
        });
    }

    /**
     * @throws UserChangeRejected
     */
    public function delete(User $user, User $actingUser): void
    {
        DB::transaction(function () use ($user, $actingUser) {
            $user = User::findAndLock($user->getKey());

            if ($user->is($actingUser)) {
                throw new UserChangeRejected('user', 'Den eigenen Zugang können Sie nicht löschen.');
            }

            if ($this->isLastActiveBoardMember($user)) {
                throw new UserChangeRejected('user', 'Das ist der letzte aktive Vorstandszugang. Er kann nicht gelöscht werden.');
            }

            if ($user->hasHistory()) {
                throw new UserChangeRejected(
                    'user',
                    'Dieser Benutzer hat Ablesungen erfasst, geprüft oder Abrechnungen versendet und steht im Verlauf. Bitte den Zugang sperren statt löschen.',
                );
            }

            $user->delete();
        });
    }

    private function isLastActiveBoardMember(User $user): bool
    {
        if ($user->role !== Role::Board || ! $user->active) {
            return false;
        }

        return ! User::query()
            ->where('role', Role::Board)
            ->where('active', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    /**
     * Only tenants belong to a plot.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutPlotUnlessTenant(array $data): array
    {
        if ($data['role'] !== Role::Tenant) {
            $data['plot_id'] = null;
        }

        return $data;
    }
}
