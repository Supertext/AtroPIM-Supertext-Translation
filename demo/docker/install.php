<?php

/**
 * Demo only (runs on every start, as www-data): points AtroPIM at its database and installs it
 * on the first start, with DEMO_ADMIN_EMAIL as the administrator.
 *
 *   MYSQL_URL        mysql://user:password@host:3306/anything (the database name is ignored)
 *   ATROPIM_DB_NAME  the demo's own database on that server (default atropim); AtroCore's
 *                    installer empties it, so it must never be a shared database
 *   DEMO_SITE_URL    public URL (default https://$RAILWAY_PUBLIC_DOMAIN, else http://localhost:$PORT)
 */

declare(strict_types=1);

chdir('/var/www/atro');
set_include_path('/var/www/atro');
require 'vendor/autoload.php';
require __DIR__ . '/common.php';

$url = parse_url((string) getenv('MYSQL_URL'));

if (empty($url['host'])) {
    demo_log('MYSQL_URL is not set (mysql://user:password@host:3306/db).');
    exit(1);
}

$dbName = (string) (getenv('ATROPIM_DB_NAME') ?: 'atropim');

if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName) || in_array(strtolower($dbName), ['railway', 'mysql', 'sys', 'information_schema', 'performance_schema'], true)) {
    demo_log("ATROPIM_DB_NAME \"$dbName\" can't be used: AtroCore's installer empties the database, so it needs one of its own.");
    exit(1);
}

$db = [
    'driver'   => 'pdo_mysql',
    'host'     => $url['host'],
    'port'     => (string) ($url['port'] ?? 3306),
    'charset'  => 'utf8mb4',
    'dbname'   => $dbName,
    'user'     => urldecode((string) ($url['user'] ?? 'root')),
    'password' => urldecode((string) ($url['pass'] ?? '')),
];

// Wait for MySQL and create the demo's database.
for ($attempt = 1; ; $attempt++) {
    try {
        $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        break;
    } catch (PDOException $e) {
        if ($attempt >= 60) {
            demo_log('MySQL is not reachable: ' . $e->getMessage());
            exit(1);
        }
        sleep(2);
    }
}

$tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = " . $pdo->quote($dbName))->fetchAll(PDO::FETCH_COLUMN);

$app    = new \Atro\Core\Application();
$c      = $app->getContainer();
$config = $c->get('config');

$siteUrl = (string) (getenv('DEMO_SITE_URL') ?: (getenv('RAILWAY_PUBLIC_DOMAIN') ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN') : 'http://localhost:' . (getenv('PORT') ?: '8080')));
$config->set('database', $db);
$config->set('siteUrl', rtrim($siteUrl, '/'));
$config->save();

if ($config->get('isInstalled')) {
    exit(0);
}

if ($tables !== [] && !in_array('user', array_map('strtolower', $tables), true)) {
    demo_log("The database \"$dbName\" has tables that are not AtroPIM's; not installing into it.");
    exit(1);
}

if ($tables !== []) {
    demo_log("The database \"$dbName\" holds an earlier installation whose data directory is gone; installing again (the database is emptied).");
}

[$username, $password] = demo_account('DEMO_ADMIN', DEMO_DEFAULT_PASSWORD_PATTERN);

if ($username === null) {
    // The installer needs an administrator. This one has a random password nobody knows; set
    // DEMO_ADMIN_* and restart to get a usable account.
    $username = 'demo-installer';
    $password = 'Aa1!' . bin2hex(random_bytes(16));
}

demo_log('Installing AtroPIM (first start)');
$result = $c->get('serviceFactory')->create('Installer')->createAdmin(['username' => $username, 'password' => $password, 'confirmPassword' => $password]);

if (empty($result['status'])) {
    demo_log('Installation failed: ' . ($result['message'] ?? 'unknown error'));
    exit(1);
}

if ($username !== 'demo-installer') {
    demo_log('Created the administrator from DEMO_ADMIN_EMAIL');
}
