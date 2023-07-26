
<?php
if (PHP_SAPI == 'cli-server') {

    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];

    // check the file types, only serve standard files
    if (preg_match('/\.(?:png|js|jpg|jpeg|gif|css)$/', $file)) {
        // does the file exist? If so, return it
        if (is_file($file))
            return false;

        // file does not exist. return a 404
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        printf('"%s" does not exist', $_SERVER['REQUEST_URI']);
        return false;
    }
}
/* go on with normal index.php operations */
?>
