<?php

declare(strict_types=1);

/*
 * SEO e site e omumi (P5-05). Matn ha UI-and (Farsi).
 */
return [
    'site_name' => 'تابلو قیمت',

    /*
     * Crawl-pazir? Faghat production. Har mohit e digar `robots.txt` e
     * `Disallow: /` migire (staging e index-shode = mohtava ye tekrari).
     */
    'indexable' => (bool) env('SEO_INDEXABLE', env('APP_ENV') === 'production'),

    // Masir haye khususi/fanni ke crawl nemishan. (`/tv` amdan inja NIST:
    // `noindex` e meta-ash bayad dide beshe — Disallow mane' e didanesh mishe.)
    'disallow' => ['/dashboard', '/login', '/api/', '/*.json$'],

    'home' => [
        'title' => 'قیمت لحظه‌ای طلا، سکه و نقره',
        'description' => 'مقایسه‌ی لحظه‌ای قیمت طلا، سکه، نقره و دلار بین چند منبع معتبر — با کمترین، بیشترین و میانگین هر نماد.',
    ],

    /*
     * Safhe haye sitemap. `route` = esm e route (+ kelid e `seo_meta` ba
     * type e `page`). `live` = lastmod az akharin gheymat.
     */
    'pages' => [
        ['route' => 'home', 'changefreq' => 'always', 'priority' => '1.0', 'live' => true],
    ],

    /*
     * FAQ e safhe ye asli — HAM ru safhe neshun dade mishe HAM tu JSON-LD
     * (`FAQPage`). Yek manba'، ta hichvaght az ham joda nashan.
     */
    'faq' => [
        [
            'question' => 'قیمت‌ها از کجا می‌آیند؟',
            'answer' => 'قیمت‌ها به‌صورت خودکار از چند منبع معتبر بازار طلا، سکه و دلار جمع‌آوری و کنار هم نمایش داده می‌شوند تا بتوانید آن‌ها را مقایسه کنید.',
        ],
        [
            'question' => 'قیمت‌ها هر چند وقت به‌روز می‌شوند؟',
            'answer' => 'جدول هر 15 ثانیه به‌روز می‌شود. قیمتی که از منبع دیر رسیده باشد کم‌رنگ‌تر نمایش داده می‌شود.',
        ],
        [
            'question' => 'کمترین، بیشترین و میانگین یعنی چه؟',
            'answer' => 'برای هر نماد، کمترین و بیشترین قیمت اعلام‌شده بین منابع و میانگین آن‌ها محاسبه می‌شود تا اختلاف بازار را یک‌جا ببینید.',
        ],
        [
            'question' => 'قیمت‌ها به چه واحدی هستند؟',
            'answer' => 'قیمت‌های بازار داخلی به تومان و نمادهای جهانی (مثل انس طلا) به دلار نمایش داده می‌شوند.',
        ],
        [
            'question' => 'آیا می‌توانم این قیمت‌ها را در سایت یا اپلیکیشن خودم استفاده کنم؟',
            'answer' => 'بله؛ قیمت‌ها از طریق API با کلید اختصاصی در دسترس است. برای دریافت دسترسی وارد حساب کاربری شوید.',
        ],
    ],
];
