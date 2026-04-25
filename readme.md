# AI Release Notes

This project generates release notes for the AI module and other modules, based on the issues closed in a given release and the people credited on these. https://drupal-mrn.dev/ is great for this, but we wanted to be able to count any credited person on an issue, so that UX, QA etc. that typically do not push code can also be credited in the release notes.

## Installation
1. Clone this repository.
2. Run `composer install` to install dependencies.

## Usage
Example is based on the AI module. Change the project and versions for other modules.

In this example we generate notes for changes from `1.3.3` to `1.4.x`.

1. Fetch issue data from GitLab and Drupal.org:
   ```bash
   php get_release_notes.php ai 1.3.3 1.4.x
   ```

   This creates `cache.json` with issues, contributors, and organizations.

   The script uses the GitLab compare API:
   ```text
   https://git.drupalcode.org/api/v4/projects/project%2Fai/repository/compare?from=1.3.3&to=1.4.x
   ```

   For every commit, it extracts a Drupal issue number from `#1234567`, loads the matching GitLab work item, and stores the work item URL:
   ```text
   https://git.drupalcode.org/project/ai/-/work_items/1234567
   ```

2. Write plain text release notes:
   ```bash
   php write_release_notes.php ai 1.3.3
   ```

3. Or write HTML release notes for publishing:
   ```bash
   php write_release_notes.php ai 1.3.3 1
   ```

   HTML output links issue titles to their GitLab work item pages.

4. When you are finished, you can delete `cache.json`.

## Arguments

`get_release_notes.php`:

```bash
php get_release_notes.php [project] [from] [to]
```

- `project`: Drupal GitLab project name, for example `ai`. You can also pass a full path such as `project/ai`.
- `from`: Previous release/tag/ref.
- `to`: Target release/tag/ref or branch.

`write_release_notes.php`:

```bash
php write_release_notes.php [project] [last_version] [with_html]
```

- `project`: Drupal.org project name, for example `ai`.
- `last_version`: Previous release version used in the release notes intro link.
- `with_html`: Optional. Pass a truthy value such as `1` to generate HTML.
