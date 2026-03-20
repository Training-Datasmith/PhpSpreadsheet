<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Document;

use Php_Office\Php_Spreadsheet\Shared\Password_Hasher;
class Security
{
    /**
     * LockRevision.
     */
    private bool $lock_revision = false;
    /**
     * LockStructure.
     */
    private bool $lock_structure = false;
    /**
     * LockWindows.
     */
    private bool $lock_windows = false;
    /**
     * RevisionsPassword.
     */
    private string $revisions_password = '';
    /**
     * WorkbookPassword.
     */
    private string $workbook_password = '';
    private string $workbook_algorithm_name = '';
    private string $workbook_hash_value = '';
    private string $workbook_salt_value = '';
    private int $workbook_spin_count = 0;
    private string $revisions_algorithm_name = '';
    private string $revisions_hash_value = '';
    private string $revisions_salt_value = '';
    private int $revisions_spin_count = 0;
    /**
     * Is some sort of document security enabled?
     */
    public function is_security_enabled(): bool
    {
        return $this->lock_revision || $this->lock_structure || $this->lock_windows;
    }
    public function get_lock_revision(): bool
    {
        return $this->lock_revision;
    }
    public function set_lock_revision(?bool $locked): self
    {
        if ($locked !== null) {
            $this->lock_revision = $locked;
        }
        return $this;
    }
    public function get_lock_structure(): bool
    {
        return $this->lock_structure;
    }
    public function set_lock_structure(?bool $locked): self
    {
        if ($locked !== null) {
            $this->lock_structure = $locked;
        }
        return $this;
    }
    public function get_lock_windows(): bool
    {
        return $this->lock_windows;
    }
    public function set_lock_windows(?bool $locked): self
    {
        if ($locked !== null) {
            $this->lock_windows = $locked;
        }
        return $this;
    }
    public function get_revisions_password(): string
    {
        return $this->revisions_password;
    }
    /**
     * Set RevisionsPassword.
     *
     * @param bool $alreadyHashed If the password has already been hashed, set this to true
     *
     * @return $this
     */
    public function set_revisions_password(?string $password, bool $already_hashed = false): static
    {
        if ($password !== null) {
            if ($this->advanced_revisions_password()) {
                if (!$already_hashed) {
                    $password = Password_Hasher::hash_password($password, $this->revisions_algorithm_name, $this->revisions_salt_value, $this->revisions_spin_count);
                }
                $this->revisions_hash_value = $password;
                $this->revisions_password = '';
            } else {
                if (!$already_hashed) {
                    $password = Password_Hasher::hash_password($password);
                }
                $this->revisions_password = $password;
            }
        }
        return $this;
    }
    public function get_workbook_password(): string
    {
        return $this->workbook_password;
    }
    /**
     * Set WorkbookPassword.
     *
     * @param bool $alreadyHashed If the password has already been hashed, set this to true
     *
     * @return $this
     */
    public function set_workbook_password(?string $password, bool $already_hashed = false): static
    {
        if ($password !== null) {
            if ($this->advanced_password()) {
                if (!$already_hashed) {
                    $password = Password_Hasher::hash_password($password, $this->workbook_algorithm_name, $this->workbook_salt_value, $this->workbook_spin_count);
                }
                $this->workbook_hash_value = $password;
                $this->workbook_password = '';
            } else {
                if (!$already_hashed) {
                    $password = Password_Hasher::hash_password($password);
                }
                $this->workbook_password = $password;
            }
        }
        return $this;
    }
    public function get_workbook_hash_value(): string
    {
        return $this->advanced_password() ? $this->workbook_hash_value : '';
    }
    public function advanced_password(): bool
    {
        return $this->workbook_algorithm_name !== '' && $this->workbook_salt_value !== '' && $this->workbook_spin_count > 0;
    }
    public function get_workbook_algorithm_name(): string
    {
        return $this->workbook_algorithm_name;
    }
    public function set_workbook_algorithm_name(string $workbook_algorithm_name): static
    {
        $this->workbook_algorithm_name = $workbook_algorithm_name;
        return $this;
    }
    public function get_workbook_spin_count(): int
    {
        return $this->workbook_spin_count;
    }
    public function set_workbook_spin_count(int $workbook_spin_count): static
    {
        $this->workbook_spin_count = $workbook_spin_count;
        return $this;
    }
    public function get_workbook_salt_value(): string
    {
        return $this->workbook_salt_value;
    }
    public function set_workbook_salt_value(string $workbook_salt_value, bool $base64Required): static
    {
        $this->workbook_salt_value = $base64Required ? base64_encode($workbook_salt_value) : $workbook_salt_value;
        return $this;
    }
    public function get_revisions_hash_value(): string
    {
        return $this->advanced_revisions_password() ? $this->revisions_hash_value : '';
    }
    public function advanced_revisions_password(): bool
    {
        return $this->revisions_algorithm_name !== '' && $this->revisions_salt_value !== '' && $this->revisions_spin_count > 0;
    }
    public function get_revisions_algorithm_name(): string
    {
        return $this->revisions_algorithm_name;
    }
    public function set_revisions_algorithm_name(string $revisions_algorithm_name): static
    {
        $this->revisions_algorithm_name = $revisions_algorithm_name;
        return $this;
    }
    public function get_revisions_spin_count(): int
    {
        return $this->revisions_spin_count;
    }
    public function set_revisions_spin_count(int $revisions_spin_count): static
    {
        $this->revisions_spin_count = $revisions_spin_count;
        return $this;
    }
    public function get_revisions_salt_value(): string
    {
        return $this->revisions_salt_value;
    }
    public function set_revisions_salt_value(string $revisions_salt_value, bool $base64Required): static
    {
        $this->revisions_salt_value = $base64Required ? base64_encode($revisions_salt_value) : $revisions_salt_value;
        return $this;
    }
}