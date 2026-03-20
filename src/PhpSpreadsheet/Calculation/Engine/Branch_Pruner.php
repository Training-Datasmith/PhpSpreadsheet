<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engine;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
class Branch_Pruner
{
    /**
     * Used to generate unique store keys.
     */
    private int $branch_store_key_counter = 0;
    /**
     * currently pending storeKey (last item of the storeKeysStack.
     */
    protected ?string $pending_store_key = null;
    /**
     * @var string[]
     */
    protected array $store_keys_stack = [];
    /**
     * @var bool[]
     */
    protected array $condition_map = [];
    /**
     * @var bool[]
     */
    protected array $then_map = [];
    /**
     * @var bool[]
     */
    protected array $else_map = [];
    /**
     * @var int[]
     */
    protected array $brace_depth_map = [];
    protected ?string $current_condition = null;
    protected ?string $current_only_if = null;
    protected ?string $current_only_if_not = null;
    protected ?string $previous_store_key = null;
    public function __construct(protected bool $branch_pruning_enabled)
    {
    }
    public function clear_branch_store(): void
    {
        $this->branch_store_key_counter = 0;
    }
    public function initialise_for_loop(): void
    {
        $this->current_condition = null;
        $this->current_only_if = null;
        $this->current_only_if_not = null;
        $this->previous_store_key = null;
        $this->pending_store_key = empty($this->store_keys_stack) ? null : end($this->store_keys_stack);
        if ($this->branch_pruning_enabled) {
            $this->initialise_condition();
            $this->initialise_then();
            $this->initialise_else();
        }
    }
    private function initialise_condition(): void
    {
        if (isset($this->pending_store_key, $this->condition_map[$this->pending_store_key]) && $this->condition_map[$this->pending_store_key]) {
            $this->current_condition = $this->pending_store_key;
            $stack_depth = count($this->store_keys_stack);
            if ($stack_depth > 1) {
                // nested if
                $this->previous_store_key = $this->store_keys_stack[$stack_depth - 2];
            }
        }
    }
    private function initialise_then(): void
    {
        if (isset($this->pending_store_key, $this->then_map[$this->pending_store_key]) && $this->then_map[$this->pending_store_key]) {
            $this->current_only_if = $this->pending_store_key;
        } elseif (isset($this->previous_store_key, $this->then_map[$this->previous_store_key]) && $this->then_map[$this->previous_store_key]) {
            $this->current_only_if = $this->previous_store_key;
        }
    }
    private function initialise_else(): void
    {
        if (isset($this->pending_store_key, $this->else_map[$this->pending_store_key]) && $this->else_map[$this->pending_store_key]) {
            $this->current_only_if_not = $this->pending_store_key;
        } elseif (isset($this->previous_store_key, $this->else_map[$this->previous_store_key]) && $this->else_map[$this->previous_store_key]) {
            $this->current_only_if_not = $this->previous_store_key;
        }
    }
    public function decrement_depth(): void
    {
        if (!empty($this->pending_store_key)) {
            --$this->brace_depth_map[$this->pending_store_key];
        }
    }
    public function increment_depth(): void
    {
        if (!empty($this->pending_store_key)) {
            ++$this->brace_depth_map[$this->pending_store_key];
        }
    }
    public function function_call(string $function_name): void
    {
        if ($this->branch_pruning_enabled && $function_name === 'IF(') {
            // we handle a new if
            $this->pending_store_key = $this->get_unused_branch_store_key();
            $this->store_keys_stack[] = $this->pending_store_key;
            $this->condition_map[$this->pending_store_key] = true;
            $this->brace_depth_map[$this->pending_store_key] = 0;
        } elseif (!empty($this->pending_store_key) && array_key_exists($this->pending_store_key, $this->brace_depth_map)) {
            // this is not an if but we go deeper
            ++$this->brace_depth_map[$this->pending_store_key];
        }
    }
    public function argument_separator(): void
    {
        if (!empty($this->pending_store_key) && $this->brace_depth_map[$this->pending_store_key] === 0) {
            // We must go to the IF next argument
            if ($this->condition_map[$this->pending_store_key]) {
                $this->condition_map[$this->pending_store_key] = false;
                $this->then_map[$this->pending_store_key] = true;
            } elseif ($this->then_map[$this->pending_store_key]) {
                $this->then_map[$this->pending_store_key] = false;
                $this->else_map[$this->pending_store_key] = true;
            } elseif ($this->else_map[$this->pending_store_key]) {
                throw new Exception('Reaching fourth argument of an IF');
            }
        }
    }
    public function closing_brace(mixed $value): void
    {
        if (!empty($this->pending_store_key) && $this->brace_depth_map[$this->pending_store_key] === -1) {
            // we are closing an IF(
            if ($value !== 'IF(') {
                throw new Exception('Parser bug we should be in an "IF("');
            }
            if ($this->condition_map[$this->pending_store_key]) {
                throw new Exception('We should not be expecting a condition');
            }
            $this->then_map[$this->pending_store_key] = false;
            $this->else_map[$this->pending_store_key] = false;
            --$this->brace_depth_map[$this->pending_store_key];
            array_pop($this->store_keys_stack);
            $this->pending_store_key = null;
        }
    }
    public function current_condition(): ?string
    {
        return $this->current_condition;
    }
    public function current_only_if(): ?string
    {
        return $this->current_only_if;
    }
    public function current_only_if_not(): ?string
    {
        return $this->current_only_if_not;
    }
    private function get_unused_branch_store_key(): string
    {
        $store_key_value = 'storeKey-' . $this->branch_store_key_counter;
        ++$this->branch_store_key_counter;
        return $store_key_value;
    }
}