<?php

error_reporting(E_ALL);

require_once 'vendor/getID3/getid3/getid3.php';

__autoloader();

use Cocur\Slugify\Slugify;

$url = 'https://www.beatport.com/track/-/';

$baseDir = dirname(__FILE__).'/';
$cacheDir = $baseDir.'cache/';
$trackDir = $baseDir.'tracks/';

echo "\n";
echo 'Konfiguration'."\n";
echo "\t".'Base directory: '.$baseDir."\n";
echo "\t".'Cache directory: '.$cacheDir."\n";
echo "\t".'Track directory: '.$trackDir."\n";
echo "\n";

/**
 * @return void
 */
function __autoloader()
{
    spl_autoload_register(function ($class) {
        $file = 'vendor/' . str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
        return false;
    });
}

/**
 * Read track directory
 * @param $trackDir
 * @param $cacheDir
 * @return array
 */
function readTracks($trackDir, $cacheDir)
{
    $tracks = [];

    if ($hdl = opendir($trackDir)) {
        while ($file = readdir($hdl)) {
            $parts = pathinfo($file);
            if ($parts['extension'] === 'mp3') {
                preg_match('/^([0-9]{6,9})_(.*)\.mp3$/', $parts['basename'], $matches);

                $track = [
                    'id' => $matches[1] ?? null,
                    'filename' => $file,
                    'filepath' => $trackDir.$file,
                    'cachepath' => null,
                    'error' => false,
                ];

                if (!empty($track['id'])) {
                    $track['cachepath'] = $cacheDir.$track['id'].'.html';
                    $tracks[$track['id']] = $track;
                }
            }
        }

        closedir($hdl);
    }

    return $tracks;
}

/**
 * Get track information from beatport
 * @param $url
 * @return mixed|null
 */
function getTrack($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_URL, $url);

    $result = curl_exec($ch);
    $info = curl_getinfo($ch);

    curl_close($ch);

    if (!$result || $info['http_code'] !== 200) {
        return null;
    }

    return $result;
}

/**
 * Grab image and save them
 * @param $url
 * @param $savePath
 * @return bool|null
 */
function grabImage($url, $savePath)
{
    if (file_exists($savePath)) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

    $result = curl_exec($ch);
    curl_close($ch);

    if (!$result) {
        return null;
    }

    if ($fp = fopen($savePath, 'x')) {
        fwrite($fp, $result);
        fclose($fp);

        return true;
    }

    return null;
}

/**
 * Generate new file names from track information
 * @param $track
 * @return string
 */
function generateNewFileName($track)
{
    $slugify = new Slugify([
        'separator' => ' ',
        'lowercase' => false
    ]);

    $newFilename =
        $track['id'].'_'.
        $slugify->slugify($track['information']['artist']).'_'.
        $slugify->slugify($track['information']['full_title']).'_'.
        $slugify->slugify($track['information']['genre']).
        '.mp3';

    $newFilename = normalize($newFilename);

    return $newFilename;
}

/**
 * Normalize text
 * @param $text
 * @return string
 */
function normalize($text)
{
    $text = strip_tags($text);
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);

    return $text;
}

// read tracks from directory
$tracks = readTracks($trackDir, $cacheDir);

