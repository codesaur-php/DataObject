<?php

namespace codesaur\DataObject;

/**
 * Trait PDOTrait
 *
 * Энэ trait нь PDO дээр суурилсан өгөгдлийн баазын
 * ерөнхий үйлдлүүдийг (prepare, query, exec, quote, driver төрлийг авах)
 * нэг стандарт интерфэйс болгон төвлөрүүлдэг.
 *
 * codesaur экосистемийн бүх Model болон Table-тэй холбоотой классууд
 * энэ trait-ийг ашигласнаар:
 *
 *  - PDO instance-г хуваалцана
 *  - SQL statement-үүдийг найдвартай бэлтгэнэ
 *  - Алдааг стандарт хэлбэрээр шиднэ
 *  - MySQL/PostgreSQL/SQLite драйверийг автоматаар танина
 *
 * @package codesaur\DataObject
 */
trait PDOTrait
{
    /**
     * PDO instance.
     *
     * @var PDO
     */
    protected \PDO $pdo;

    /**
     * Ашиглаж буй драйверийн нэр (mysql | pgsql | sqlite).
     * @var string|null
     */
    private ?string $_driver;

    /**
     * PDO instance-г загварт оноож өгөх.
     *
     * @param PDO $pdo
     * @return void
     */
    public final function setInstance(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Ашиглаж буй PDO драйверийн нэрийг буцаана.
     * Жишээ: mysql, pgsql
     *
     * @return string|null
     */
    public final function getDriverName()
    {
        if (empty($this->_driver)) {
            $this->_driver = \strtolower($this->pdo?->getAttribute(\PDO::ATTR_DRIVER_NAME));
        }

        return $this->_driver;
    }

    /**
     * PDO/PDOStatement-ийн алдааны мэдээллээр Exception шидэх.
     *
     * @param string $message Алдааны тайлбар
     * @param \PDO|\PDOStatement $source Алдааны эх үүсвэр
     * @throws \Exception
     */
    protected final function throwPdoError(string $message, \PDO|\PDOStatement $source): never
    {
        $error_info = $source->errorInfo();

        throw new \Exception($message . \implode(': ', $error_info), (int)($error_info[1] ?? 0));
    }

    /**
     * SQL string-ийг драйверт тохирсон хэлбэрээр escape хийх.
     *
     * @param string $string SQL-д ашиглах текст
     * @param int $parameter_type PDO::PARAM_* төрөл
     * @return string|false
     */
    public final function quote(string $string, int $parameter_type = \PDO::PARAM_STR): string|false
    {
        return $this->pdo->quote($string, $parameter_type);
    }

    /**
     * SQL statement-г бэлтгэж (prepare) PDOStatement буцаана.
     * Амжилтгүй бол стандарт Exception шиднэ.
     *
     * @param string $statement Бэлтгэх SQL
     * @param array $driver_options PDO-ийн драйвер тохиргоо
     * @return PDOStatement
     * @throws Exception
     */
    public final function prepare(string $statement, array $driver_options = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($statement, $driver_options);
        if ($stmt != false) {
            return $stmt;
        }

        $this->throwPdoError(__CLASS__ . ': PDO prepare error! ', $this->pdo);
    }

    /**
     * SQL команд шууд гүйцэтгэх (DDL/DML).
     *
     * @param string $statement SQL команд (CREATE, DROP, UPDATE гэх мэт)
     * @return int|false Нөлөөлсөн мөрийн тоо буюу false
     */
    public final function exec(string $statement): int|false
    {
        return $this->pdo->exec($statement);
    }

    /**
     * Query-г бэлтгэлгүйгээр шууд гүйцэтгэх.
     *
     * @param string $statement SQL SELECT
     * @return PDOStatement
     * @throws Exception
     */
    public final function query(string $statement): \PDOStatement
    {
        $stmt = $this->pdo->query($statement);
        if ($stmt != false) {
            return $stmt;
        }

        $this->throwPdoError(__CLASS__ . ': PDO query error! ', $this->pdo);
    }

    /**
     * Өгөгдлийн баазад хүснэгт байгаа эсэхийг шалгах.
     *
     * @param string $table Хүснэгтийн нэр
     * @return bool
     * @throws RuntimeException Дэмжээгүй драйвер
     */
    public final function hasTable(string $table): bool
    {
        switch ($this->getDriverName()) {
            case Constants::DRIVER_MYSQL:
                return $this->query('SHOW TABLES LIKE ' . $this->quote($table))->rowCount() > 0;

            case Constants::DRIVER_PGSQL:
                return $this->query("SELECT tablename
                    FROM pg_tables
                    WHERE schemaname='public'
                    AND tablename=" . $this->quote($table))->rowCount() > 0;

            case Constants::DRIVER_SQLITE: {
                $stmt = $this->query("SELECT name
                    FROM sqlite_master
                    WHERE type='table'
                    AND name=" . $this->quote($table));
                return $stmt->fetch() !== false;
            }

            default:
                throw new \RuntimeException("Driver not supported");
        }
    }
}
