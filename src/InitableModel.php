<?php

namespace codesaur\DataObject;

class InitableModel extends Model implements InitableInterface
{
    public function setTable(?string $name = null): bool
    {
        if (!parent::setTable($name)) {
            return false;
        }

        $this->initial();
        
        return true;
    }
    
    public function initial(): bool
    {
        return false;
    }
}
