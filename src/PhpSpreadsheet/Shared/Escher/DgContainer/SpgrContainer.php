<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container;

class Spgr_Container
{
    /**
     * Parent Shape Group Container.
     */
    private ?self $parent = null;
    /**
     * Shape Container collection.
     *
     * @var mixed[]
     */
    private array $children = [];
    /**
     * Set parent Shape Group Container.
     */
    public function set_parent(?self $parent): void
    {
        $this->parent = $parent;
    }
    /**
     * Get the parent Shape Group Container if any.
     */
    public function get_parent(): ?self
    {
        return $this->parent;
    }
    /**
     * Add a child. This will be either spgrContainer or spContainer.
     *
     * @param SpgrContainer|SpgrContainer\SpContainer $child child to be added
     */
    public function add_child(mixed $child): void
    {
        $this->children[] = $child;
        $child->set_parent($this);
    }
    /**
     * Get collection of Shape Containers.
     *
     * @return mixed[]
     */
    public function get_children(): array
    {
        return $this->children;
    }
    /**
     * Recursively get all spContainers within this spgrContainer.
     *
     * @return SpgrContainer\SpContainer[]
     */
    public function get_all_sp_containers(): array
    {
        $all_sp_containers = [];
        foreach ($this->children as $child) {
            if ($child instanceof self) {
                $all_sp_containers = array_merge($all_sp_containers, $child->get_all_sp_containers());
            } else {
                $all_sp_containers[] = $child;
            }
        }
        /** @var SpgrContainer\SpContainer[] $allSpContainers */
        return $all_sp_containers;
    }
}