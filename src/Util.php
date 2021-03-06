<?php

namespace AnkiDeckManager;

use Ramsey\Uuid\Uuid;

class Util {

  public static function prepareDir($dir) {
    // Prepare destination dir
    if (!file_exists($dir)) {
      mkdir($dir, 0755, TRUE);
    }

    if (!is_dir($dir)) {
      Util::err('Cannot create directory: ' . $dir);
    }
  }

  public static function msg($msg) {
    fwrite(STDOUT, $msg . "\n");
  }

  public static function err($msg) {
    fwrite(STDERR,'Error: ' . $msg . "\n");
    die(1);
  }

  public static function warn($msg) {
    fwrite(STDERR, 'Warning: ' .$msg . "\n");
  }

  public static function toJson($data) {
    /** @noinspection PhpComposerExtensionStubsInspection */
    $result = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $result = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $result);
    return $result;
  }

  public static function getFilesList($dir, $type = 'file') {
    // Read templates
    $data = [];
    if ($dh = opendir($dir)) {
      while (($file = readdir($dh)) !== false) {
        if ($file == '.' || $file == '..') {
          continue;
        }
        if (filetype($dir . '/' . $file) == $type) {
          $data[] = $file;
        }
      }
    }
    return $data;
  }

  public static function getJsons($dir) {
    $data = [];
    if ($dh = opendir($dir)) {
      while (($file = readdir($dh)) !== false) {
        if ($file == '.' || $file == '..') {
          continue;
        }
        if (filetype($dir . '/' . $file) == 'file') {
          $data[] = static::getJson($dir . '/' . $file);
        }
      }
    }
    return $data;
  }

  public static function getFields($dir) {
    $data = [];
    if ($dh = opendir($dir)) {
      while (($file = readdir($dh)) !== false) {
        if ($file == '.' || $file == '..') {
          continue;
        }
        if (filetype($dir . '/' . $file) == 'file') {
          if (preg_match('~(.*)\.json$~', $file, $matches)) {
            $field = $matches[1];
            $data[$field] = static::getJson($dir . '/' . $file);
          }
        }
      }
    }
    return $data;
  }

  public static function getTemplates($dir) {
    $data = [];
    if ($dh = opendir($dir)) {
      while (($file = readdir($dh)) !== false) {
        if ($file == '.' || $file == '..') {
          continue;
        }
        if (filetype($dir . '/' . $file) == 'file') {
          if (preg_match('~(.*)\.html$~', $file, $matches)) {
            $template = $matches[1];
            $html = static::getRaw($dir . '/' . $file);
            if (($parts = preg_split('~\r?\n\r?\n--\r?\n\r?\n~', $html)) === FALSE) {
              static::err("Incorrect template: $file. It must consist of two parts divided by '--' on a separate line.");
            }
            $data[$template] = [
              'qfmt' => $parts[0],
              'afmt' => $parts[1],
              'name' => $template,
              'bafmt' => '',
              'bqfmt' => '',
              'did' => NULL,
            ];
          }
        }
      }
    }
    return $data;
  }

  public static function getRaw($file, $required = TRUE) {
    if (!$required && !file_exists($file)) {
      return NULL;
    }
    if (($data = @file_get_contents($file)) === FALSE) {
      Util::err('Cannot read file: ' . $file);
    }
    return $data;
  }

  public static function getFieldDefaults() {
    return [
      "font" => 'Arial',
      "media" => [],
      "rtl" => FALSE,
      "size" => 20,
      "sticky" => FALSE
    ];
  }

  public static function getJson($file, $required = TRUE) {
    $data = static::getRaw($file, $required);
    if (!isset($data)) {
      return [];
    }
    /** @noinspection PhpComposerExtensionStubsInspection */
    return json_decode($data, TRUE);
  }

  public static function getCsv($file, $required = TRUE) {
    if (!$required && !file_exists($file)) {
      return NULL;
    }
    if (!($fp = @fopen($file, 'r'))) {
      static::err('Cannot read file: ' . $file);
    }
    $result = [];
    $header = [];
    $langs = ['default' => []];
    while(!feof($fp)) {
      if (($row = fgetcsv($fp)) === FALSE) {
        break;
      }
      if (empty($header)) {
        foreach ($row as $i => $col) {
          if (strpos($col, ':') !== FALSE) {
            list($field, $lang) = explode(':', $col);
            if ($field == 'guid') {
              Util::warn('Translating "guid" field doesn\'t have any sense.');
            }
            $langs[$lang][$field] = $i;
          }
          else {
            $field = $col;
            $langs['default'][$field] = $i;
          }
        }
        foreach ($langs as $lang => $cols) {
          if ($lang !== 'default') {
            $langs[$lang] = array_merge($langs['default'], $langs[$lang]);
          }
        }
        foreach ($langs as $lang => $cols) {
          $langs[$lang] = array_flip($langs[$lang]);
          ksort($langs[$lang]);
        }
        $header = TRUE;
        continue;
      }
      foreach ($langs as $lang => $cols) {
        foreach ($row as $j => $cell) {
          if (isset($cols[$j])) {
            $result[$lang][$cols[$j]][] = $cell;
          }
        }
      }
    }
    if (empty($result)) {
      // Create one row anyway
      foreach ($langs as $lang => $cols) {
        foreach($cols as $j => $col) {
          $result[$lang][$col][] = "";
        }
      }
    }
    return $result;
  }

  protected static function getGuidChars() {
    return 'abcdefghijklmnopqrstuvwxyz' . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . '0123456789' . "!#$%&()*+,-./:;<=>?@[]^_`{|}~";
  }

  public static function createGuid() {
    $table = static::getGuidChars();
    $num = rand(0, PHP_INT_MAX);
    $buf = "";
    while ($num) {
      $mod = $num % strlen($table);
      $num = ($num - $mod) / strlen($table);
      $buf = $table[$mod] . $buf;
    }
    return $buf;
  }

  public static function createUuid() {
    $result = '';
    try {
      $result = (string) Uuid::uuid1();
    }
    catch (\Exception $e) {
      static::err($e->getMessage());
    }
    return $result;
  }

  public static function uuidEncode($uuid, $lang) {
    if ($lang == 'default') {
      return $uuid;
    }
    $table = '0123456789abcdef';
    $lang_code = 0;
    for ($i = 0; $i < strlen($lang); $i++) {
      $lang_code += ord($lang[$i]);
    }
    $j = 0;
    $result = $uuid;
    while ($j < strlen($uuid)) {
      if ($uuid[$j] == '-') {
        $j++;
        continue;
      }
      $a = $uuid[$j];
      if (($an = strpos($table, $a)) === FALSE) {
        Util::err("Cannot encode 'uuid': uuid char not found: $a. 'lang' = $lang, 'uuid' = $uuid");
      }
      $cn = ($an + $lang_code) % strlen($table);
      $result[$j] = $table[$cn];
      $j++;
    }
    return $result;
  }

  public static function guidEncode($guid, $uuid) {
    return static::guidTransform($guid, $uuid, 'encode');
  }

  public static function guidDecode($guid, $uuid) {
    return static::guidTransform($guid, $uuid, 'decode');
  }

  protected static function guidTransform($guid, $uuid, $dir = 'encode') {
    $table = static::getGuidChars();
    $i = 0;
    $j = 0;
    $result = $guid;
    while ($j < strlen($uuid)) {
      if ($i == strlen($guid)) {
        $i = 0;
      }
      $a = $guid[$i];
      $b = $uuid[$j];
      if (($an = strpos($table, $a)) === FALSE) {
        Util::err("Cannot encode 'guid': guid char not found: $a. 'guid' = $guid, 'uuid' = $uuid");
      }
      if (($bn = strpos($table, $b)) === FALSE) {
        Util::err("Cannot encode 'guid': guid char not found: $b. 'guid' = $guid, 'uuid' = $uuid");
      }
      if ($dir == 'encode') {
        $cn = $an - $bn;
      }
      else {
        $cn = $an + $bn;
      }
      if ($cn >= strlen($table)) {
        $cn = $cn % strlen($table);
      }
      else if ($cn < 0) {
        $cn = strlen($table) + $cn;
      }
      $result[$i] = $table[$cn];
      $i++;
      $j++;
    }
    return $result;
  }

  public static function isDirEmpty($dir) {
    if (!is_readable($dir)) return NULL;
    return (count(scandir($dir)) == 2);
  }

  public static function deckToFilename($deck) {
    $filename = str_replace('_', '___', $deck);
    $filename = str_replace('::', '__', $filename);
    return static::ensureFilename($filename);
  }

  public static function filenameToDeck($filename) {
    $deck = preg_replace_callback('~(_+)~', function($match) {
      return strlen($match[1]) == 2 ? '::' : $match[1];
    }, $filename);
    $deck = str_replace('___', '_', $deck);
    return $deck;
  }

  public static function ensureFilename($filename) {
    $allowed_chars = str_split('abcdefghijklmnopqrstuvwxyz' . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . '0123456789' . "$-_ ");
    $result = '';
    foreach(str_split($filename) as $char) {
      $result .= in_array($char, $allowed_chars) ? $char : '-';
    }
    return $result;
  }


  public static function checkFieldName($name) {
    if (in_array($name, ['guid', 'tags'])) {
      Util::err('Fields with names "guid" and "tags" are reserved, please rename them first.');
    }
    return $name;
  }

  /**
   * Copy a file, or recursively copy a folder and its contents
   *
   * @author      Aidan Lister <aidan@php.net>
   * @version     1.0.1
   * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
   * @param       string   $source    Source path
   * @param       string   $dest      Destination path
   * @return      bool     Returns TRUE on success, FALSE on failure
   */
  public static function xcopy($source, $dest) {
    // Check for symlinks
    if (is_link($source)) {
      return symlink(readlink($source), $dest);
    }

    // Simple copy for a file
    if (is_file($source)) {
      return copy($source, $dest);
    }

    // Make destination directory
    if (!is_dir($dest)) {
      mkdir($dest);
    }

    // Loop through the folder
    $dir = dir($source);
    while (false !== $entry = $dir->read()) {
      // Skip pointers
      if ($entry == '.' || $entry == '..') {
        continue;
      }

      // Deep copy directories
      static::xcopy("$source/$entry", "$dest/$entry");
    }

    // Clean up
    $dir->close();
    return true;
  }
}
