<?php

/**
 * Bitrix Tree
 *
 * @author <masterklavi@gmail.com>
 * @version 0.1
 */


print 'Bitrix Tree'.PHP_EOL;


print 'setup' . PHP_EOL;

$config = file_exists(__DIR__ . '/config.php') ? include(__DIR__ . '/config.php') : include(__DIR__ . '/config.php.dist');

$dest   = $argc === 2 ? $argv[1] : __FILE__ . '.out.html';
$host   = $config['mysql']['host'] ?: (readline('> enter mysql host [127.0.0.1]: ') ?: '127.0.0.1');
$port   = $config['mysql']['port'] ?: (readline('> enter mysql port [3306]: ') ?: '3306');
$dbname = $config['mysql']['name'] ?: readline('> enter mysql database name: ');
$dbuser = $config['mysql']['username'] ?: readline('> enter mysql username: ');
$dbpass = $config['mysql']['password'] ?: readline('> enter mysql password: ');

if (readline('connect via ssh (y/n)? ') === 'y') {
    print 'openning tunnel'.PHP_EOL;
    $userhost = $config['userhost'] ?: readline('> enter userhost (apache@example.com): ');
    exec('ssh -f -L ' . escapeshellarg('33007:' . $host . ':' . $port) . ' ' . escapeshellarg($userhost) . ' sleep 10 > /dev/null');
    $port = '33007';
}

print 'connecting to mysql'.PHP_EOL;
$db = new mysqli($host, $dbuser, $dbpass, $dbname, $port);

print 'select all tables'.PHP_EOL;
$tables = [];
$result = $db->query('SHOW TABLES');
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}
$result->close();

print 'select iblocks'.PHP_EOL;
$iblocks = [];
$result = $db->query('SELECT ID, CODE, NAME FROM b_iblock ORDER BY SORT');
while ($row = $result->fetch_assoc()) {
    $iblocks[$row['ID']] = $row;
}
$result->close();

print 'select properties'.PHP_EOL;
$properties = [];
$result = $db->query('SELECT ID, IBLOCK_ID, NAME, CODE FROM b_iblock_property');
while ($row = $result->fetch_assoc()) {
    $properties[$row['ID']] = $row;
}
$result->close();

print 'select propery tables'.PHP_EOL;
foreach ($iblocks as &$iblock) {
    $iblock['properties'] = null;

    $table = "b_iblock_element_prop_s{$iblock['ID']}";
    if (!in_array($table, $tables)) {
        continue;
    }

    $result = $db->query("SELECT * FROM `{$table}` LIMIT 1");
    if ($result->num_rows === 0) {
        continue;
    }

    $row = $result->fetch_assoc();
    $result->close();

    $fields = [];
    foreach (array_keys($row) as $key) {
        if (preg_match('#^PROPERTY_(\d+)$#', $key, $matches)) {
            $fields[$key] = $properties[$matches[1]];
        }
    }

    $iblock['properties'] = ['fields' => $fields, 'row' => $row];
}

$db->close();


ob_start();

print '<!doctype html>';
print '<html>';
print '<head>';
print '<title>Bitrix Tree</title>';
print '<meta charset="utf8"/>';
print '<style>';
print <<<CSS
    h4 {
        margin: 10px 0 2px;
    }
    table {
        font: 10px monospace;
        border-spacing: 1px;
        background: #ddd;
    }
    table th {
        background: #eee;
        font-weight: normal;
        text-align: left;
    }
    table td {
        background: #fff;
    }
CSS;
print '</style>';
print '</head>';
print '<body>';

print "<h2>Bitrix Tree v0.1</h2>";

foreach ($iblocks as $iblock)
{
    print "<h4>ID: {$iblock['ID']} CODE: {$iblock['CODE']}</h4>";
    print "{$iblock['NAME']} [b_iblock_element_prop_s{$iblock['ID']}]<br/>";

    if (!$iblock['properties'])
    {
        continue;
    }

    print '<table>';
    print '<tbody>';
    foreach ($iblock['properties']['row'] as $key => $value)
    {
        print '<tr>';
        print '<th>';
        print '<strong>'.$key.'</strong>';
        if (isset($iblock['properties']['fields'][$key]))
        {
            $f = & $iblock['properties']['fields'][$key];
            print ' &nbsp; '.$f['CODE'];
            print '<br/>';
            print mb_substr($f['NAME'], 0, 200);
        }
        print '</th>';
        print '<td>';
        print $value ? mb_substr($value, 0, 100) : '';
        print '</td>';
        print '</tr>';
    }
    print '</tbody>';
    print '</table>';
}

print '</body>';
print '</html>';

$buffer = ob_get_clean();
file_put_contents($dest, $buffer);
