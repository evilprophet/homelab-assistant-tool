<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

#[ORM\Entity]
#[ORM\Table(name: 'action_logs')]
#[ORM\Index(name: 'idx_action_logs_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_action_logs_source', columns: ['source'])]
#[ORM\Index(name: 'idx_action_logs_level', columns: ['level'])]
#[ORM\Index(name: 'idx_action_logs_action', columns: ['action'])]
class ActionLog
{
    public const string SOURCE_CRON = 'CRON';
    public const string SOURCE_CLI = 'CLI';
    public const string SOURCE_WEB = 'WEB';

    public const string LEVEL_INFO = 'info';
    public const string LEVEL_WARNING = 'warning';
    public const string LEVEL_ERROR = 'error';

    public const array SOURCES = [
        self::SOURCE_CRON,
        self::SOURCE_CLI,
        self::SOURCE_WEB,
    ];
    public const array LEVELS = [
        self::LEVEL_INFO,
        self::LEVEL_WARNING,
        self::LEVEL_ERROR,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 16)]
    protected string $source;

    #[ORM\Column(length: 64)]
    protected string $action;

    #[ORM\Column(length: 16)]
    protected string $level;

    #[ORM\Column(type: Types::TEXT)]
    protected string $message;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        if (!in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException(
                sprintf("Invalid action log source '%s'. Allowed values: %s.", $source, implode(', ', self::SOURCES))
            );
        }

        $this->source = $source;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): self
    {
        if (!in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException(
                sprintf("Invalid action log level '%s'. Allowed values: %s.", $level, implode(', ', self::LEVELS))
            );
        }

        $this->level = $level;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
