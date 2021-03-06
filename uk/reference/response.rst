Повернення Відповідей
===================

Частина HTTP є повернення відповіді клієнтам. :doc:`Phalcon\\Http\\Response <../api/Phalcon_Http_Response>` є компонентом Phalcon призначений для виконання цього завдання. HTTP-відповіді, як правило, складається із заголовків і тіла. Нижче наведено приклад базового використання:

.. code-block:: php

    <?php

    use Phalcon\Http\Response;

    // Отримання примірника відповіді
    $response = new Response();

    // Встановлення код стану
    $response->setStatusCode(404, "Не знайдено");

    // Встановлення змісту відповіді
    $response->setContent("Вибачте, сторінки не існує");

    // Надсилання відповіді клієнту
    $response->send();

Якщо ви використовуєте повний стек MVC немає необхідності створювати відповідей вручну. Проте, якщо вам потрібно повернути відповідь безпосередньо від дії контролера використовуйте цей приклад:

.. code-block:: php

    <?php

    use Phalcon\Http\Response;
    use Phalcon\Mvc\Controller;

    class FeedController extends Controller
    {
        public function getAction()
        {
            // Отримання примірника відповіді
            $response = new Response();

            $feed = // ... Load here the feed

            // Встановлення змісту відповіді
            $response->setContent(
                $feed->asString()
            );

            // Надсилання відповіді
            return $response;
        }
    }

Робота з заголовками
--------------------
Заголовки є важливою частиною HTTP відповіді. Він містить корисну інформацію про стан відповіді, таку як статус HTTP,тип реакції і багато іншого.

Ви можете встановити заголовки в такий спосіб:

.. code-block:: php

    <?php

    // Установка заголовка по імені
    $response->setHeader("Content-Type", "application/pdf");
    $response->setHeader("Content-Disposition", 'attachment; filename="downloaded.pdf"');

    // Установка вихідного заголовка
    $response->setRawHeader("HTTP/1.1 200 OK");

A :doc:`Phalcon\\Http\\Response\\Headers <../api/Phalcon_Http_Response_Headers>` bag internally manages headers. This class
retrieves the headers before sending it to client:
Всі :doc:`Phalcon\\Http\\Response\\Headers <../api/Phalcon_Http_Response_Headers>` внутрішньо управляє заголовками. Цей клас витягує заголовки перед відправкою клієнту:

.. code-block:: php

    <?php

    // Отримання всіх заголовків
    $headers = $response->getHeaders();

    // Отримання заголовка по імені
    $contentType = $headers->get("Content-Type");

Making Redirections
-------------------
З :doc:`Phalcon\\Http\\Response <../api/Phalcon_Http_Response>` ви можете також виконати HTTP переадресацію:

.. code-block:: php

    <?php

    // Перенаправлення на URI за замовчуванням
    $response->redirect();

    // Перенаправлення на базовий локальний URI
    $response->redirect("posts/index");

    // Перенаправлення на зовнішній URL
    $response->redirect("http://en.wikipedia.org", true);

    // Перенаправлення із зазначенням коду стану HTTP
    $response->redirect("http://www.example.com/new-location", true, 301);

Всі внутрішні ідентифікатори URI генеруються за допомогою 'url' сервісу :doc:`Phalcon\\Mvc\\Url <url>`). Цей приклад показує, як можна перенаправити за допомогою маршрут, який Ви визначили в вашому додатку:

.. code-block:: php

    <?php

    // Перенаправлення на основі імені маршруту
    return $response->redirect(
        [
            "for"        => "index-lang",
            "lang"       => "jp",
            "controller" => "index",
        ]
    );

Зверніть увагу, що перенаправлення не відключає компонент перегляду, так що якщо є вид, пов'язаний з поточним дією він буде виконаний в будь-якому випадку. Можна відключити вид з контролера, виконавши :code:`$this->view->disable()`;

HTTP-кешування
----------
Один з найпростіших способів поліпшити продуктивність в додатках і зменшити трафік це використовувати HTTP-кешування. Більшість сучасних браузерів підтримують кешування HTTP і це є однією з причин, чому багато веб-сайти в даний час швидкі.

HTTP-кеш може бути змінений в наступних значеннях заголовків, які були надіслані додатком при обслуговуванні сторінки в перший раз:

* *Expires:* За допомогою цього заголовка програми можна встановити дату в майбутньому або минулому повідомляючи браузеру, коли сторінка повинна закінчитися.
* *Cache-Control:* Цей заголовок дозволяє визначити, скільки часу сторінка повинна вважатися свіжим в браузері.
* *Last-Modified:* Цей заголовок повідомляє браузеру, який був останній раз, коли сайт був оновлений уникаючи перезавантаження сторінки
* *ETag:* ETag являє собою унікальний ідентифікатор, який повинен бути створений, включаючи зміну тимчасової мітки поточної сторінки

Налаштування Expiration Time
^^^^^^^^^^^^^^^^^^^^^^^^^^
Термін придатності є одним з найпростіших і найефективніших способів для кешування сторінки на клієнті(браузер).
Починаючи з поточної дати ми додаємо кількість часу скільки сторінка буде зберігатися в кеші браузера. До цієї дати поки не закінчиться ніякого вмісту не буде запропоновано з сервера:

.. code-block:: php

    <?php

    $expiryDate = new DateTime();
    $expiryDate->modify("+2 months");

    $response->setExpires($expiryDate);

Компонент Response автоматично показує дату в часовому поясі, як і очікувалося, в Завершує заголовок.

Якщо встановити це значення на дату в минулому, браузер завжди буде оновлювати запитану сторінку:

.. code-block:: php

    <?php

    $expiryDate = new DateTime();
    $expiryDate->modify("-10 minutes");

    $response->setExpires($expiryDate);

Браузери покладаються на годинник клієнта, щоб оцінити, якщо ця дата пройшла чи ні. Годинники клієнт може бути змінений, щоб зробити сторінки закінченою, і це може бути обмеження цього механізму кешування.

Cache-Control
^^^^^^^^^^^^^
Цей заголовок забезпечує більш безпечний спосіб для кешування сторінок. Ми просто повинні вказати час в секундах, які повідомляють браузеру, як довго він повинен тримати сторінку в кеші:

.. code-block:: php

    <?php

    // Починаючи з цього моменту, кешувати сторінку на один день
    $response->setHeader("Cache-Control", "max-age=86400");

Протилежний ефект (уникати кешування сторінок) досягається наступним чином:

.. code-block:: php

    <?php

    // Ніколи не кешувати обслуговуючу сторінку
    $response->setHeader("Cache-Control", "private, max-age=0, must-revalidate");

E-Tag
^^^^^
"entity-tag" чи "E-tag" це унікальний ідентифікатор, який допомагає браузеру зрозуміти, якщо сторінка змінилася чи ні між двома запитами. Ідентифікатор повинен бути розрахований з урахуванням, що змінитися повинен, якщо раніше обслуговуючий зміст змінився:

.. code-block:: php

    <?php

    // Обчислення E-Tag грунтуюється на часі модифікації останніх новин
    $mostRecentDate = News::maximum(
        [
            "column" => "created_at"
        ]
    );

    $eTag = md5($mostRecentDate);

    // Надіслати E-Tag заголовока
    $response->setHeader("E-Tag", $eTag);
