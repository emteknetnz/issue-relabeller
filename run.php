<?php
use emteknetnz\DataFetcher\Misc\Consts;
use GuzzleHttp\Client;

require 'vendor/autoload.php';

function get_ghrepos() {
    $ghrepos = [];
    foreach (['silverstripe', 'symbiote'] as $acct) {
        foreach (['regular', 'tooling'] as $type) {
            foreach (Consts::MODULES[$type][$acct] ?? [] as $repo) {
                $ghrepos[] = "$acct/$repo";
            }
        }
    }
    return $ghrepos;
}

function get_github_token() {
    $fn = '.github-token';
    if (!file_exists($fn)) {
        echo "Missing .github-token\n";
        die;
    }
    $token = trim(file_get_contents($fn));
    if (empty($token)) {
        echo ".github-token is empty\n";
        die;
    }
    return $token;
}

$client = null;
function req($method, $path, $jsonBody = null) {
    # https://docs.guzzlephp.org/en/stable/quickstart.html
    global $client;
    if (!$client) {
        $client = new Client(['base_uri' => 'https://api.github.com']);
    }
    $headers = [
        'Accept' => 'application/vnd.github.v3+json',
        'Authorization' => 'token ' . get_github_token()
    ];
    $method = strtoupper($method);
    echo "$method $path\n";
    $resp = $client->request($method, $path, ['headers' => $headers]);
    return json_decode($resp->getBody());
}

function pp($json) {
    echo json_encode($json, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
}

function init() {
    if (!file_exists('output')) {
        mkdir('output');
    }
}

function fetch_labels() {
    # https://api.github.com/repos/OWNER/REPO/labels
    $lines = ['ghrepo,id,name'];
    foreach (get_ghrepos() as $ghrepo) {
        $json = req('get', "repos/$ghrepo/labels");
        foreach ($json as $obj) {
            $lines[] = implode(',', [
                $ghrepo,
                $obj->id,
                $obj->name
            ]);
        }
    }
    file_put_contents('output/labels.csv', implode("\n", $lines));
}

function read_csv($fn) {
    $data = [];
    $headers = null;
    $header_row = true;
    if (($fh = fopen($fn, 'r')) !== false) {
        while (($r = fgetcsv($fh, 1000, ',')) !== false) {
            if ($header_row) {
                $headers = $r;
                $header_row = false;
            } else {
                $row = [];
                for ($c = 0; $c < count($r); $c++) {
                    $header = $headers[$c];
                    $row[$header] = $r[$c];
                }
                $data[] = $row;
            }
        }
        fclose($fh);
    }
    return $data;
}

# SCRIPT
init();
# fetch_labels();
$data = read_csv('output/labels.csv');

$labels = [];
foreach ($data as $r) {
    $labels[$r['name']] ??= 0;
    $labels[$r['name']]++;
}
asort($labels);
$labels = array_reverse($labels);

print_r($labels);

// pp(
//     req('get', "repos/$ghrepo/labels")
// );
