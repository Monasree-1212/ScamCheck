<?php

namespace ScamCheck;

use Predis\Client;
use InvalidArgumentException;
use RuntimeException;

class User
{
    private static function getRedis(): Client
    {
        return Database::getClient();
    }

    public static function create(array $data): array
    {
        $redis = self::getRedis();

        $username = trim($data['username'] ?? '');
        $email    = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $fullName = trim($data['full_name'] ?? '');
        $roleInput = $data['role'] ?? 'user';
        $role     = in_array($roleInput, ['admin', 'user'], true) ? $roleInput : 'user';

        if (empty($username) || strlen($username) < 3) {
            throw new InvalidArgumentException("Username must be at least 3 characters.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address.");
        }
        if (empty($password) || strlen($password) < 6) {
            throw new InvalidArgumentException("Password must be at least 6 characters.");
        }

        // Check uniqueness
        if ($redis->exists("user_by_email:{$email}")) {
            throw new InvalidArgumentException("Email is already registered.");
        }
        if ($redis->exists("user_by_username:" . strtolower($username))) {
            throw new InvalidArgumentException("Username is already taken.");
        }

        $userId = $redis->incr('users:counter');
        $now = date('Y-m-d H:i:s');
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $userData = [
            'id'            => (string)$userId,
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'full_name'     => $fullName ?: $username,
            'role'          => $role,
            'status'        => 'active',
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        // Redis transaction or pipeline for consistency
        $pipe = $redis->pipeline();
        $pipe->hmset("users:{$userId}", $userData);
        $pipe->set("user_by_email:{$email}", (string)$userId);
        $pipe->set("user_by_username:" . strtolower($username), (string)$userId);
        $pipe->sadd('users:all', [(string)$userId]);
        $pipe->execute();

        return $userData;
    }

    public static function findById(int|string $id): ?array
    {
        $redis = self::getRedis();
        $user = $redis->hgetall("users:{$id}");
        if (empty($user)) {
            return null;
        }
        return $user;
    }

    public static function findByEmail(string $email): ?array
    {
        $redis = self::getRedis();
        $email = strtolower(trim($email));
        $id = $redis->get("user_by_email:{$email}");
        if (!$id) {
            return null;
        }
        return self::findById($id);
    }

    public static function findByUsername(string $username): ?array
    {
        $redis = self::getRedis();
        $username = strtolower(trim($username));
        $id = $redis->get("user_by_username:{$username}");
        if (!$id) {
            return null;
        }
        return self::findById($id);
    }

    public static function findByCredentials(string $login, string $password): ?array
    {
        $login = trim($login);
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? self::findByEmail($login)
            : self::findByUsername($login);

        if (!$user) {
            return null;
        }

        if (password_verify($password, $user['password_hash'] ?? '')) {
            return $user;
        }

        return null;
    }

    public static function update(int|string $id, array $data): bool
    {
        $redis = self::getRedis();
        $existing = self::findById($id);
        if (!$existing) {
            return false;
        }

        $updates = [];
        if (isset($data['full_name'])) {
            $updates['full_name'] = trim($data['full_name']);
        }
        if (isset($data['role']) && in_array($data['role'], ['admin', 'user'], true)) {
            $updates['role'] = $data['role'];
        }
        if (isset($data['status']) && in_array($data['status'], ['active', 'suspended'], true)) {
            $updates['status'] = $data['status'];
        }
        if (!empty($data['password'])) {
            $updates['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($updates)) {
            return true;
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');
        $redis->hmset("users:{$id}", $updates);
        return true;
    }

    public static function getAll(): array
    {
        $redis = self::getRedis();
        $ids = $redis->smembers('users:all');
        if (empty($ids)) {
            return [];
        }

        $users = [];
        foreach ($ids as $id) {
            $u = self::findById($id);
            if ($u) {
                // hide password hash for listing
                unset($u['password_hash']);
                $users[] = $u;
            }
        }

        usort($users, fn($a, $b) => ($b['id'] <=> $a['id']));
        return $users;
    }

    public static function count(): int
    {
        $redis = self::getRedis();
        return (int)$redis->scard('users:all');
    }
}
