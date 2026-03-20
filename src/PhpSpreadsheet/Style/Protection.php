<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

class Protection extends Supervisor
{
    /** Protection styles */
    public const PROTECTION_INHERIT = 'inherit';
    public const PROTECTION_PROTECTED = 'protected';
    public const PROTECTION_UNPROTECTED = 'unprotected';
    /**
     * Locked.
     */
    protected ?string $locked = null;
    /**
     * Hidden.
     */
    protected ?string $hidden = null;
    /**
     * Create a new Protection.
     *
     * @param bool $isSupervisor Flag indicating if this is a supervisor or not
     *                                    Leave this value at default unless you understand exactly what
     *                                        its ramifications are
     * @param bool $isConditional Flag indicating if this is a conditional style or not
     *                                    Leave this value at default unless you understand exactly what
     *                                        its ramifications are
     */
    public function __construct(bool $is_supervisor = false, bool $is_conditional = false)
    {
        // Supervisor?
        parent::__construct($is_supervisor);
        // Initialise values
        if (!$is_conditional) {
            $this->locked = self::PROTECTION_INHERIT;
            $this->hidden = self::PROTECTION_INHERIT;
        }
    }
    /**
     * Get the shared style component for the currently active cell in currently active sheet.
     * Only used for style supervisor.
     */
    public function get_shared_component(): self
    {
        /** @var Style $parent */
        $parent = $this->parent;
        return $parent->get_shared_component()->get_protection();
    }
    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return array{protection: mixed[]}
     */
    public function get_style_array(array $array): array
    {
        return ['protection' => $array];
    }
    /**
     * Apply styles from array.
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getLocked()->applyFromArray(
     *     [
     *         'locked' => TRUE,
     *         'hidden' => FALSE
     *     ]
     * );
     * </code>
     *
     * @param array{locked?: string, hidden?: string} $styleArray Array containing style information
     *
     * @return $this
     */
    public function apply_from_array(array $style_array): static
    {
        if ($this->is_supervisor) {
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($this->get_style_array($style_array));
        } else {
            if (isset($style_array['locked'])) {
                $this->set_locked($style_array['locked']);
            }
            if (isset($style_array['hidden'])) {
                $this->set_hidden($style_array['hidden']);
            }
        }
        return $this;
    }
    /**
     * Get locked.
     */
    public function get_locked(): ?string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_locked();
        }
        return $this->locked;
    }
    /**
     * Set locked.
     *
     * @param string $lockType see self::PROTECTION_*
     *
     * @return $this
     */
    public function set_locked(string $lock_type): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['locked' => $lock_type]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->locked = $lock_type;
        }
        return $this;
    }
    /**
     * Get hidden.
     */
    public function get_hidden(): ?string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_hidden();
        }
        return $this->hidden;
    }
    /**
     * Set hidden.
     *
     * @param string $hiddenType see self::PROTECTION_*
     *
     * @return $this
     */
    public function set_hidden(string $hidden_type): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['hidden' => $hidden_type]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->hidden = $hidden_type;
        }
        return $this;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_hash_code();
        }
        return md5($this->locked . $this->hidden . self::class);
    }
    /** @return mixed[] */
    protected function export_array1(): array
    {
        $exported_array = [];
        $this->export_array2($exported_array, 'locked', $this->get_locked());
        $this->export_array2($exported_array, 'hidden', $this->get_hidden());
        return $exported_array;
    }
}