# codesaur/dataobject

[![CI](https://github.com/codesaur-php/DataObject/actions/workflows/ci.yml/badge.svg)](https://github.com/codesaur-php/DataObject/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
![License](https://img.shields.io/badge/License-MIT-green.svg)

## Агуулга / Table of Contents

1. [Монгол](#1-монгол-тайлбар) | 2. [English](#2-english-description) | 3. [Getting Started](#3-getting-started)

---

## 1. Монгол тайлбар

PDO суурьтай өгөгдлийн модель ба хүснэгтүүдийг удирдагч компонент. MySQL / PostgreSQL / SQLite дээр адилхан кодоор ажилладаг.

`codesaur/dataobject` нь **codesaur ecosystem**-ийн нэг хэсэг бөгөөд хөнгөн жинтэй,
фрэймворкоос үл хамааран standalone байдлаар ашиглаж болох PHP өгөгдлийн давхаргын компонент юм.

Багц нь дараах үндсэн class-уудаас бүрдэнэ:

- **Model** - нэг хүснэгтэд зориулсан загварын суурь класс
- **LocalizedModel** - олон хэл дээрх контент хадгалах зориулалттай загварын суурь класс
- **Column** - хүснэгтийн баганын бүтцийг тодорхойлох класс
- **PDOTrait** - PDO үйлдлүүдийг төвлөрүүлсэн trait
- **TableTrait** - хүснэгттэй ажиллах үндсэн боломжуудыг агуулсан trait

### Дэлгэрэнгүй мэдээлэл

- [Бүрэн танилцуулга](docs/mn/README.md) - Суурилуулалт, хэрэглээ, жишээнүүд
- [API тайлбар](docs/mn/api.md) - Бүх метод, exception-үүдийн тайлбар
- [Шалгалтын тайлан](docs/mn/review.md) - Код шалгалтын тайлан

---

## 2. English description

PDO-based data model and table management component. Works with the same code on MySQL / PostgreSQL / SQLite.

`codesaur/dataobject` is part of the **codesaur ecosystem** and is a lightweight PHP data layer component that can be used standalone, independent of any framework.

The package consists of the following core classes:

- **Model** - base class for models targeting a single table
- **LocalizedModel** - base class for models storing content in multiple languages
- **Column** - class for defining table column structure
- **PDOTrait** - trait centralizing PDO operations
- **TableTrait** - trait containing basic capabilities for working with tables

### Documentation

- [Full Documentation](docs/en/README.md) - Installation, usage, examples
- [API Reference](docs/en/api.md) - Complete API documentation
- [Review](docs/en/review.md) - Code review report

---

## 3. Getting Started

### Requirements

- PHP **8.2.1+** with `ext-pdo` extension
- Composer
- MySQL or PostgreSQL or SQLite

### Installation

Composer ашиглан суулгана / Install via Composer:

```bash
composer require codesaur/dataobject
```

### Quick Example

```php
use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class UserModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('username', 'varchar', 64))->unique(),
            new Column('password', 'varchar', 255),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
        ]);

        $this->setTable('users');
    }

    protected function __initial()
    {
        // Хүснэгт анх удаа үүсгэгдэх үед ганц удаа ажиллана
    }
}

// Хэрэглэх / Usage
$pdo = new \PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
$userModel = new UserModel($pdo);

// Нэмэх / Insert
$user = $userModel->insert([
    'username' => 'john',
    'password' => password_hash('secret', PASSWORD_DEFAULT),
    'created_at' => date('Y-m-d H:i:s'),
]);

// Унших / Read
$user = $userModel->getRowWhere(['username' => 'john']);
```

### Running Tests

Тест ажиллуулах / Run tests:

```bash
# Бүх тестүүдийг ажиллуулах / Run all tests
composer test

# Coverage-тэй тест ажиллуулах / Run tests with coverage
composer test-coverage
```

---

## Changelog

- [CHANGELOG.md](CHANGELOG.md) - Full version history

## Contributing & Security

- [Contributing Guide](.github/CONTRIBUTING.md)
- [Security Policy](.github/SECURITY.md)

## License

This project is licensed under the MIT License.

## Author

**Narankhuu**  
codesaur@gmail.com  
https://github.com/codesaur  

**codesaur ecosystem:** https://codesaur.net
