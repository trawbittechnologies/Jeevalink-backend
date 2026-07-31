<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findById(int|string $id): ?User;
    public function findByEmail(string $email): ?User;
    public function create(array $data): User;
    public function update(int|string $id, array $data): bool;
    public function delete(int|string $id): bool;
    public function getAllUsers(array $filters = []): Collection|LengthAwarePaginator;
    public function getVolunteersByDistrict(string $district): Collection;
}
