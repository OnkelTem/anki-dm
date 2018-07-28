<?php

namespace AnkiDeckManager;

class Importer {

  static function import($path, $dir, $deck = NULL) {

    $path = rtrim($path, '/');
    $file = $path . '/' . basename($path) . '.json';

    $dir = rtrim($dir, '/');
    if (!$dir) {
      $dir = 'src';
    }

    $deck_data = Util::getJson($file);

    // Check if we support such deck
    if (count($deck_data['deck_configurations']) > 1 || count($deck_data['note_models']) > 1) {
      err("Multiple models or configurations per deck is not supported");
    }

    // Check if it's an empty deck
    if (count($deck_data['deck_configurations']) == 0 || count($deck_data['note_models']) == 0) {
      err("Decks with empty models or configurations are not supported. Try adding one card in your deck.");
    }

    // Array of IDs
    $uuids['deck'] = $deck_data['crowdanki_uuid'];

    // GLOBAL

    // Saving global deck properties
    $deck_info = array_intersect_key($deck_data, array_flip(['dyn', 'extendNew', 'extendRev'])) + ['children' => []];
    file_put_contents("$dir/deck.json", Util::toJson($deck_info));

    // Save configuration
    $configuration = $deck_data['deck_configurations'][0];
    $uuids['config'] = $configuration['crowdanki_uuid'];
    $configuration_info = array_intersect_key($configuration, array_flip(['autoplay', 'dyn', 'lapse', 'maxTaken', 'name', 'new', 'replayq', 'rev', 'timer']));
    file_put_contents("$dir/config.json", Util::toJson($configuration_info));

    // Saving deck description
    $desc = $deck_data['desc'];
    file_put_contents("$dir/desc.html", $desc);

    // Save model
    $model = $deck_data['note_models'][0];
    $uuids['model'] = $model['crowdanki_uuid'];
    $model_info = array_intersect_key($model, array_flip(['latexPost', 'latexPre', 'type'])) + ['vers' => []];
    file_put_contents("$dir/model.json", Util::toJson($model_info));

    // Save fields
    Util::prepareDir("$dir/fields");
    foreach($model['flds'] as $field) {
      $field_name = Util::checkFieldName($field['name']);
      // Unset ordinal numbers
      unset($field['ord']);
      unset($field['name']);
      file_put_contents("$dir/fields/$field_name.json", Util::toJson($field));
    }
    $field_list = array_map(function ($value) {
      return $value['name'];
    }, $model['flds']);

    // Save data
    $notes = $deck_data['notes'];
    if (!($fp = fopen("$dir/data.csv", 'w'))) {
      Util::err("Cannot write to file: $dir/data.csv");
    }
    $header = [];
    // Header row
    $header[] = 'guid';
    foreach($field_list as $i => $field) {
      $header[] = $field;
    }
    $header[] = 'tags';
    fputcsv($fp, $header);
    foreach($notes as $note) {
      $row = [];
      $row[] = Util::guidEncode($note['guid'], $uuids['model']);
      foreach($field_list as $i => $field) {
        $row[] = $note['fields'][$i];
      }
      $row[] = implode(' ', $note['tags']);
      fputcsv($fp, $row);
    }
    fclose($fp);

    // Save media
    $media_files = $deck_data['media_files'];
    Util::prepareDir("$dir/media");
    foreach ($media_files as $media_file) {
      copy("$path/media/$media_file", "$dir/media/$media_file");
    }

    // Saving templates
    $templates = $model['tmpls'];
    Util::prepareDir("$dir/templates");
    foreach($templates as $template) {
      file_put_contents("$dir/templates/$template[name].html", $template['qfmt'] . "\n\n--\n\n" . $template['afmt']);
    }
    // Keep templates list for later
    $template_list = array_map(function ($value) {
      return $value['name'];
    }, $templates);

    // Saving css
    $css = $model['css'];
    file_put_contents("$dir/style.css", $css . "\n");

    // DECK-SPECIFIC

    if (isset($deck)) {
      $deck_name = $deck;
    }
    else {
      $deck_name = $deck_data['name'];
      $deck = $deck_name;
    }

    $deck_dir_name = Util::ensureDeckFilename($deck_name);
    Util::prepareDir("$dir/decks/$deck_dir_name");

    $build_info = [
      'name' => $deck_name,
      'model_name' => $model['name'],
      'uuids' => [
        'deck' => $uuids['deck'],
        'model' => $uuids['model'],
        'config' => $uuids['config']
      ],
      'fields' => $field_list,
      'templates' => $template_list,
    ];
    file_put_contents("$dir/decks/$deck_dir_name/build.json", Util::toJson($build_info));

    Util::msg("Created deck: $deck");
  }
}
