# Contributing

Thank you for taking the time to contribute! Ask for help, report a bug, or request a feature simply by [opening a new issue](https://github.com/OnkelTem/anki-dm/issues)! If you would like to contribute code, please fork the project and keep reading for how to set it up.

# Beginner setup for contributing code

The goal of this section is to make it as easy for new contributors to add and test simple contributions. It assumes the user has succesfully installed and used `anki-dm` with a deck, but does not assume any additional knowledge of PHP or Composer.

## Github project: Fork, clone, and create new branch

Please fork the Anki Deck Manager project and clone it to your local machine. If you haven't used GitHub bfore, please refer to [this guide](https://guides.github.com/activities/contributing-to-open-source).

Create a new branch. In this example it is named `my-branch-xyz`.

## Install from fork

To install Anki Deck Manager in your deck using your fork's new branch, the deck's `composer.json` will need modification. If your shared deck is also online, ensure these changes to `composer.json` are not inadvertently committed to the shared deck's `composer.json`. If the shared deck is on Github, this is easily accomplished by forking the shared deck and making a disposable branch named `for-testing-anki-dm` for example.

In the deck's `composer.json`, first modify the repository URL to point either to fork's online Github URL or to your fork's code cloned on your local machine. If using a local Windows clone, the path should look like `C:/username/Documents/GitHub/anki-dm`. In particular, do not use `\` as it is for escaping.

Second, modify the `@dev` to be your branch name with `dev-` added as a prefix. In this example the branch is named `my-branch-xyz`, so the `@dev` is replaced with `dev-my-branch-xyz`.

Here is an example modified `composer.json` pointing to a local machine clone:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "/full/path/to/the/local/package/package-name"
    }
  ],
  "require": {
    "onkeltem/anki-dm": "dev-my-branch-xyz"
  }
}
```

After this change, run `composer update` to overwrite the existing `anki-dm` installation with your fork.

Note, simply modifying files and then running `composer update` will return `Nothing to install or update`, ignoring whatever changes you made. For Composer to recognize your changes and actually update `anki-dm`, you must make a commit of the changes.