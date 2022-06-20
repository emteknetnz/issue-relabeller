<?php
use emteknetnz\DataFetcher\Misc\Consts;
use GuzzleHttp\Client;

require 'vendor/autoload.php';

const RENAME = [
    'effort/easy' => 'complexity/low',
    'effort/medium' => 'complexity/medium',
    'effort/hard' => 'complexity/high',
    'change/major' => 'type/api-change'
];

// Do not prefix color with hash, can be lower or upper case
const COLORS = [
    'affects/v4' => '5319e7',
    'affects/v5' => '0e8a16',
    'complexity/low' => 'C2E0C6',
    'complexity/medium' => 'FEF2C0',
    'complexity/high' => 'F9D0C4',
    'Epic' => '3E4B9E',
    'impact/critical' => 'e11d21',
    'impact/high' => 'eb6420',
    'impact/medium' => 'f7c6c7',
    'impact/low' => 'fef2c0',
    'rfc/accepted' => 'dddddd',
    'rfc/draft' => 'dddddd',
    'type/api-change' => '1D76DB',
    'type/bug' => 'd93f0b',
    'type/docs' => '02d7e1',
    'type/enhancement' => '0e8a16',
    'type/userhelp' => 'c5def5',
    'type/UX' => '006b75'
];

const REMOVE = [
    'change/patch',
    'change/minor',
    'feedback-required/author',
    'feedback-required/core-team',
    'affects/v3',
    'type/frontend',
    #
    'dependencies',
    'good first issue',
    'javascript',
    'wontfix',
    'invalid',
    'question',
    'duplicate',
    'enhancement',
    'help wanted',
    'bug',
    'documentation',
    'affects/mobile',
    'blocker',
    'WIP',
    'feature',
    'discussion',
    'v4',
    'required-for-merge',
    'post-release',
    'api',
    'hacktoberfest-accepted',
];

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
    $options = ['headers' => $headers];
    if ($jsonBody) {
        $options['body'] = $jsonBody;
    }
    $resp = $client->request($method, $path, $options);
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
        foreach ($json as $label) {
            $lines[] = implode(',', [
                $ghrepo,
                $label->id,
                $label->name
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

function rename_labels() {
    # foreach (get_ghrepos() as $ghrepo) {
    foreach (['silverstripe/silverstripe-crazy-egg'] as $ghrepo) {
        $json = req('get', "repos/$ghrepo/labels");
        foreach ($json as $label) {
            $name = $label->name;
            if (isset(RENAME[$name])) {
                // https://docs.github.com/en/rest/issues/labels#update-a-label
                $new_name = RENAME[$name];
                echo "Renaming $name to $new_name on $ghrepo\n";
                // note: no need to escape spaces in $name
                req('PATCH', "repos/$ghrepo/labels/$name", "{\"new_name\":\"$new_name\"}");
            }
        }
    }
}

function delete_labels() {
    # foreach (get_ghrepos() as $ghrepo) {
    foreach (['silverstripe/silverstripe-crazy-egg'] as $ghrepo) {
        $json = req('get', "repos/$ghrepo/labels");
        foreach ($json as $label) {
            $name = $label->name;
            if (in_array($name, REMOVE)) {
                // https://docs.github.com/en/rest/issues/labels#delete-a-label
                echo "Deleting $name from $ghrepo\n";
                // note: no need to escape spaces in $name
                req('DELETE', "repos/$ghrepo/labels/$name");
            }
        }
    }
}

function update_or_create_labels() {
    # foreach (get_ghrepos() as $ghrepo) {
    foreach (['silverstripe/silverstripe-crazy-egg'] as $ghrepo) {
        $json = req('get', "repos/$ghrepo/labels");
        foreach (COLORS as $create_name => $color) {
            $found = false;
            foreach ($json as $label) {
                $name = $label->name;
                if ($name == $create_name) {
                    if ($label->color != $color) {
                        // https://docs.github.com/en/rest/issues/labels#update-a-label
                        echo "Updating color for $name to $color on $ghrepo\n";
                        // note: no need to escape spaces in $name
                        req('PATCH', "repos/$ghrepo/labels/$name", "{\"color\":\"$color\"}");
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // https://docs.github.com/en/rest/issues/labels#create-a-label
                echo "Creating label for $create_name with color $color on $ghrepo\n";
                req('POST', "/repos/$ghrepo/labels", "{\"name\":\"$create_name\",\"color\":\"$color\"}");
            }
        }
    }
}

# SCRIPT
init();

#rename_labels();
update_or_create_labels();
#delete_labels();

// # fetch_labels();
// $data = read_csv('output/labels.csv');
// $labels = [];
// foreach ($data as $r) {
//     $labels[$r['name']] ??= 0;
//     $labels[$r['name']]++;
// }
// asort($labels);
// $labels = array_reverse($labels);

// print_r($labels);

// pp(
//     req('get', "repos/$ghrepo/labels")
// );
