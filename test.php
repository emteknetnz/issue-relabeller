<?php

use emteknetnz\DataFetcher\Misc\Consts;
use GuzzleHttp\Client;

require 'vendor/autoload.php';

function get_ghrepos() {
    $ghrepos = [];
    foreach (['silverstripe', 'sybiote'] as $acct) {
        foreach (['regular', 'tooling'] as $type) {
            foreach (Consts::MODULES[$type][$acct] ?? [] as $repo) {
                $ghrepos[] = "$acct/$repo";
            }
        }
    }
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
    global $client;
    if (!$client) {
        $client = new Client(['base_uri' => 'https://api.github.com']);
    }
    $headers = [
        'Accept: application/vnd.github.v3+json',
        'Authorization: token ' . get_github_token()
    ];
    $resp = $client->request(strtoupper($method), $path, ['headers' => $headers]);
    return json_decode($resp->getBody());
}

function pp($json) {
    echo json_encode($json, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
}

$ghrepos = get_ghrepos();
$ghrepos = [
    'silverstripe/silverstripe-admin'
];

# https://docs.guzzlephp.org/en/stable/quickstart.html

# https://api.github.com/repos/OWNER/REPO/labels

$i = 0;
$ghrepo = $ghrepos[0];
pp(
    req('get', "repos/$ghrepo/labels")
);
