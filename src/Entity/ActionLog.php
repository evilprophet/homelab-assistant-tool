<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'action_logs',
    indexes: [
        new ORM\Index(name: 'idx_action_logs_created_at', columns: ['created_at']),
        new ORM\Index(name: 'idx_action_logs_source', columns: ['source']),
        new ORM\Index(name: 'idx_action_logs_level', columns: ['level']),
        new ORM\Index(name: 'idx_action_logs_action', columns: ['action']),
    ]
)]
class ActionLog
{
    public const string SOURCE_CRON = 'CRON';
    public const string SOURCE_CLI = 'CLI';
    public const string SOURCE_WEB = 'WEB';

    public const string LEVEL_INFO = 'info';
    public const string LEVEL_WARNING = 'warning';
    public const string LEVEL_ERROR = 'error';

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
