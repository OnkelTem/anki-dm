<?php

namespace AnkiDeckManager;

class Builder {

  public static function build($decks, $src_dir, $build_dir, $lang) {
    $globals = [
      'deck' => Util::getJson($src_dir . '/deck.json'),
      'config' => Util::getJson($src_dir . '/config.json'),
      'model' => Util::getJson($src_dir . '/model.json'),
      'media' => Util::getFilesList($src_dir . '/media'),
      'templates' => Util::getTemplates($src_dir . '/templates'),
      'desc' => Util::getRaw($src_dir . '/desc.html'),
      'css' => Util::getRaw($src_dir . '/style.css'),
      'data' => Util::getCsv($src_dir . '/data.csv'),
    ];

    $languages = array_keys($globals['data']);

    if ($lang) {
      if (!in_array($lang, $languages)) {
        Util::err("Language '$lang' is not available.");
      }
      else {
        $languages = [$lang];
      }
    }

    foreach ($languages as $lang) {

      // Read decks
      $decks_build = static::readDecks($decks, $src_dir . '/decks');

      // Generate decks
      foreach ($decks_build as $deck => $deck_build) {
         Util::msg("Building deck: $deck (Language: $lang)");

         // Encode UUIDs with language
        $deck_build['deck']['uuid'] = Util::uuidEncode($deck_build['deck']['uuid'], $lang);
        $deck_build['config']['uuid'] = Util::uuidEncode($deck_build['config']['uuid'], $lang);
        $deck_build['model']['uuid'] = Util::uuidEncode($deck_build['model']['uuid'], $lang);

        // Populate top-level properties
        $deck_data = [
          '__type__' => 'Deck',
          'crowdanki_uuid' => $deck_build['deck']['uuid'],
          'name' => Util::ensureDeckName($lang == 'default' ? $deck : $deck . '[' . $lang . ']'),
          'desc' => isset($deck_build['@desc']) ? $deck_build['@desc'] : $globals['desc'],
        ] + array_merge($globals['deck'], $deck_build['@deck']);

        // Populate config
        $deck_data['deck_configurations'][] = [
          '__type__' => 'DeckConfig',
          'crowdanki_uuid' => $deck_build['config']['uuid'],
          'name' => $deck_build['config']['name']
        ] + array_merge($globals['config'], $deck_build['@config']);
        $deck_data['deck_config_uuid'] = $deck_build['config']['uuid'];

        // Populate templates
        $deck_templates_info = [];
        $k = 0;
        foreach ($deck_build['templates'] as $template) {
          if (!isset($globals['templates'][$template])) {
            Util::err("Field template '$template' not found.");
          }
          $deck_templates_info[] = ['name'  => $template, 'ord' => $k++] + $globals['templates'][$template];
        }

        // Get field list from the data
        $field_list = [];
        foreach(array_keys($globals['data'][$lang]) as $field_name) {
          $field_list[$field_name] = 1;
        }

        // Populate fields
        $deck_fields_info = [];
        $i = 0;
        foreach ($deck_build['fields'] as $field) {
          if (!isset($field_list[$field])) {
            Util::err("Field '$field' not found.");
          }
          $deck_fields_info[] = ['name'  => $field, 'ord' => $i++] + Util::getFieldDefaults();
        }

        // Populate model
        // We don't populate 'req' since it doesn't seem really needed.
        // Whenever you export a deck 'req' is calculated by Anki.
        $deck_data['note_models'][] = [
            '__type__' => 'NoteModel',
            'crowdanki_uuid' => $deck_build['model']['uuid'],
            'name' => $deck_build['model']['name'],
            'flds' => $deck_fields_info,
            'tmpls' => $deck_templates_info,
            'css' => isset($deck_build['@css']) ? $deck_build['@css'] : $globals['css'],
        ] + array_merge($globals['model'], $deck_build['@model']);

        // Populate data and media

        // Check for guid column
        if (!isset($globals['data'][$lang]['guid'])) {
          Util::err('Missed required "guid" column in "data.csv"');
        }

        // Check if guid values are unique
        if (count($globals['data'][$lang]['guid']) !== count(array_unique($globals['data'][$lang]['guid']))) {
          Util::err('Found duplicate values in the "guid" column. Run "index" command.');
        }

        $deck_fields_data = [];
        $deck_media = [];
        foreach ($deck_build['fields'] as $field) {
          if (!isset($globals['data'][$lang][$field])) {
            Util::err("Column '$field' is missed in 'data.csv'.");
          }
          foreach ($globals['data'][$lang][$field] as $i => $cell) {
            $deck_fields_data[$i]['fields'][] = $cell;
            // Scan for media
            foreach ($globals['media'] as $media_file) {
              if (strpos($cell, $media_file) !== FALSE) {
                $deck_media[] = $media_file;
              }
            }
          }
        }

        foreach ($globals['data'][$lang]['guid'] as $i => $cell) {
          if (empty($cell)) {
            Util::err('Missing value in the "guid" field in the row: ' . "\n" . Util::toJson($deck_fields_data[$i]['fields']) . "\n" . 'Run "index" command to fix the problem.');
          }
          $deck_fields_data[$i]['guid'] = Util::guidDecode($cell, $deck_build['model']['uuid']);
        }

        if (!isset($globals['data'][$lang]['tags'])) {
          foreach ($globals['data'][$lang]['tags'] as $i => $cell) {
            $deck_fields_data[$i]['tags'] = explode(' ', $cell);
          }
        }

        $deck_data['media_files'] = $deck_media;
        foreach($deck_fields_data as $row) {
          $deck_data['notes'][] = [
            '__type__' => 'Note',
            'data' => "",
            'fields' => $row['fields'],
            'flags' => 0,
            'guid' => $row['guid'],
            'note_model_uuid' => $deck_build['model']['uuid'],
            'tags' => isset($row['tags']) ? $row['tags'] : [],
          ];
        }

        // Write deck
        $localized_deck = $lang == 'default' ? $deck : $deck . '_' . $lang;
        $deck_dir = $build_dir . '/' . $localized_deck;
        Util::prepareDir($deck_dir);
        file_put_contents($deck_dir . '/' . $localized_deck . '.json', Util::toJson($deck_data));

        // Copy media
        Util::prepareDir($deck_dir . '/media');
        foreach($deck_media as $media_file) {
          copy($src_dir . '/media/' . $media_file, $deck_dir . '/media/' . $media_file);
        }
      }
    }
  }

