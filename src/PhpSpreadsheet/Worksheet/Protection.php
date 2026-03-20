<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Shared\Password_Hasher;
class Protection
{
    public const ALGORITHM_MD2 = 'MD2';
    public const ALGORITHM_MD4 = 'MD4';
    public const ALGORITHM_MD5 = 'MD5';
    public const ALGORITHM_SHA_1 = 'SHA-1';
    public const ALGORITHM_SHA_256 = 'SHA-256';
    public const ALGORITHM_SHA_384 = 'SHA-384';
    public const ALGORITHM_SHA_512 = 'SHA-512';
    public const ALGORITHM_RIPEMD_128 = 'RIPEMD-128';
    public const ALGORITHM_RIPEMD_160 = 'RIPEMD-160';
    public const ALGORITHM_WHIRLPOOL = 'WHIRLPOOL';
    /**
     * Autofilters are locked when sheet is protected, default true.
     */
    private ?bool $auto_filter = null;
    /**
     * Deleting columns is locked when sheet is protected, default true.
     */
    private ?bool $delete_columns = null;
    /**
     * Deleting rows is locked when sheet is protected, default true.
     */
    private ?bool $delete_rows = null;
    /**
     * Formatting cells is locked when sheet is protected, default true.
     */
    private ?bool $format_cells = null;
    /**
     * Formatting columns is locked when sheet is protected, default true.
     */
    private ?bool $format_columns = null;
    /**
     * Formatting rows is locked when sheet is protected, default true.
     */
    private ?bool $format_rows = null;
    /**
     * Inserting columns is locked when sheet is protected, default true.
     */
    private ?bool $insert_columns = null;
    /**
     * Inserting hyperlinks is locked when sheet is protected, default true.
     */
    private ?bool $insert_hyperlinks = null;
    /**
     * Inserting rows is locked when sheet is protected, default true.
     */
    private ?bool $insert_rows = null;
    /**
     * Objects are locked when sheet is protected, default false.
     */
    private ?bool $objects = null;
    /**
     * Pivot tables are locked when the sheet is protected, default true.
     */
    private ?bool $pivot_tables = null;
    /**
     * Scenarios are locked when sheet is protected, default false.
     */
    private ?bool $scenarios = null;
    /**
     * Selection of locked cells is locked when sheet is protected, default false.
     */
    private ?bool $select_locked_cells = null;
    /**
     * Selection of unlocked cells is locked when sheet is protected, default false.
     */
    private ?bool $select_unlocked_cells = null;
    /**
     * Sheet is locked when sheet is protected, default false.
     */
    private ?bool $sheet = null;
    /**
     * Sorting is locked when sheet is protected, default true.
     */
    private ?bool $sort = null;
    /**
     * Hashed password.
     */
    private string $password = '';
    /**
     * Algorithm name.
     */
    private string $algorithm = '';
    /**
     * Salt value.
     */
    private string $salt = '';
    /**
     * Spin count.
     */
    private int $spin_count = 10000;
    /**
     * Is some sort of protection enabled?
     */
    public function is_protection_enabled(): bool
    {
        return $this->password !== '' || isset($this->sheet) || isset($this->objects) || isset($this->scenarios) || isset($this->format_cells) || isset($this->format_columns) || isset($this->format_rows) || isset($this->insert_columns) || isset($this->insert_rows) || isset($this->insert_hyperlinks) || isset($this->delete_columns) || isset($this->delete_rows) || isset($this->select_locked_cells) || isset($this->sort) || isset($this->auto_filter) || isset($this->pivot_tables) || isset($this->select_unlocked_cells);
    }
    public function get_sheet(): ?bool
    {
        return $this->sheet;
    }
    public function set_sheet(?bool $sheet): self
    {
        $this->sheet = $sheet;
        return $this;
    }
    public function get_objects(): ?bool
    {
        return $this->objects;
    }
    public function set_objects(?bool $objects): self
    {
        $this->objects = $objects;
        return $this;
    }
    public function get_scenarios(): ?bool
    {
        return $this->scenarios;
    }
    public function set_scenarios(?bool $scenarios): self
    {
        $this->scenarios = $scenarios;
        return $this;
    }
    public function get_format_cells(): ?bool
    {
        return $this->format_cells;
    }
    public function set_format_cells(?bool $format_cells): self
    {
        $this->format_cells = $format_cells;
        return $this;
    }
    public function get_format_columns(): ?bool
    {
        return $this->format_columns;
    }
    public function set_format_columns(?bool $format_columns): self
    {
        $this->format_columns = $format_columns;
        return $this;
    }
    public function get_format_rows(): ?bool
    {
        return $this->format_rows;
    }
    public function set_format_rows(?bool $format_rows): self
    {
        $this->format_rows = $format_rows;
        return $this;
    }
    public function get_insert_columns(): ?bool
    {
        return $this->insert_columns;
    }
    public function set_insert_columns(?bool $insert_columns): self
    {
        $this->insert_columns = $insert_columns;
        return $this;
    }
    public function get_insert_rows(): ?bool
    {
        return $this->insert_rows;
    }
    public function set_insert_rows(?bool $insert_rows): self
    {
        $this->insert_rows = $insert_rows;
        return $this;
    }
    public function get_insert_hyperlinks(): ?bool
    {
        return $this->insert_hyperlinks;
    }
    public function set_insert_hyperlinks(?bool $insert_hyper_links): self
    {
        $this->insert_hyperlinks = $insert_hyper_links;
        return $this;
    }
    public function get_delete_columns(): ?bool
    {
        return $this->delete_columns;
    }
    public function set_delete_columns(?bool $delete_columns): self
    {
        $this->delete_columns = $delete_columns;
        return $this;
    }
    public function get_delete_rows(): ?bool
    {
        return $this->delete_rows;
    }
    public function set_delete_rows(?bool $delete_rows): self
    {
        $this->delete_rows = $delete_rows;
        return $this;
    }
    public function get_select_locked_cells(): ?bool
    {
        return $this->select_locked_cells;
    }
    public function set_select_locked_cells(?bool $select_locked_cells): self
    {
        $this->select_locked_cells = $select_locked_cells;
        return $this;
    }
    public function get_sort(): ?bool
    {
        return $this->sort;
    }
    public function set_sort(?bool $sort): self
    {
        $this->sort = $sort;
        return $this;
    }
    public function get_auto_filter(): ?bool
    {
        return $this->auto_filter;
    }
    public function set_auto_filter(?bool $auto_filter): self
    {
        $this->auto_filter = $auto_filter;
        return $this;
    }
    public function get_pivot_tables(): ?bool
    {
        return $this->pivot_tables;
    }
    public function set_pivot_tables(?bool $pivot_tables): self
    {
        $this->pivot_tables = $pivot_tables;
        return $this;
    }
    public function get_select_unlocked_cells(): ?bool
    {
        return $this->select_unlocked_cells;
    }
    public function set_select_unlocked_cells(?bool $select_unlocked_cells): self
    {
        $this->select_unlocked_cells = $select_unlocked_cells;
        return $this;
    }
    /**
     * Get hashed password.
     */
    public function get_password(): string
    {
        return $this->password;
    }
    /**
     * Set Password.
     *
     * @param bool $alreadyHashed If the password has already been hashed, set this to true
     *
     * @return $this
     */
    public function set_password(string $password, bool $already_hashed = false): static
    {
        if (!$already_hashed) {
            $salt = $this->generate_salt();
            $this->set_salt($salt);
            $password = Password_Hasher::hash_password($password, $this->get_algorithm(), $this->get_salt(), $this->get_spin_count());
        }
        $this->password = $password;
        return $this;
    }
    public function set_hash_value(string $password): self
    {
        return $this->set_password($password, true);
    }
    /**
     * Create a pseudorandom string.
     */
    private function generate_salt(): string
    {
        return base64_encode(random_bytes(16));
    }
    /**
     * Get algorithm name.
     */
    public function get_algorithm(): string
    {
        return $this->algorithm;
    }
    /**
     * Set algorithm name.
     */
    public function set_algorithm(string $algorithm): self
    {
        return $this->set_algorithm_name($algorithm);
    }
    /**
     * Set algorithm name.
     */
    public function set_algorithm_name(string $algorithm): self
    {
        $this->algorithm = $algorithm;
        return $this;
    }
    public function get_salt(): string
    {
        return $this->salt;
    }
    public function set_salt(string $salt): self
    {
        return $this->set_salt_value($salt);
    }
    public function set_salt_value(string $salt): self
    {
        $this->salt = $salt;
        return $this;
    }
    /**
     * Get spin count.
     */
    public function get_spin_count(): int
    {
        return $this->spin_count;
    }
    /**
     * Set spin count.
     */
    public function set_spin_count(int $spin_count): self
    {
        $this->spin_count = $spin_count;
        return $this;
    }
    /**
     * Verify that the given non-hashed password can "unlock" the protection.
     */
    public function verify(string $password): bool
    {
        if ($this->password === '') {
            return true;
        }
        $hash = Password_Hasher::hash_password($password, $this->get_algorithm(), $this->get_salt(), $this->get_spin_count());
        return $this->get_password() === $hash;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if (is_object($value)) {
                $this->{$key} = clone $value;
            } else {
                $this->{$key} = $value;
            }
        }
    }
}