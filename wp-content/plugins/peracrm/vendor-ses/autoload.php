<?php
/** Private, lazily loaded signer/message subset; never register public AWS/PSR names. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'PeraCRM\\SESDeps\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) { return; }
    $relative = substr($class, strlen($prefix));
    if (preg_match('/\A[A-Za-z0-9_\\\\]+\z/', $relative) !== 1) { return; }
    $file = __DIR__.'/src/'.str_replace('\\', '/', $relative).'.php';
    if (is_file($file)) { require $file; }
});
