<?php

declare(strict_types=1);

/*
 * ⚠️ Faghat yek kelid override mishe: component e GENERIC e `<x-icon>` e
 * package khamush mishe، chon ba `resources/views/components/icon.blade.php`
 * e kit (subset e inline ba stroke e yekdast، az noskhe ye ghabli) tasadom
 * e esm dasht — package bar anonymous component moghaddam bud va
 * «Svg by name "coins" not found» midad.
 *
 * Icon haye Tabler hamchenan ba `<x-tabler-*>` kar mikonan.
 */
return [
    'components' => [
        'disabled' => true,
    ],
];
