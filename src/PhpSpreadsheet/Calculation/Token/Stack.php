<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Token;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Engine\Branch_Pruner;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Stack
{
    /**
     * The parser stack for formulae.
     *
     * @var array<int, array<mixed>>
     */
    private array $stack = [];
    /**
     * Count of entries in the parser stack.
     */
    private int $count = 0;
    public function __construct(private readonly Branch_Pruner $branch_pruner)
    {
    }
    /**
     * Return the number of entries on the stack.
     */
    public function count(): int
    {
        return $this->count;
    }
    /**
     * Push a new entry onto the stack.
     */
    public function push(string $type, mixed $value, ?string $reference = null): void
    {
        $stack_item = $this->get_stack_item($type, $value, $reference);
        $this->stack[$this->count++] = $stack_item;
        if ($type === 'Function') {
            $locale_function = Calculation::locale_func(String_Helper::convert_to_string($value));
            if ($locale_function != $value) {
                $this->stack[$this->count - 1]['localeValue'] = $locale_function;
            }
        }
    }
    /** @param array<mixed> $stackItem */
    public function push_stack_item(array $stack_item): void
    {
        $this->stack[$this->count++] = $stack_item;
    }
    /** @return array<mixed> */
    public function get_stack_item(string $type, mixed $value, ?string $reference = null): array
    {
        $stack_item = ['type' => $type, 'value' => $value, 'reference' => $reference];
        // will store the result under this alias
        $store_key = $this->branch_pruner->current_condition();
        if (isset($store_key) || $reference === 'NULL') {
            $stack_item['storeKey'] = $store_key;
        }
        // will only run computation if the matching store key is true
        $only_if = $this->branch_pruner->current_only_if();
        if (isset($only_if) || $reference === 'NULL') {
            $stack_item['onlyIf'] = $only_if;
        }
        // will only run computation if the matching store key is false
        $only_if_not = $this->branch_pruner->current_only_if_not();
        if (isset($only_if_not) || $reference === 'NULL') {
            $stack_item['onlyIfNot'] = $only_if_not;
        }
        return $stack_item;
    }
    /**
     * Pop the last entry from the stack.
     *
     * @return null|array<mixed>
     */
    public function pop(): ?array
    {
        if ($this->count > 0) {
            return $this->stack[--$this->count];
        }
        return null;
    }
    /**
     * Return an entry from the stack without removing it.
     *
     * @return null|array<mixed>
     */
    public function last(int $n = 1): ?array
    {
        if ($this->count - $n < 0) {
            return null;
        }
        return $this->stack[$this->count - $n];
    }
    /**
     * Clear the stack.
     */
    public function clear(): void
    {
        $this->stack = [];
        $this->count = 0;
    }
}