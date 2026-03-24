<?php

namespace codesaur\DataObject;

/**
 * Final class Constants
 *
 * DataObject экосистемийн бүх тогтмол утгуудыг нэг дор төвлөрүүлнө.
 *
 * @package codesaur\DataObject
 */
final class Constants
{
    // --- Database Driver Names ---
    public const DRIVER_MYSQL  = 'mysql';
    public const DRIVER_PGSQL  = 'pgsql';
    public const DRIVER_SQLITE = 'sqlite';

    // --- Error Codes ---
    public const ERR_TABLE_NAME_MISSING  = 1103;
    public const ERR_COLUMNS_NOT_DEFINED = 1113;
    public const ERR_COLUMN_NOT_FOUND    = 1054;

    // --- Table Name Sanitization ---
    public const TABLE_NAME_PATTERN = '/[^A-Za-z0-9_-]/';

    // --- Structural Column Names ---
    public const COL_ID        = 'id';
    public const COL_IS_ACTIVE = 'is_active';
    public const COL_PARENT_ID = 'parent_id';
    public const COL_CODE      = 'code';

    // --- Localized Model ---
    public const CONTENT_TABLE_SUFFIX = '_content';
    public const CONTENT_KEY_COLUMNS  = [self::COL_ID, self::COL_PARENT_ID, self::COL_CODE];
    public const LOCALIZED_KEY        = 'localized';
    public const PRIMARY_ALIAS_PREFIX = 'p_';
    public const CONTENT_ALIAS_PREFIX = 'c_';
    public const DEFAULT_CODE_LENGTH  = 2;
}
