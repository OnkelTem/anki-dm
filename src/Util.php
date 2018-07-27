<?php

namespace AnkiDeckManager;

class Util {

  public static function prepareDir($dir) {
    // Prepare destination dir
    if (!file_exists($dir)) {
      mkdir($dir, 0755, TRUE);
    }

    if (!is_dir($dir)) {
      err('Cannot create directory: ' . $dir);
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

  public static function getJson($file, $required = TRUE) {
    $data = static::getRaw($file, $required);
    if (!isset($data)) {
      return [];
    }
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

  public static function anki_py_base62($num, $extra = "") {
    $table = 'abcdefghijklmnopqrstuvwxyz' . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . '0123456789' . $extra;
    $buf = "";
    while ($num) {
      $mod = $num % strlen($table);
      $num = ($num - $mod) / strlen($table);
      $buf = $table[$mod] . $buf;
    }
    return $buf;
  }

  public static function anki_py_base91($num) {
    $base91_extra_chars = "!#$%&()*+,-./:;<=>?@[]^_`{|}~";
    # all printable characters minus quotes, backslash and separators
    return static::anki_py_base62($num, $base91_extra_chars);
  }

  public static function ankiPyGuid64() {
    return static::anki_py_base91(rand(0, PHP_INT_MAX));
  }

  public static function isDirEmpty($dir) {
    if (!is_readable($dir)) return NULL;
    return (count(scandir($dir)) == 2);
  }

  public static function ensureDeckFilename($filename) {
    return str_replace('::', '__', $filename);
  }

  public static function checkFieldName($name) {
    if (in_array($name, ['guid', 'tags'])) {
      Util::err('Fields with names "guid" and "tags" are reserved, please rename them first.');
    }
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
