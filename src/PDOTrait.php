<?php

namespace codesaur\DataObject;

/**
 * Trait PDOTrait
 *
 * Энэ trait нь PDO дээр суурилсан өгөгдлийн баазын
 * ерөнхий үйлдлүүдийг (prepare, query, exec, quote, driver төрлийг авах)
 * нэг стандарт интерфэйс болгон төвлөрүүлдэг.
 *
 * DataObject экосистемийн бүх Model болон Table-тэй холбоотой классууд
 * энэ trait-ийг ашигласнаар:
 *
 *  - PDO instance-г хуваалцана
 *  - SQL statement-үүдийг найдвартай бэлтгэнэ
 *  - Алдааг стандарт хэлбэрээр шиднэ
 *  - MySQL/PostgreSQL драйверийг автоматаар танина
 *  - FOREIGN KEY CHECKS зэрэг тохиргоог удирдана
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
     * Ашиглаж буй драйверийн нэр (mysql | pgsql).
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

        $error_info = $this->pdo->errorInfo();
        if (\is_numeric($error_info[1] ?? null)) {
            $error_code = (int) $error_info[1];
        } elseif (\is_numeric($this->pdo->errorCode())) {
            $error_code = (int) $this->pdo->errorCode();
        } else {
            $error_code = 0;
        }

        throw new \Exception(__CLASS__ . ': PDO error! ' . \implode(': ', $error_info), $error_code);
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

        $error_info = $this->pdo->errorInfo();
        if (\is_numeric($error_info[1] ?? null)) {
            $error_code = (int) $error_info[1];
        } elseif (\is_numeric($this->pdo->errorCode())) {
            $error_code = (int) $this->pdo->errorCode();
        } else {
            $error_code = 0;
        }

        throw new \Exception(__CLASS__ . ': PDO error! ' . \implode(': ', $error_info), $error_code);
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
            case 'mysql':
                return $this->query('SHOW TABLES LIKE ' . $this->quote($table))->rowCount() > 0;

            case 'pgsql':
                return $this->query("SELECT tablename 
                    FROM pg_tables 
                    WHERE schemaname='public' 
                    AND tablename=" . $this->quote($table))->rowCount() > 0;

            default:
                throw new \RuntimeException("Driver not supported");
        }
    }

    /**
     * FOREIGN KEY constraints-г асаах/унтраах.
     * 
     * MySQL → SET foreign_key_checks  
     * PostgreSQL → SET session_replication_role
     *
     * @param bool $enable TRUE=асаах, FALSE=унтраах
     * @return int|false
     * @throws RuntimeException
     */
    public final function setForeignKeyChecks(bool $enable): int|false
    {
        switch ($this->getDriverName()) {
            case 'mysql':
                return $this->exec('SET foreign_key_checks=' . ($enable ? 1 : 0));

            case 'pgsql':
                return $this->exec('SET session_replication_role = ' . $this->quote($enable ? 'origin' : 'replica'));

            default:
                throw new \RuntimeException("Driver not supported");
        }
    }
}
