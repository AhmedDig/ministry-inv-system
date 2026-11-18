<?php
require_once __DIR__ . '/../src/utils/PhoneNormalizer.php';
use Ministry\Utils\PhoneNormalizer;

$cases = [
    '0599123456' => '0599123456',
    '+970599123456' => '0599123456',
    '00970599123456' => '0599123456',
    '970599123456' => '0599123456',
    '599123456' => '0599123456',
    '0599 123 456' => '0599123456',
    '0599-123-456' => '0599123456',
    '12345' => null,
    'abcdef' => null,
    '025555555' => null, // landline, not mobile
    '' => null,
];

$fail = 0;
foreach ($cases as $input => $expected) {
    $got = PhoneNormalizer::normalize($input);
    $ok = $got === $expected;
    if (!$ok)
        $fail++;
    printf(
        "%s  in=%-20s expected=%-12s got=%s\n",
        $ok ? '✅' : '❌',
        var_export($input, true),
        var_export($expected, true),
        var_export($got, true)
    );
}

echo $fail === 0 ? "\n✅ All tests passed\n" : "\n❌ {$fail} failures\n";
exit($fail === 0 ? 0 : 1);