  protected static function readDecks($decks, $dir) {
    $decks_data = [];
    if (empty($decks)) {
      // Read all the decks
      if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
          if ($file == '.' || $file == '..') {
            continue;
          }
          if (filetype($dir . '/' . $file) == 'dir') {
            $decks_data[$file] = static::readDeck($dir . '/' . $file);
          }
        }
      }
      else {
        Util::err('Cannot read dir: ' . $dir);
      }
    }
    else {
      // Build only specified decks
      foreach($decks as $deck) {
        if (file_exists($dir . '/' . $deck) && filetype($dir . '/' . $deck) == 'dir') {
          $decks_data[$deck] = static::readDeck($dir . '/' . $deck);
        }
        else {
          Util::err('Deck not found: ' . $dir . '/' . $deck);
        }
      }
    }
    return $decks_data;
  }

  /**
   * @param $dir
   * @return array
   */
  protected static function readDeck($dir) {
    $deck_data = Util::getJson($dir . '/build.json');
    $deck_data['@deck'] = Util::getJson($dir . '/deck.json', FALSE);
    $deck_data['@config'] = Util::getJson($dir . '/config.json', FALSE);
    $deck_data['@desc'] = Util::getRaw($dir . '/info.html', FALSE);
    $deck_data['@model'] = Util::getJson($dir . '/model.json', FALSE);
    $deck_data['@css'] = Util::getRaw($dir . '/style.css', FALSE);
    return $deck_data;
  }

}
