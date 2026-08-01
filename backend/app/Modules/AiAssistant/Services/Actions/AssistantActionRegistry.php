<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use InvalidArgumentException;

final class AssistantActionRegistry
{
    /** @var array<string, AssistantActionInterface> */
    private array $actions = [];

    /**
     * @param  iterable<AssistantActionInterface>  $actions
     */
    public function __construct(iterable $actions = [])
    {
        foreach ($actions as $action) {
            $this->register($action);
        }
    }

    public function register(AssistantActionInterface $action): void
    {
        $name = $action->name();
        if (isset($this->actions[$name])) {
            throw new InvalidArgumentException("Duplicate assistant action registered: {$name}");
        }

        $this->actions[$name] = $action;
    }

    public function has(string $name): bool
    {
        return isset($this->actions[$name]);
    }

    public function get(string $name): AssistantActionInterface
    {
        if (! isset($this->actions[$name])) {
            throw new InvalidArgumentException("Action not allowlisted: {$name}");
        }

        return $this->actions[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->actions);
    }
}
