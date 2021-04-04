<?php

namespace AnkiDeckManager;

class Indexer {

  public static function index($full, $base) {
    $file = $base . '/data.csv';
    if (!($fp = @fopen($file, 'r'))) {
      Util::err('Cannot read file: ' . $file);
    }
    $data = [];
    $guid_column = NULL;
    $header_found = FALSE;
    while(!feof($fp)) {
      if (($row = fgetcsv($fp)) === FALSE) {
        break;
      }
      if (!$header_found) {
        $header_found = TRUE;
        if (($guid_column = array_search('guid', $row)) === FALSE) {
          Util::err('Missing "guid" column');
        }
      }
      $data[] = $row;
    }
    $guids = [];
    fclose($fp);
    if (!($fp = @fopen($file, 'w'))) {
      Util::err('Cannot write to file: ' . $file);
    }
    foreach ($data as $i => &$row) {
      $guid = &$row[$guid_column];
      if ($i === 0) {
        fputcsv($fp, $row);
        continue;
      }
      if (empty($guid)) {
        // Empty
        $guid = Util::createGuid();
      }
      else if (in_array($guid, $guids)) {
        // Duplicate
        $guid = Util::createGuid();
      }
      else if ($full) {
        // Reindex
        $guid = Util::createGuid();
      }
      $guids[] = $guid;
      fputcsv($fp, $row);
    }
    fclose($fp);
    Util::msg('Successfully reindexed "data.csv"');
  }

}
