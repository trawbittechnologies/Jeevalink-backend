<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int|string $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int|string $id, array $data): bool
    {
        $user = $this->findById($id);
        if (!$user) {
            return false;
        }
        return $user->update($data);
    }

    public function delete(int|string $id): bool
    {
        $user = $this->findById($id);
        if (!$user) {
            return false;
        }
        return (bool) $user->delete();
    }

    public function getAllUsers(array $filters = []): Collection|LengthAwarePaginator
    {
        $query = User::query();

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (!empty($filters['district'])) {
            $query->where('district', $filters['district']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('fullName', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        return $query->latest()->get();
    }

    public function getVolunteersByDistrict(string $district): Collection
    {
        return User::where('role', 'volunteer')
            ->where('district', $district)
            ->get();
    }
}
