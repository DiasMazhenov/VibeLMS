VibeLMS: локализация и языковые файлы
=====================================

Эта папка содержит локализацию и языковые файлы VibeLMS. Для совместимости с исходным
кодом текстовый домен `lifterlms` сохранён.

Русский перевод `lifterlms-ru_RU.po/.mo` и JSON-файлы JavaScript включены в пакет плагина.
Они загружаются автоматически, когда язык WordPress установлен как русский.

## Перевод VibeLMS

VibeLMS использует стандартную систему переводов WordPress. Основные строки имеют текстовый
домен `lifterlms`, а русский перевод синхронизирован с актуальным пакетом WordPress.org.


## Localization Information Files

The `.php` files contained within this directory contain lists of localization information (such as country, address, and currency formatting data). These files are loaded by LifterLMS core functions to various areas of the LifterLMS plugin.

The data contained within these files is compiled from regularly updated sources and converted into a format used by our internal API. These files are automatically generated during a release step.

Information for these files is derived from the following projects and sources:

+ [Countries States Cities Database](https://github.com/dr5hn/countries-states-cities-database)
+ [Currency Formatter](https://github.com/smirzaei/currency-formatter)
+ [addressfield.json](https://github.com/tableau-mkt/addressfield.json)
+ [LocalePlanet](https://www.localeplanet.com/)

If you locate any incorrect information in any of these files, please let us know by opening [a new issue](https://github.com/gocodebox/lifterlms/issues/new/choose).
