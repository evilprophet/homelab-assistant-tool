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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthUserService extends AbstractDatabaseService
{
    protected const string WHITESPACE_PATTERN = '/\s/';

    public function __construct(
        EntityManagerInterface $entityManager,
        protected UserRepository $userRepository,
        protected UserPasswordHasherInterface $userPasswordHasher
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

        $this->assertPasswordIsUsable($password);

        if ($this->userRepository->findByUsername($normalizedUsername) !== null) {
            throw new EntityAlreadyExists(sprintf("User with username '%s' already exists.", $normalizedUsername));
        }

        $user = (new User())
            ->setUsername($normalizedUsername)
            ->setOidcSubject(null);
        $user->setPasswordHash($this->hashPassword($user, $password));

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

        $this->assertPasswordIsUsable($newPassword);

        $user = $this->userRepository->findByUsername($normalizedUsername);
        if ($user === null) {
            throw EntityNotFound::forField('User', 'username', $normalizedUsername);
        }

        $user->setPasswordHash($this->hashPassword($user, $newPassword));
        $this->persist($user);
        $this->flush();

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

    protected function assertPasswordIsUsable(string $password): void
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        // Whitespace survives shell quoting and copy-paste inconsistently, so it turns
        // into logins that fail while looking identical to the operator.
        if (preg_match(self::WHITESPACE_PATTERN, $password) === 1) {
            throw new InvalidArgumentException('Password cannot contain whitespace characters.');
        }
    }

    protected function hashPassword(User $user, string $plainPassword): string
    {
        return $this->userPasswordHasher->hashPassword($user, $plainPassword);
    }
}
