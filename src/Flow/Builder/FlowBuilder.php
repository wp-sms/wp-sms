<?php

namespace WSms\Flow\Builder;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Flow\Contracts\Flow;

defined('ABSPATH') || exit;

class FlowBuilder
{
    private string $id;
    private string $name = '';
    private ?string $description = null;
    private string $triggerType = '';
    private array $triggerConfig = [];
    private array $steps = [];
    private int $priority = 0;

    /** @var array Stack for nested builders (condition branches, parallel branches) */
    private array $stack = [];

    private function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function create(string $id = ''): self
    {
        return new self($id ?: (string) new Ulid());
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function trigger(string $type, array $config = []): self
    {
        $this->triggerType = $type;
        $this->triggerConfig = $config;
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function action(string $actionId, array $config = []): self
    {
        $this->addStep([
            'id'     => 'step_' . (string) new Ulid(),
            'type'   => 'action',
            'action' => $actionId,
            'config' => $config,
        ]);
        return $this;
    }

    public function sendSms(string $gateway, string $to, string $body): self
    {
        return $this->action('send_message', [
            'gateway' => $gateway,
            'channel' => 'sms',
            'to'      => $to,
            'body'    => $body,
        ]);
    }

    public function sendEmail(string $gateway, string $to, string $subject, string $body): self
    {
        return $this->action('send_message', [
            'gateway' => $gateway,
            'channel' => 'email',
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
        ]);
    }

    public function httpRequest(string $method, string $url, array $body = []): self
    {
        return $this->action('http_request', [
            'method' => $method,
            'url'    => $url,
            'body'   => wp_json_encode($body),
        ]);
    }

    public function condition(string $expression): self
    {
        $this->stack[] = [
            'context' => 'condition',
            'id'      => 'step_' . (string) new Ulid(),
            'expression' => $expression,
            'then'    => [],
            'else'    => [],
            'branch'  => 'then',
        ];
        return $this;
    }

    public function then(): self
    {
        $frame = &$this->stack[count($this->stack) - 1];
        $frame['branch'] = 'then';
        return $this;
    }

    public function otherwise(): self
    {
        $frame = &$this->stack[count($this->stack) - 1];
        $frame['branch'] = 'else';
        return $this;
    }

    public function endCondition(): self
    {
        $frame = array_pop($this->stack);
        $this->addStep([
            'id'         => $frame['id'],
            'type'       => 'condition',
            'expression' => $frame['expression'],
            'then'       => $frame['then'],
            'else'       => $frame['else'],
        ]);
        return $this;
    }

    public function parallel(): self
    {
        $this->stack[] = [
            'context'  => 'parallel',
            'id'       => 'step_' . (string) new Ulid(),
            'branches' => [[]],
        ];
        return $this;
    }

    public function branch(): self
    {
        $frame = &$this->stack[count($this->stack) - 1];
        $frame['branches'][] = [];
        return $this;
    }

    public function endParallel(): self
    {
        $frame = array_pop($this->stack);
        $this->addStep([
            'id'       => $frame['id'],
            'type'     => 'parallel',
            'branches' => $frame['branches'],
        ]);
        return $this;
    }

    public function delay(int $seconds): self
    {
        $this->addStep([
            'id'       => 'step_' . (string) new Ulid(),
            'type'     => 'delay',
            'duration' => $seconds,
            'then'     => [],
        ]);
        return $this;
    }

    public function build(): Flow
    {
        return new Flow(
            id: $this->id,
            name: $this->name,
            triggerType: $this->triggerType,
            triggerConfig: $this->triggerConfig,
            steps: $this->steps,
            description: $this->description,
            priority: $this->priority,
        );
    }

    private function addStep(array $step): void
    {
        if (!empty($this->stack)) {
            $frame = &$this->stack[count($this->stack) - 1];

            if ($frame['context'] === 'condition') {
                $branch = $frame['branch'];
                $frame[$branch][] = $step;
            } elseif ($frame['context'] === 'parallel') {
                $lastIdx = count($frame['branches']) - 1;
                $frame['branches'][$lastIdx][] = $step;
            }
        } else {
            $this->steps[] = $step;
        }
    }
}
