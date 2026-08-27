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
php get_release_notes.php [project] [from] [to] [--exclude=from..to]
```

- `project`: Drupal GitLab project name, for example `ai`. You can also pass a full path such as `project/ai`.
- `from`: Previous release/tag/ref.
- `to`: Target release/tag/ref or branch.
- `--exclude`: Optional, repeatable. A ref such as `1.4.x`, or a `from..to` range, whose issues have already been released and should be left out.

### Excluding cherry-picked commits

When you cherry-pick a fix from `1.4.x` into `1.5.x`, the commit gets a new hash,
so the compare API reports it as a new commit in the `1.5.x` range. Hashes cannot
be matched across a cherry-pick, but the Drupal issue number can.

The script handles this automatically for the two refs you compare: it also runs
the compare in the reverse direction (`to..from`), which yields the commits that
are on `from` but not on `to` - the other half of each cherry-pick pair - and
skips their issue numbers. So `php get_release_notes.php ai 1.4.x 1.5.x` only
lists issues that are new in `1.5.x`.

Use `--exclude` when the work was also released on a branch that is not part of
the compare:

```bash
php get_release_notes.php ai 1.4.0 1.5.x --exclude=1.4.x
```

Any issue that appears anywhere in the history of `1.4.x` is skipped in the `1.5.x`
notes, regardless of which hash it landed under. Pass `--exclude` more than once if
the work was already released on several branches.

Prefer a bare ref over a range. A range only covers what is between its two
endpoints, so `--exclude=1.4.0..1.4.x` still lets through anything that shipped in
`1.4.0` itself, which is a large part of what gets cherry-picked forward.

Note that the exclude pass reads only the commit title and message, so a
cherry-picked commit that lost its `#1234567` reference cannot be matched. Such a
commit is dropped from the notes anyway, for the same reason.

`write_release_notes.php`:

```bash
php write_release_notes.php [project] [last_version] [with_html]
```

- `project`: Drupal.org project name, for example `ai`.
- `last_version`: Previous release version used in the release notes intro link.
- `with_html`: Optional. Pass a truthy value such as `1` to generate HTML.
