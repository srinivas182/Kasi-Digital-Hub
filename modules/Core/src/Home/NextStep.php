<?php

declare(strict_types=1);

namespace Modules\Core\Home;

/**
 * One item in "Your next steps" on the hub home.
 */
final readonly class NextStep
{
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $href,
        public bool $done,
        public int $priority = 50,
    ) {}

    /**
     * @return array{key: string, title: string, description: string, href: string, done: bool}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'title' => $this->title, 'description' => $this->description, 'href' => $this->href, 'done' => $this->done];
    }
}
