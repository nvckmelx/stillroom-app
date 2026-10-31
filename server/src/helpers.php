<?php
declare(strict_types=1);

/** Escape for HTML text and attributes. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** URL-safe slug from any title (keeps Latin letters/digits; transliterates common Cyrillic). */
function slugify(string $s): string
{
    static $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya','ā'=>'a','č'=>'c','ē'=>'e','ģ'=>'g','ī'=>'i','ķ'=>'k','ļ'=>'l','ņ'=>'n','š'=>'s','ū'=>'u','ž'=>'z'];
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, $map);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) {
            $s = $t;
        }
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return substr($s, 0, 80) ?: 'item';
}

function str_limit(string $s, int $max): string
{
    return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
}
