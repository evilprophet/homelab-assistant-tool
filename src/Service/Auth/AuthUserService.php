<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Auth;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\User;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\UserRepository;
use EvilStudio\HAT\Service\Application\AbstractDatabaseService;
use InvalidArgumentException;
use RuntimeException;

class AuthUserService extends AbstractDatabaseService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        protected UserRepository $userRepository
    ) {
        parent::__construct($entityManager);
    }

    public function findUserById(int $userId): ?User
    {
        return $this->userRepository->findById($userId);
    }

    public function createSimpleUser(string $username, string $password): User
    {
        $normalizedUsername = $this->normalizeUsername($username);
        if ($normalizedUsername === '') {
            throw new InvalidArgumentException('Username cannot be empty.');
        }

        if (trim($password) === '') {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        if ($this->userRepository->findByUsername($normalizedUsername) !== null) {
            throw new EntityAlreadyExists(sprintf("User with username '%s' already exists.", $normalizedUsername));
        }

        $passwordHash = $this->hashPassword($password);

        $user = (new User())
            ->setUsername($normalizedUsername)
            ->setPasswordHash($passwordHash)
            ->setOidcSubject(null);

        $this->persist($user);
        $this->flush();

        return $user;
    }

    public function removeSimpleUser(string $username): void
    {
        $normalizedUsername = $this->normalizeUsername($username);
        if ($normalizedUsername === '') {
            throw new InvalidArgumentException('Username cannot be empty.');
        }

        $user = $this->userRepository->findByUsername($normalizedUsername);
        if ($user === null) {
            throw EntityNotFound::forField('User', 'username', $normalizedUsername);
        }

        $this->remove($user);
        $this->flush();
    }

    public function resetSimpleUserPassword(string $username, string $newPassword): User
    {
        $normalizedUsername = $this->normalizeUsername($username);
        if ($normalizedUsername === '') {
            throw new InvalidArgumentException('Username cannot be empty.');
        }

        if (trim($newPassword) === '') {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        $user = $this->userRepository->findByUsername($normalizedUsername);
        if ($user === null) {
            throw EntityNotFound::forField('User', 'username', $normalizedUsername);
        }

        $user->setPasswordHash($this->hashPassword($newPassword));
        $this->persist($user);
        $this->flush();

        return $user;
    }

    public function authenticateSimple(string $username, string $password): ?User
    {
        $normalizedUsername = $this->normalizeUsername($username);
        if ($normalizedUsername === '' || trim($password) === '') {
            return null;
        }

        $user = $this->userRepository->findByUsername($normalizedUsername);
        if ($user === null) {
            return null;
        }

        $passwordHash = $user->getPasswordHash();
        if ($passwordHash === null || $passwordHash === '') {
            return null;
        }

        if (!password_verify($password, $passwordHash)) {
            return null;
        }

        return $user;
    }

    public function createOrUpdateFromOidc(string $oidcSubject, string $preferredUsername): User
    {
        $subject = trim($oidcSubject);
        $username = $this->normalizeUsername($preferredUsername);

        if ($subject === '') {
            throw new InvalidArgumentException('OIDC subject cannot be empty.');
        }

        if ($username === '') {
            throw new InvalidArgumentException('OIDC preferred_username cannot be empty.');
        }

        $userBySubject = $this->userRepository->findByOidcSubject($subject);
        if ($userBySubject !== null) {
            if ($userBySubject->getUsername() !== $username) {
                $userByUsername = $this->userRepository->findByUsername($username);
                if (
                    $userByUsername !== null
                    && $userByUsername->getId() !== $userBySubject->getId()
                ) {
                    throw new EntityAlreadyExists(
                        sprintf("Cannot rename OIDC user to '%s' because username already exists.", $username)
                    );
                }

                $userBySubject->setUsername($username);
                $this->persist($userBySubject);
                $this->flush();
            }

            return $userBySubject;
        }

        $userByUsername = $this->userRepository->findByUsername($username);
        if ($userByUsername !== null) {
            $existingSubject = $userByUsername->getOidcSubject();
            if ($existingSubject !== null && $existingSubject !== $subject) {
                throw new EntityAlreadyExists(
                    sprintf("Username '%s' is already linked to a different OIDC subject.", $username)
                );
            }

            $userByUsername->setOidcSubject($subject);
            $this->persist($userByUsername);
            $this->flush();

            return $userByUsername;
        }

        $user = (new User())
            ->setUsername($username)
            ->setPasswordHash(null)
            ->setOidcSubject($subject);

        $this->persist($user);
        $this->flush();

        return $user;
    }

    protected function normalizeUsername(string $username): string
    {
        return trim($username);
    }

    protected function hashPassword(string $plainPassword): string
    {
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Failed to generate password hash.');
        }

        return $passwordHash;
    }
}
