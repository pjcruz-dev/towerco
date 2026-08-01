<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use InvalidArgumentException;

/**
 * Explicit allowlist of read-only assistant tools.
 */
final class AssistantToolRegistry
{
    /** @var array<string, AssistantToolInterface> */
    private array $tools = [];

    /**
     * @param  iterable<AssistantToolInterface>  $tools
     */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(AssistantToolInterface $tool): void
    {
        $name = $tool->name();
        if (isset($this->tools[$name])) {
            throw new InvalidArgumentException("Duplicate assistant tool registered: {$name}");
        }

        $this->tools[$name] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): AssistantToolInterface
    {
        if (! isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool not allowlisted: {$name}");
        }

        return $this->tools[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * @return list<AssistantToolInterface>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }
}
