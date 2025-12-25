# Changelog

Энэ төслийн бүх чухал өөрчлөлтүүд энэ файлд тэмдэглэгдэнэ.

Формат нь [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)-д суурилсан,
мөн энэ төсөл [Semantic Versioning](https://semver.org/spec/v2.0.0.html)-д нийцдэг.

**🇬🇧 [English Version](CHANGELOG.EN.md) | 🇲🇳 Монгол Хувилбар**

---

## [9.0.0] - 2025-12-25

### 🚨 Breaking Changes

- **`getRowByCode()` method устгагдсан** `LocalizedModel`-аас
  - Энэ method нь `getRowsByCode()`-тэй давхцаж байсан тул устгагдсан
  - **Migration:** Оронд нь `getRowsByCode($code, $condition)` ашиглана
  - Жишээ: `getRowByCode($id, 'en')` → `getRowsByCode('en', ['WHERE' => 'p.id=:id', 'PARAM' => [':id' => $id]])`

### ✨ Нэмэгдсэн

- **Англи хэл дээрх Баримт Бичиг**
  - `README.EN.md` нэмэгдсэн - README-ийн бүрэн Англи хувилбар
  - `API.EN.md` нэмэгдсэн - Бүрэн Англи API баримт бичиг
  - `REVIEW.EN.md` нэмэгдсэн - Бүрэн Англи код review баримт бичиг
  - Бүх баримт бичгийн файлууд одоо хэл солих линк агуулна

### 🔧 Өөрчлөгдсөн

- **Баримт Бичгийн Бүтэц**
  - Бүх `.md` файлууд одоо эхэнд хэл солих линк агуулна
  - Баримт бичгийн зохион байгуулалт, хүртээмж сайжруулсан

### 📝 Баримт Бичиг

- Устгагдсан `getRowByCode()`-ийн оронд бүх жишээнүүд `getRowsByCode()` ашиглахаар шинэчлэгдсэн
- Код суурь даяар PHPdoc тайлбарууд сайжруулсан
- README файлууд дахь код жишээнүүд сайжруулсан

---

## [8.1.0] - Өмнөх хувилбар

### Онцлогууд

- MySQL, PostgreSQL, SQLite бүрэн дэмжлэг
- Энгийн хүснэгтүүдэд зориулсан `Model` класс
- Олон хэлтэй контентэд зориулсан `LocalizedModel` класс
- Хүснэгт автоматаар үүсгэх
- Бүрэн CRUD үйлдлүүд
- Unit болон Integration тестүүд
- GitHub Actions CI/CD pipeline

---

[9.0.0]: https://github.com/codesaur-php/DataObject/compare/v8.1.0...v9.0.0
[8.1.0]: https://github.com/codesaur-php/DataObject/releases/tag/v8.1.0
