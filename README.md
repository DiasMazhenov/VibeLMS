<h1 align="center">
  <img src=".github/lifterlms-logo.png" alt="VibeLMS logo" width="300">
</h1>

<p align="center"><strong>VibeLMS</strong> is a reusable WordPress learning platform by <a href="https://mazhenov.kz">Mazhenov Design</a>.</p>

<hr />

<div align="center">

[![WordPress Plugin Version][img-wp-plugin]][link-wp-repo]
[![WordPress Plugin Tested WP Version][img-wp-tested]][link-wp-repo]
[![PHP Supported Version][img-php]][link-php]

[![WordPress Plugin Rating][img-wp-rating]][link-wp-reviews]
[![WordPress Plugin Downloads][img-wp-downloads]][link-wp-advanced]
[![WordPress Plugin Active Installs][img-wp-installs]][link-wp-advanced]

[![PHPUnit Tests][img-phpunit-tests]][link-phpunit-tests]
[![PHPCS Coding Standards][img-phpcs-checks]][link-phpcs-checks]
[![Code Climate maintainability][img-cc-maintainability]][link-cc]
[![Code Climate test coverage][img-cc-coverage]][link-cc-coverage]

[![Contributions Welcome][img-contributions-welcome]](.github/CONTRIBUTING.md)
[![Contributors][img-contributors]](#contributors)
[![Slack community][img-slack]][link-slack]

</div>

<hr />

Welcome to the VibeLMS repository. It contains a reusable WordPress LMS core, generic platform capabilities and the diagnostics needed for safe development on a staging WordPress site. Project-specific branding, languages, materials, questions and certificates are configured as content and settings; they are not hard-coded into the plugin.

The repository is currently a development fork; do not use it as a production release until the VibeLMS acceptance checks are complete.

### VibeLMS implementation status

Implemented:

- reusable course, lesson, quiz and certificate foundation;
- opt-in structured PHP diagnostics with sensitive-value redaction;
- `vibelms_student` and `vibelms_observer` roles with restricted capabilities;
- Russian admin interface and VibeLMS branding;
- bundled Advanced Quizzes with question bank and advanced question types;
- Elementor widgets for courses, lessons, tests, test results, certificates, profiles and access groups, with manual course selection;
- standalone test library and constructor under **VibeLMS → Тесты**, using the existing LifterLMS quiz/question models;
- configurable assessment rule, identity form, protected attempt journal and CSV export;
- required RU/KZ language selector in registration with optional automatic assignment to configured access groups;
- optional automatic certificate award after a successful assessment;
- native language-aware materials for slides, videos and documents, with protected authenticated document downloads;
- reusable frontend VibeLMS header, footer, language switcher and Elementor widgets;
- complete VibeLMS transfer archive for settings, courses, users, tests, progress, reports and local media;
- reproducible installable ZIP build through `scripts/build-installable-package.sh`.

Configuration remains project-neutral:

- project branding, languages, slides, videos, documents, questions and certificate templates are supplied through WordPress content, Elementor and ACF Pro;
- internal compatibility identifiers remain unchanged so existing course data and Elementor documents continue to work.


### Documentation and support

- [Changelog](./CHANGELOG.md)
- [План разработки](./docs/)
- вопросы и сообщения об ошибках: <https://github.com/DiasMazhenov/VibeLMS/issues>
- сайт разработчика: <https://mazhenov.kz>


### Installing for development

The GitHub tree includes the runtime Composer dependencies required by WordPress, so it can be deployed directly through Push-to-Deploy. Development dependencies are not committed. When refreshing dependencies, use:

```bash
composer install --no-dev --no-scripts --no-interaction --prefer-dist
VIBELMS_PACKAGE_VERSION=0.0.32 ./scripts/build-installable-package.sh
```

The resulting `dist/vibelms-0.0.31.zip` can be uploaded in **Plugins → Add New → Upload Plugin** on a staging WordPress site. Advanced Quizzes is bundled into VibeLMS, so a separate paid add-on is not required. Its PHP and browser messages are included in the Russian catalog. After activation, create a test under **VibeLMS → Тесты**, attach it to a lesson, and insert it with the Elementor widget **Тест**. Courses, lessons and tests also support **Редактировать в Elementor**; for a course, the Elementor Finder contains **Открыть конструктор курса VibeLMS**, which opens the existing `llms-course-builder` in a separate tab. The widget also has a **Стили VibeLMS** tab with typography, colors, background, responsive spacing, border and shadow controls. Create slides, videos and documents under **VibeLMS → Материалы**, choose the language and order, then use the Elementor widget **Учебные материалы**. The widgets **Шапка VibeLMS** and **Подвал VibeLMS** provide reusable site chrome; the same blocks are available as `[vibelms_header]`, `[vibelms_footer]` and `[vibelms_language_switcher]`. Configure the assessment rule and language groups under **VibeLMS → Настройки → Общие**: assign one access group to Русский and one to Казахский, and new participants will be enrolled automatically after choosing their language in the registration form. Review **Журнал тестирования**, and check **VibeLMS → Статус → Логи** when `VIBELMS_DEBUG` is enabled. In **VibeLMS → Настройки → Общие → Режим интерфейса** choose **Упрощённый** for focused navigation or **Расширенный** to show the full legacy menu. The compact VibeLMS header includes materials for administrators and provides quick access to the main sections; the existing course builder remains unchanged.


The current package is `dist/vibelms-0.0.32.zip`.

### Перенос между сайтами

На исходном сайте откройте **VibeLMS → Перенос данных** и нажмите **Скачать полный экспорт**. На целевом сайте выберите ZIP и нажмите **Проверить ZIP**. После предварительного отчёта выберите режим дубликатов и нажмите **Подтвердить и начать импорт**.

Архив переносит настройки VibeLMS, курсы с уроками и тестами, вопросы, группы доступа, шаблоны сертификатов, LMS-пользователей, зачисления, попытки, журнал VibeLMS и связанные медиафайлы. Пользователи сопоставляются по email, а записи и ссылки получают новые ID на целевом сайте. Импорт добавляет данные и не удаляет существующий контент.

Пароли и токены в архив не попадают. Для нового пользователя создаётся случайный пароль и устанавливается отметка `vibelms_transfer_needs_password_reset`; после импорта администратор должен задать пользователю новый пароль. Перед импортом сделайте резервную копию базы данных и `wp-content/uploads`.

Импорт выполняется пакетами с живым индикатором этапа и автоматически восстанавливается после обновления страницы. После завершения экран показывает понятную статистику и список предупреждений, а подробный JSON-отчёт можно скачать; диагностические события также записываются в журнал VibeLMS.

Подробные ограничения и состав архива описаны в [инструкции по переносу](./docs/vibelms-transfer.md).


### Contributing

[![Contributions Welcome][img-contributions-welcome]](.github/CONTRIBUTING.md)

Interested in contributing to VibeLMS? Read the contributor guidelines [here](.github/CONTRIBUTING.md).


### Contributors

[![Contributors][img-contributors]](#contributors)

Endless thanks to all our incredible contributors!

[//]: contributor-faces
<a href="https://github.com/thomasplevy"><img src="https://avatars.githubusercontent.com/u/1290739?v=4" title="thomasplevy" width="80" height="80"></a>
<a href="https://github.com/eri-trabiccolo"><img src="https://avatars.githubusercontent.com/u/7689242?v=4" title="eri-trabiccolo" width="80" height="80"></a>
<a href="https://github.com/brianhogg"><img src="https://avatars.githubusercontent.com/u/627497?v=4" title="brianhogg" width="80" height="80"></a>
<a href="https://github.com/pondermatic"><img src="https://avatars.githubusercontent.com/u/5377968?v=4" title="pondermatic" width="80" height="80"></a>
<a href="https://github.com/ideadude"><img src="https://avatars.githubusercontent.com/u/33220397?v=4" title="ideadude" width="80" height="80"></a>
<a href="https://github.com/therealmarknelson"><img src="https://avatars.githubusercontent.com/u/5050601?v=4" title="therealmarknelson" width="80" height="80"></a>
<a href="https://github.com/PSmolic"><img src="https://avatars.githubusercontent.com/u/4542049?v=4" title="PSmolic" width="80" height="80"></a>
<a href="https://github.com/actuallyakash"><img src="https://avatars.githubusercontent.com/u/18614782?v=4" title="actuallyakash" width="80" height="80"></a>
<a href="https://github.com/seothemes"><img src="https://avatars.githubusercontent.com/u/24793388?v=4" title="seothemes" width="80" height="80"></a>
<a href="https://github.com/kimcoleman"><img src="https://avatars.githubusercontent.com/u/5312875?v=4" title="kimcoleman" width="80" height="80"></a>
<a href="https://github.com/bmatt468"><img src="https://avatars.githubusercontent.com/u/8673706?v=4" title="bmatt468" width="80" height="80"></a>
<a href="https://github.com/chrisbadgett"><img src="https://avatars.githubusercontent.com/u/12163552?v=4" title="chrisbadgett" width="80" height="80"></a>
<a href="https://github.com/MaximilianoRicoTabo"><img src="https://avatars.githubusercontent.com/u/1678457?v=4" title="MaximilianoRicoTabo" width="80" height="80"></a>
<a href="https://github.com/alimathis"><img src="https://avatars.githubusercontent.com/u/16086976?v=4" title="alimathis" width="80" height="80"></a>
<a href="https://github.com/nrherron92"><img src="https://avatars.githubusercontent.com/u/47434271?v=4" title="nrherron92" width="80" height="80"></a>
<a href="https://github.com/daniel-shuy"><img src="https://avatars.githubusercontent.com/u/17351764?v=4" title="daniel-shuy" width="80" height="80"></a>
<a href="https://github.com/andreasblumberg"><img src="https://avatars.githubusercontent.com/u/1697968?v=4" title="andreasblumberg" width="80" height="80"></a>
<a href="https://github.com/imknight"><img src="https://avatars.githubusercontent.com/u/77604?v=4" title="imknight" width="80" height="80"></a>
<a href="https://github.com/philwp"><img src="https://avatars.githubusercontent.com/u/5949352?v=4" title="philwp" width="80" height="80"></a>
<a href="https://github.com/faisalahammad"><img src="https://avatars.githubusercontent.com/u/13257516?v=4" title="faisalahammad" width="80" height="80"></a>
<a href="https://github.com/alaa-alshamy"><img src="https://avatars.githubusercontent.com/u/2883734?v=4" title="alaa-alshamy" width="80" height="80"></a>
<a href="https://github.com/chetansatasiya"><img src="https://avatars.githubusercontent.com/u/7081284?v=4" title="chetansatasiya" width="80" height="80"></a>
<a href="https://github.com/Mte90"><img src="https://avatars.githubusercontent.com/u/403283?v=4" title="Mte90" width="80" height="80"></a>
<a href="https://github.com/actual-saurabh"><img src="https://avatars.githubusercontent.com/u/1739834?v=4" title="actual-saurabh" width="80" height="80"></a>
<a href="https://github.com/nikolapasic"><img src="https://avatars.githubusercontent.com/u/10199798?v=4" title="nikolapasic" width="80" height="80"></a>
<a href="https://github.com/AndreaBarghigiani"><img src="https://avatars.githubusercontent.com/u/190159?v=4" title="AndreaBarghigiani" width="80" height="80"></a>
<a href="https://github.com/yojance"><img src="https://avatars.githubusercontent.com/u/1916064?v=4" title="yojance" width="80" height="80"></a>
<a href="https://github.com/tpkemme"><img src="https://avatars.githubusercontent.com/u/3424234?v=4" title="tpkemme" width="80" height="80"></a>
<a href="https://github.com/paulgoodchild"><img src="https://avatars.githubusercontent.com/u/10562196?v=4" title="paulgoodchild" width="80" height="80"></a>
<a href="https://github.com/wenchen"><img src="https://avatars.githubusercontent.com/u/959457?v=4" title="wenchen" width="80" height="80"></a>
<a href="https://github.com/mcguffin"><img src="https://avatars.githubusercontent.com/u/402988?v=4" title="mcguffin" width="80" height="80"></a>
<a href="https://github.com/dineshchouhan"><img src="https://avatars.githubusercontent.com/u/15683967?v=4" title="dineshchouhan" width="80" height="80"></a>
<a href="https://github.com/hovpoghosyan"><img src="https://avatars.githubusercontent.com/u/9405480?v=4" title="hovpoghosyan" width="80" height="80"></a>
<a href="https://github.com/tnorthcutt"><img src="https://avatars.githubusercontent.com/u/796639?v=4" title="tnorthcutt" width="80" height="80"></a>
<a href="https://github.com/ThePikJoker"><img src="https://avatars.githubusercontent.com/u/16877156?v=4" title="ThePikJoker" width="80" height="80"></a>
<a href="https://github.com/nicolas-jaussaud"><img src="https://avatars.githubusercontent.com/u/33153717?v=4" title="nicolas-jaussaud" width="80" height="80"></a>
<a href="https://github.com/lifterlms-maurice"><img src="https://avatars.githubusercontent.com/u/272279717?v=4" title="lifterlms-maurice" width="80" height="80"></a>
<a href="https://github.com/mrosati84"><img src="https://avatars.githubusercontent.com/u/855068?v=4" title="mrosati84" width="80" height="80"></a>
<a href="https://github.com/jasonyingling"><img src="https://avatars.githubusercontent.com/u/4986487?v=4" title="jasonyingling" width="80" height="80"></a>
<a href="https://github.com/jasonyingling-hlk"><img src="https://avatars.githubusercontent.com/u/196813470?v=4" title="jasonyingling-hlk" width="80" height="80"></a>
<a href="https://github.com/flintfromthebasement"><img src="https://avatars.githubusercontent.com/u/267404437?v=4" title="flintfromthebasement" width="80" height="80"></a>
<a href="https://github.com/bsetiawan88"><img src="https://avatars.githubusercontent.com/u/5827051?v=4" title="bsetiawan88" width="80" height="80"></a>
<a href="https://github.com/yumashev"><img src="https://avatars.githubusercontent.com/u/37841388?v=4" title="yumashev" width="80" height="80"></a>
<a href="https://github.com/sujaypawar"><img src="https://avatars.githubusercontent.com/u/2222249?v=4" title="sujaypawar" width="80" height="80"></a>
<a href="https://github.com/AlexVCS"><img src="https://avatars.githubusercontent.com/u/49458917?v=4" title="AlexVCS" width="80" height="80"></a>
<a href="https://github.com/dotance"><img src="https://avatars.githubusercontent.com/u/38263904?v=4" title="dotance" width="80" height="80"></a>
<a href="https://github.com/edent"><img src="https://avatars.githubusercontent.com/u/837136?v=4" title="edent" width="80" height="80"></a>
<a href="https://github.com/sekanderb"><img src="https://avatars.githubusercontent.com/u/3262638?v=4" title="sekanderb" width="80" height="80"></a>
<a href="https://github.com/sapayth"><img src="https://avatars.githubusercontent.com/u/15567340?v=4" title="sapayth" width="80" height="80"></a>
<a href="https://github.com/reedhewitt"><img src="https://avatars.githubusercontent.com/u/957141?v=4" title="reedhewitt" width="80" height="80"></a>
<a href="https://github.com/Nikschavan"><img src="https://avatars.githubusercontent.com/u/2931091?v=4" title="Nikschavan" width="80" height="80"></a>
<a href="https://github.com/nhandl3"><img src="https://avatars.githubusercontent.com/u/1247539?v=4" title="nhandl3" width="80" height="80"></a>
<a href="https://github.com/matthalliday"><img src="https://avatars.githubusercontent.com/u/249506?v=4" title="matthalliday" width="80" height="80"></a>
<a href="https://github.com/kamalahmed"><img src="https://avatars.githubusercontent.com/u/6803549?v=4" title="kamalahmed" width="80" height="80"></a>
<a href="https://github.com/moorscode"><img src="https://avatars.githubusercontent.com/u/2005352?v=4" title="moorscode" width="80" height="80"></a>
<a href="https://github.com/iTechsTR"><img src="https://avatars.githubusercontent.com/u/33372714?v=4" title="iTechsTR" width="80" height="80"></a>
<a href="https://github.com/unt01d"><img src="https://avatars.githubusercontent.com/u/11303423?v=4" title="unt01d" width="80" height="80"></a>
<a href="https://github.com/cadengrey"><img src="https://avatars.githubusercontent.com/u/30481164?v=4" title="cadengrey" width="80" height="80"></a>
<a href="https://github.com/andrewvaughan"><img src="https://avatars.githubusercontent.com/u/1119590?v=4" title="andrewvaughan" width="80" height="80"></a>
<a href="https://github.com/andrewlimaza"><img src="https://avatars.githubusercontent.com/u/12629136?v=4" title="andrewlimaza" width="80" height="80"></a>
<a href="https://github.com/alexjpanagis"><img src="https://avatars.githubusercontent.com/u/32090467?v=4" title="alexjpanagis" width="80" height="80"></a>

[//]: contributor-faces


### Partners and Sponsors

[<img src="https://raw.githubusercontent.com/gocodebox/lifterlms/trunk/.github/sponsors/browserstack-logo.png" height="60" alt="BrowserStack">](https://www.browserstack.com/)

[BrowserStack](https://www.browserstack.com/) helps us ensure LifterLMS looks great and works on every imaginable browser and device.

<!-- References: Links -->
[link-cc]: https://codeclimate.com/github/gocodebox/lifterlms "LifterLMS on Code Climate"
[link-cc-coverage]: https://codeclimate.com/github/gocodebox/lifterlms/coverage "Code coverage reports on Code Climate"
[link-experts]: https://lifterlms.com/docs/do-you-have-any-recommended-developers-who-can-modifycustomize-lifterlms/ "Hire a LifterLMS Expert"
[link-php]: https://www.php.net/supported-versions "PHP Support Versions"
[link-phpunit-tests]: https://github.com/gocodebox/lifterlms/actions/workflows/test-phpunit.yml "PHPUnit Tests Status"
[link-phpcs-checks]: https://github.com/gocodebox/lifterlms/actions/workflows/coding-standards.yml "PHPCS Coding Standards Checks"
[link-slack]: https://lifterlms.com/slack "Chat with the community on Slack"
[link-support]: https://lifterlms.com/my-account/my-tickets "LifterLMS customer support"
[link-support-forums]: https://wordpress.org/support/plugin/lifterlms "LifterLMS user support forums"
[link-wp-advanced]:https://wordpress.org/plugins/lifterlms/advanced/ "Advanced plugin details on the WordPress plugin repository"
[link-wp-repo]:https://wordpress.org/plugins/lifterlms/ "LifterLMS on the WordPress plugin repository"
[link-wp-reviews]:https://wordpress.org/support/plugin/lifterlms/reviews/ "Leave a review on the WordPress plugin repository"

[img-cc-coverage]:https://img.shields.io/codeclimate/coverage/gocodebox/lifterlms?style=for-the-badge&logo=code-climate
[img-cc-maintainability]:https://img.shields.io/codeclimate/maintainability/gocodebox/lifterlms?logo=code-climate&style=for-the-badge
[img-contributors]: https://img.shields.io/github/contributors/gocodebox/lifterlms?color=blue&style=for-the-badge&logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGlkPSJzdmcyIiB3aWR0aD0iNjQ1IiBoZWlnaHQ9IjU4NSIgdmVyc2lvbj0iMS4wIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPiA8ZyBpZD0ibGF5ZXIxIj4gIDxwYXRoIGlkPSJwYXRoMjQxNyIgZD0ibTI5Ny4zIDU1MC44N2MtMTMuNzc1LTE1LjQzNi00OC4xNzEtNDUuNTMtNzYuNDM1LTY2Ljg3NC04My43NDQtNjMuMjQyLTk1LjE0Mi03Mi4zOTQtMTI5LjE0LTEwMy43LTYyLjY4NS01Ny43Mi04OS4zMDYtMTE1LjcxLTg5LjIxNC0xOTQuMzQgMC4wNDQ1MTItMzguMzg0IDIuNjYwOC01My4xNzIgMTMuNDEtNzUuNzk3IDE4LjIzNy0zOC4zODYgNDUuMS02Ni45MDkgNzkuNDQ1LTg0LjM1NSAyNC4zMjUtMTIuMzU2IDM2LjMyMy0xNy44NDUgNzYuOTQ0LTE4LjA3IDQyLjQ5My0wLjIzNDgzIDUxLjQzOSA0LjcxOTcgNzYuNDM1IDE4LjQ1MiAzMC40MjUgMTYuNzE0IDYxLjc0IDUyLjQzNiA2OC4yMTMgNzcuODExbDMuOTk4MSAxNS42NzIgOS44NTk2LTIxLjU4NWM1NS43MTYtMTIxLjk3IDIzMy42LTEyMC4xNSAyOTUuNSAzLjAzMTYgMTkuNjM4IDM5LjA3NiAyMS43OTQgMTIyLjUxIDQuMzgwMSAxNjkuNTEtMjIuNzE1IDYxLjMwOS02NS4zOCAxMDguMDUtMTY0LjAxIDE3OS42OC02NC42ODEgNDYuOTc0LTEzNy44OCAxMTguMDUtMTQyLjk4IDEyOC4wMy01LjkxNTUgMTEuNTg4LTAuMjgyMTYgMS44MTU5LTI2LjQwOC0yNy40NjF6IiBmaWxsPSIjZGQ1MDRmIi8%2BIDwvZz48L3N2Zz4%3D
[img-contributions-welcome]: https://img.shields.io/badge/contributions-welcome-blue.svg?style=for-the-badge&logo=data:image/svg%2bxml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPHN2ZyB3aWR0aD0iMTc5MiIgaGVpZ2h0PSIxNzkyIiB2aWV3Qm94PSIwIDAgMTc5MiAxNzkyIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxwYXRoIGQ9Ik02NzIgMTQ3MnEwLTQwLTI4LTY4dC02OC0yOC02OCAyOC0yOCA2OCAyOCA2OCA2OCAyOCA2OC0yOCAyOC02OHptMC0xMTUycTAtNDAtMjgtNjh0LTY4LTI4LTY4IDI4LTI4IDY4IDI4IDY4IDY4IDI4IDY4LTI4IDI4LTY4em02NDAgMTI4cTAtNDAtMjgtNjh0LTY4LTI4LTY4IDI4LTI4IDY4IDI4IDY4IDY4IDI4IDY4LTI4IDI4LTY4em05NiAwcTAgNTItMjYgOTYuNXQtNzAgNjkuNXEtMiAyODctMjI2IDQxNC02NyAzOC0yMDMgODEtMTI4IDQwLTE2OS41IDcxdC00MS41IDEwMHYyNnE0NCAyNSA3MCA2OS41dDI2IDk2LjVxMCA4MC01NiAxMzZ0LTEzNiA1Ni0xMzYtNTYtNTYtMTM2cTAtNTIgMjYtOTYuNXQ3MC02OS41di04MjBxLTQ0LTI1LTcwLTY5LjV0LTI2LTk2LjVxMC04MCA1Ni0xMzZ0MTM2LTU2IDEzNiA1NiA1NiAxMzZxMCA1Mi0yNiA5Ni41dC03MCA2OS41djQ5N3E1NC0yNiAxNTQtNTcgNTUtMTcgODcuNS0yOS41dDcwLjUtMzEgNTktMzkuNSA0MC41LTUxIDI4LTY5LjUgOC41LTkxLjVxLTQ0LTI1LTcwLTY5LjV0LTI2LTk2LjVxMC04MCA1Ni0xMzZ0MTM2LTU2IDEzNiA1NiA1NiAxMzZ6IiBmaWxsPSIjZmZmIi8+PC9zdmc+
[img-php]: https://img.shields.io/badge/PHP-7.2%2B-brightgreen?style=for-the-badge&logoColor=white&logo=php
[img-phpunit-tests]: https://img.shields.io/github/workflow/status/gocodebox/lifterlms/Test%20PHPUnit?label=PHPUnit&logo=github&style=for-the-badge
[img-phpcs-checks]: https://img.shields.io/github/workflow/status/gocodebox/lifterlms/Coding%20Standards?label=PHPCS&logo=github&style=for-the-badge
[img-slack]: https://img.shields.io/badge/chat-on%20slack-blueviolet?style=for-the-badge&logo=slack
[img-wp-downloads]: https://img.shields.io/wordpress/plugin/dt/lifterlms.svg?style=for-the-badge&logo=wordpress
[img-wp-installs]: https://img.shields.io/wordpress/plugin/installs/lifterlms.svg?style=for-the-badge&logo=wordpress
[img-wp-plugin]:https://img.shields.io/wordpress/plugin/v/lifterlms.svg?style=for-the-badge&logo=wordpress
[img-wp-rating]:https://img.shields.io/wordpress/plugin/r/lifterlms.svg?style=for-the-badge&logo=wordpress
[img-wp-tested]:https://img.shields.io/wordpress/v/lifterlms.svg?style=for-the-badge&logo=wordpress
