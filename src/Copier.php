<?php

namespace AnkiDeckManager;

use Ramsey\Uuid\Uuid;

class Copier {

  public static function copy($deck1, $deck2, $base) {
    $deck1_path = $base . '/decks/' . Util::ensureDeckFilename($deck1);

    $deck2_suffix = "";
    if ($deck2) {
      $deck2_path = $base . '/decks/' . Util::ensureDeckFilename($deck2);
    }
    else {
      // Calculate destination deck name
      $i = 1;
      while(file_exists($deck2_path = $deck1_path . " ($i)")) {
        $i++;
      }
      $deck2_suffix = " ($i)";
    }

    // Check if source deck exists
    if (!is_dir($deck1_path)) {
      Util::err("Source deck not found: " . $deck1);
    }

    if (!Util::xcopy($deck1_path, $deck2_path)) {
      Util::err("Cannot copy files");
    }

    $deck_build = Util::getJson($deck2_path . '/build.json');

    $deck_build['name'] = isset($deck2) ? $deck2 : $deck_build['name'] . $deck2_suffix;
    $deck_uuid = Uuid::uuid1();
    // Create new uuids
    $deck_build['uuids'] = [
      'deck' => $deck_uuid,
      'model' => Uuid::uuid1(),
      'config' => $deck_uuid
    ];

    file_put_contents($deck2_path . '/build.json', Util::toJson($deck_build));

    Util::msg("Created deck: $deck_build[name]");
  }

}