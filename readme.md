# AI Release Notes

This project generates release notes for the AI module and other modules, based on the issues closed in a given release and the people credited on these. https://drupal-mrn.dev/ is great for this, but we wanted to be able to count any credited person on an issue, so that UX, QA etc. that typically do not push code can also be credited in the release notes.

## Installation
1. Clone this repository.
2. Run `composer install` to install dependencies.

## Usage
Example is based on the AI module, change accordingly for other modules. In this example we will generate release notes for the AI 1.1.8 release.

1. Go to https://git.drupalcode.org/project/ai and click on code >> compare revisions.
2. In the Source version select the main version branch, in this case "1.1.x".
3. In the Target version select the last release, in this case "1.1.7".
4. Click on compare and you will be given a list of all commits since the last release.
5. Copy all the issue numbers from the commit messages into a text file called copied_issues.txt, one issue number per line.
6. (Or) use `git log` to get the commit messages between two tags and extract the issue numbers into copied_issues.txt.
7. Run the script to get release notes:
   ```bash
   php generate_release_notes.php copied_issues.txt ai 1.1.8
   ```
8. This will create a cache.json file to speed up future writing.
9. The release notes will be printed to the console. You need to specify project, previous version and if you want plain text for a release or html for the publishing - in this example we want plain text:
   ```bash
   php write_release_notes.php ai 1.1.7
   ```
10. When you are finished, you can delete the copied_issues.txt and cache.json files.
