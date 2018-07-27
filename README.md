# Anki Deck Manager

**Anki Deck Manager** (`anki-dsm`) is a tool for creating and maintaining 
multilingual multi-variant Anki decks. It reads files organized in 
a specific way and generates CrowdAnki JSON decks. I.e. it doesn't create   
some installable `*.apkg` files for Anki, you should use [CrowdAnki add-on](https://github.com/Stvad/CrowdAnki)
to install JSON decks.

## How it worked before (CrowdAnki only)

**CrowAnki** add-on defines a JSON format for storing decks data 
and can export/import into it.  

But it's actually not that convenient to edit big JSON files manually and there are no 
tools which would considerably simplify the process. In other words, CrowdAnki doesn't 
change the way how you manage decks, the editing workflow remains the same: you edit
your deck inside Anki application.

## How it works now (CrowdAnki + Anki DM)

**Anki Deck Manager** disassembles CrowdAnki deck into a collection of files 
and directories which are really easy to maintain. Specifically, given a CrowdAnki 
deck at its input it generates a file structure like this:  

```
src/
  decks/
    DeckName/
      build.json
      desc.html
  fields/
    Front.json
    Back.json
  media/
    Card-1.png
    Card-2.png
  templates/
    Card 1.html
  config.json
  data.csv
  deck.json
  desc.html
  model.json
  style.css
```

These files are then used to create CrowdAnki decks during the build process (see below). 
They are divided into two levels: global and deck-specific.

### Global level files

It contains files which are shared between decks (or deck variants):

- `fields/*.json` - deck fields in JSON format;
- `templates/*.html` - deck templates in HTML format;
- `media/*` - media files - images or audio; 
- `desc.html` - deck description in HTML format;
- `style.css` - deck stylesheet in CSS format;
- `config.json` - deck configuration options;
- `model.json` - model deck properties like type of the deck (Cloze or not), LeTex document prefix and suffix;
- `deck.json` - some deck properties;
- `data.csv` - main data file, containing cards information in CSV format.

These files are considered to be common for all the decks, but some of them 
can be overridden at the deck-specific level.

### Deck level files

You can create unlimited amount of deck variants. They are stored inside
the `decks/` directory. Each can have its own list of fields and templates 
as well as description, options and styles.

- `decks/<DeckName>/build.json` - main deck configuration file. It's the file
where you define the list and the order of the fields and templates, set the name
of the deck and the model, and assign UUIDs for the deck, model and config. 

```json
{
  "name": "My Deck",
  "model_name": "My Deck Model",
  "uuids": {
    "deck": "a49aa3a0-8f80-11e8-a999-c86000cb6fe2",
    "model": "a49aa3a4-8f80-11e8-a999-c86000cb6fe2",
    "config": "a49aa3a1-8f80-11e8-a999-c86000cb6fe2"
  },
  "fields": [
    "Front",
    "Back"
  ],
  "templates": [
    "Card 1"
  ]
}

```

The files: `desc.html`, `style.css`, `config.json`, `model.json` and `deck.json` - 
serve the same purpose as their parents at the global level but override them.

You cannot override however `data.csv` file. That one, along with field 
and template definitions cannot be redefined at the deck level. This is by design.

### Data file structure

The data file - `data.csv` - is just a regular CSV file with the header. 
Each field from the `fields/*.json` list must be represented here as a column.  

In addition there are two special columns: `guid` and `tags`:
```
guid,       Name,     Flag,                           tags
e+/O]%*qfk, England,  "<img src=""England.png"" />",  Europe
h~5xz+=ke~, Scotland, "<img src=""Scotland.png"" />", Europe
```

The `tags` column contains whitespace-separated lists of tags.

The `guid` column is an identifier for a row. For the same data 
it should always be the same or deck update for this row won't work. 
When you add new rows just leave the `guid` cells empty and run
`anki-dm index` once you're done.

### Data translation

To localize a deck simply add new columns with translation. 
**Anki DM** will parse the column names trying to extract language code
which is specified via suffix: `<FieldName>:fr`.   

Consider the example (spaces are added for readability):

```
guid,       Name,  Color,   tags
qSYi3}_Rdg, Red,   #ff0000, ""
q`KoKFfXAS, Green, #00ff00, ""
qSYi3}_Rdg, Blue,  #0000ff, ""
```

It defines 3 rows of the data and 2 columns without language specification.
In this form the language is considered to be undefined or `default`. Let's
now add French color names:


```
guid,       Name,  Name:fr, Color,   tags
qSYi3}_Rdg, Red,   Rouge,   #ff0000, ""
q`KoKFfXAS, Green, Vert,    #00ff00, ""
qSYi3}_Rdg, Blue,  Bleu,    #0000ff, ""
```

## Installation

**Anki DM** is a PHP script. To run it you need to install:

- [PHP 7.x](http://php.net)
- [Composer](https://getcomposer.org/download/)

Then create the `composer.json` file with the following minimal content:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/OnkelTem/anki-dm"
    }
  ],
  "require": {
    "OnkelTem/anki-dm": "*"
  }
}
```

Now if you run `composer install` it should download and install everything.
The `anki-dm` executable will be installed under `./vendor/bin/` directory.

## Usage

You can get the full list of options and commands via `anki-dm --help`.

Usually you do something like this:

- Export a deck from Anki using the CrowdAnki export option.
- Get the deck disassembled into Anki DM framework:
    ```
    $ anki-dm import path/to/deck/directory
    ```
- Edit files according to your needs.
- If you've added some new rows into `data.csv`, update the `guid` index:
    ```
    $ anki-dm index
    ```
- Finally build the deck(s): 
    ```
    $ anki-dm build
    ```
   The decks will be saved in the `build/` directory. They're now
   ready to be imported inside Anki.    

Instead of starting with an existing deck you can use `init` command 
which imports an empty deck from one of predefined templates, e.g.:

```
$ anki-dm init Default
```

Making a copy of a deck can be done it two ways. The first one is obvious:
just copy the deck's directory with a new name:

```
$ cp -r decks/ExistingDeck decks/NewDeck
```
  
But doing so you're also copying UUIDs of the deck, model
and config (they are stored in the `build.json` file). If you then build
two decks with different models (i.e. different lists of fields) but with the 
same Model UUID it can drive Anki mad and not without a reason, eh? 

Instead please use the `copy` command: 

```
$ anki-dm copy ExistingDeck NewDeck
```

It will not only copy files recursively, but will also generate appropriate UUIDs.  
