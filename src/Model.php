<?php

namespace codesaur\DataObject;

class Model extends Table
{
    public function setTable(?string $name = null): bool
    {
        if (!parent::setTable($name)) {
            return false;
        }

        $this->initial();
        
        return true;
    }
    
    public function initial()
    {
    }

    public function insert(array $record)
    {
        if ($this->hasColumn('created_at')
                && !isset($record['created_at'])
        ) {
            $record['created_at'] = date('Y-m-d H:i:s');
        }

        if (getenv('CODESAUR_ACCOUNT_ID', true)
                && $this->hasColumn('created_by')
                && !isset($record['created_by'])
        ) {
            $record['created_by'] = (int)getenv('CODESAUR_ACCOUNT_ID', true);
        }

        return parent::insert($record);
    }
    
    public function update(
            array  $record,
            array  $where = [],
            string $condition = ''
    ) {
        if ($this->hasColumn('updated_at')
                && !isset($record['updated_at'])
        ) {
            $record['updated_at'] = date('Y-m-d H:i:s');
        }
        
        if (getenv('CODESAUR_ACCOUNT_ID', true)
                && $this->hasColumn('updated_by')
                && !isset($record['updated_by'])
        ) {
            $record['updated_by'] = (int)getenv('CODESAUR_ACCOUNT_ID', true);
        }
        
        return parent::update($record, $where, $condition);
    }
}