if (!empty($tracks) && count($tracks) > 0) {
    // load beatport pages & information
    echo 'Load beatport pages'."\n";
    foreach ($tracks as &$track) {
        echo "\t".$track['filename'].' -> '.$url.$track['id'];

        if (!file_exists($track['cachepath'])) {
            $html = getTrack($url.$track['id']);

            if (!empty($html)) {
                file_put_contents($track['cachepath'], $html);
                echo " -> downloaded";
            } else {
                $track['error'] = true;
                echo "\e[0;31m -> ERROR\e[0m";
            }
        } else {
            echo "\e[1;34m -> already exists\e[0m";
        }

        echo "\n";
    }

    echo "\n";

    // now, it is time to extract the track information
    echo 'Extract beatport information'."\n";

    libxml_use_internal_errors(true);

    foreach ($tracks as &$track) {
        echo "\t".$track['id'].': ';

        $track['information'] = [];

        if ($track['error'] === true) {
            echo "\e[0;31mERROR\e[0m\n";
            continue;
        }

        // load file
        $dom = new DomDocument;
        $dom->loadHTMLFile($track['cachepath']);

        // create xpath
        $xpath = new DomXPath($dom);

        // title & remix
        $nodes = $xpath->query("//div[@class='interior-title']/h1");
        foreach ($nodes as $i => $node) {
            if ($i === 0) {
                $track['information']['title'] = normalize($node->nodeValue);
                echo '*';
            } elseif ($i === 1) {
                $track['information']['remix'] = normalize($node->nodeValue);
                echo '*';
            }
        }

        // full title
        if (!empty($track['information']['title']) && !empty($track['information']['remix'])) {
            $track['information']['full_title'] = $track['information']['title'].' ('.$track['information']['remix'].')';
            echo '*';
        }

        // artist
        $nodes = $xpath->query("//div[@class='interior-track-artists']/span[@class='value']");
        foreach ($nodes as $i => $node) {
            if ($i === 0) {
                $track['information']['artist'] = normalize($node->nodeValue);
                echo '*';
            } elseif ($i === 1) {
                $track['information']['remixer'] = normalize($node->nodeValue);
                echo '*';
            }
        }

        // album
        $nodes = $xpath->query("//li[@class='interior-track-releases-artwork-container ec-item']/@data-ec-name");
        foreach ($nodes as $i => $node) {
            $track['information']['album'] = normalize($node->nodeValue);
            echo '*';
        }

        // genre
        $nodes = $xpath->query("//li[@class='interior-track-content-item interior-track-genre']/span[@class='value']");
        foreach ($nodes as $i => $node) {
            $track['information']['genre'] = normalize($node->nodeValue);
            echo '*';
        }

        // label
        $nodes = $xpath->query("//li[@class='interior-track-content-item interior-track-labels']/span[@class='value']");
        foreach ($nodes as $i => $node) {
            $track['information']['label'] = normalize($node->nodeValue);
            echo '*';
        }

        // cover
        $nodes = $xpath->query("//img[@class='interior-track-release-artwork']/@src");
        foreach ($nodes as $i => $node) {
            $track['information']['cover'] = normalize($node->nodeValue);
            echo '*';
        }

        // release date
        $nodes = $xpath->query("//li[@class='interior-track-content-item interior-track-released']/span[@class='value']");
        foreach ($nodes as $i => $node) {
            $track['information']['release_date'] = normalize($node->nodeValue);
            echo '*';
        }

        // bpm
        $nodes = $xpath->query("//li[@class='interior-track-content-item interior-track-bpm']/span[@class='value']");
        foreach ($nodes as $i => $node) {
            $track['information']['bpm'] = normalize($node->nodeValue);
            echo '*';
        }

        // key
        $nodes = $xpath->query("//li[@class='interior-track-content-item interior-track-key']/span[@class='value']");
        foreach ($nodes as $i => $node) {
            $track['information']['key'] = normalize($node->nodeValue);
            echo '*';
        }

        echo "\n";
    }

    echo "\n";

    // ok, download the images
    echo 'Grab track covers'."\n";

    foreach ($tracks as &$track) {
        echo "\t".$track['filename'];

        if ($track['error'] === true) {
            echo "\e[0;31m -> ERROR\e[0m";
            continue;
        }

        if (isset($track['information']['cover']) && !empty($track['information']['cover'])) {
            echo ' -> '.$track['information']['cover'];

            $parts = pathinfo($track['information']['cover']);
            $coverPath = $cacheDir.$parts['basename'];

            $status = grabImage($track['information']['cover'], $coverPath);

            if ($status === true) {
                $track['information']['cover'] = $coverPath;

                echo "\e[0;32m -> image grabbed\e[0m";
            } elseif ($status === false) {
                echo "\e[1;34m -> already exists\e[0m";
            } else {
                echo "\e[0;31m -> image not grabbed and saved\e[0m";
            }
        } else {
            echo "\e[0;31m -> no cover information\e[0m";
        }

        echo "\n";
    }

    echo "\n";

    // it is time to rename the files
    echo 'Rename files'."\n";

    foreach ($tracks as &$track) {
        echo "\t".$track['filename'];

        if ($track['error'] === true) {
            echo "\e[0;31m -> ERROR\e[0m";
            continue;
        }

        $newFilename = generateNewFileName($track);

        $track['filename_new'] = $newFilename;
        $track['filepath_new'] = $trackDir.$newFilename;

        echo ' -> '.$track['filename_new'];

        if (!file_exists($track['filepath_new'])) {
            if (rename($track['filepath'], $track['filepath_new'])) {
                echo "\e[0;32m -> renamed\e[0m";
            } else {
                echo "\e[0;31m -> renaming went wrong\e[0m";
            }
        } else {
            echo "\e[1;34m -> file not renamed\e[0m";
        }

        echo "\n";
    }

    // all is in our information bag, now it is time to write id3 tags
    echo 'Tag files'."\n";

    foreach ($tracks as &$track) {
        if ($track['error'] === true) {
            echo "\t".$track['filename']."\e[0;31m -> ERROR\e[0m\n";
            continue;
        }

        echo "\t".$track['filename_new'];

        $getID3 = new getID3;
        $getID3->setOption(array('encoding' => 'UTF-8'));

        getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'write.php', __FILE__, true);

        $data = [
            'track_number' => [$track['id']],
            'artist' => [$track['information']['artist'] ?? ''],
            'title' => [$track['information']['full_title'] ?? ''],
            'remixer' => [$track['information']['remixer'] ?? ''],
            'album' => [$track['information']['album'] ?? ''],
            'genre' => [$track['information']['genre'] ?? ''],
            'publisher' => [$track['information']['label'] ?? ''],
            'year' => [substr($track['information']['release_date'] ?? date('Y-m-d'), 0, 4)],
            'original_release_time' => [$track['information']['release_date'] ?? ''],
            'release_time' => [$track['information']['release_date'] ?? ''],
            'popularimeter' => array('email' => '', 'rating' => 255, 'data' => 0),
            'bpm' => [(int)$track['information']['bpm'] ?? ''],
            'initial_key' => [$track['information']['key'] ?? ''],
        ];

        if ($img = file_get_contents($track['information']['cover'])) {
            if ($exif = exif_imagetype($track['information']['cover'])) {
                $parts = pathinfo($track['information']['cover']);
                $data['attached_picture'] = [
                    [
                        'data' => $img,
                        'picturetypeid' => '03',
                        'description' => $parts['filename'],
                        'mime' => image_type_to_mime_type($exif),
                    ],
                ];

                echo ' -> Cover added';
            } else {
                echo ' -> Invalid image format (only GIF, JPEG, PNG)';
            }
        } else {
            echo ' -> Can not open '.$track['information']['cover'];
        }

        $tagwriter = new getid3_writetags;
        $tagwriter->filename = $track['filepath_new'];
        $tagwriter->tagformats = array('id3v1', 'id3v2.4');
        $tagwriter->tag_encoding = 'UTF-8';
        $tagwriter->overwrite_tags = true;
        $tagwriter->remove_other_tags = true;
        $tagwriter->tag_data = $data;

        if ($tagwriter->WriteTags() === true) {
            echo "\e[0;32m -> File tagged\e[0m";
        } else {
            echo "\e[0;31m -> No tags written\e[0m\n";
            echo "\n";
            var_dump($tagwriter->errors);
            echo "\n";
        }

        echo "\n";
    }

    echo "\n";
} else {
    echo "\e[1;34mNo tracks available.\e[0m\n";
    echo "\n";
}

echo "\e[0;32mDONE!\e[0m\n";
echo "\n";
