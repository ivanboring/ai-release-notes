<?php

/**
 * @file
 * Write release notes for the AI module.
 */

// Make sure that version, with_html and project are set via cli.
$project = $argv[1] ?? '';
$version = $argv[2] ?? '';
$with_html = $argv[3] ?? FALSE;

if (!$project || !$version) {
  echo "Usage: php write_release_notes.php [project] [last_version] [with_html (optional, default false)]\n";
  exit(1);
}

$from = $version;

$mapping = [
  3 => 'New Features',
  2 => 'Tasks',
  1 => 'Bugs',
  4 => 'Support Requests',
  5 => 'Planning',
];

// Try to load the cache if it exists.
if (file_exists('cache.json')) {
  $cache = json_decode(file_get_contents('cache.json'), TRUE);
  if (is_array($cache) && isset($cache['issues'], $cache['contributors'], $cache['organizations'])) {
    $issues = $cache['issues'];
    $contributors = $cache['contributors'];
    $organizations = $cache['organizations'];
  }
  if ($with_html) {
    $text = '<p>Issues resolved since <a href="https://www.drupal.org/project/' . $project . '/releases/' . $from . '">' . $from . '</a>: ' . count($issues) . '</p>';
    // The actual output.
    $text .= "<h3>Contributors</h3>\n";
    $contrib_text = [];
    foreach ($contributors as $contributor => $count) {
      $contrib_text[] = '<a href="https://www.drupal.org/u/' . $contributor . '">' . $contributor . '</a> (' . $count . ')';
    }
    $text .= implode(', ', $contrib_text) . "\n";

    // Sort the issues by category.
    $sorted_issues = [];
    foreach ($issues as $issue) {
      $sorted_issues[$issue['category']][] = '<li>' . format_issue_html($issue) . '</li>';
    }

    foreach ($mapping as $key => $category) {
      if (isset($sorted_issues[$key])) {
        $text .= "<h3>$category</h3>\n";
        $text .= "<ul>\n";
        $text .= implode("\n", $sorted_issues[$key]) . "\n";
        $text .= "</ul>\n";
      }
    }

    // List organizations.
    if (!empty($organizations)) {
      $text .= "<h3>Organizations</h3>\n";
      $org_text = [];
      foreach ($organizations as $organization => $count) {
        $org_text[] = $organization . ' (' . $count . ')';
      }
      $text .= implode(', ', $org_text) . "\n";
    }

    $text .= "<h3>Stats</h3>\n";
    $text .= "<p><strong>Amount of contributors: </strong>" . count($contributors) . "</p>\n";
    $text .= "<p><strong>Amount of organizations: </strong>" . count($organizations) . "</p>\n";
    $text .= "<p><strong>Amount of issues: </strong>" . count($issues) . "</p>\n";
  }
  else {
    $text = "";
    // Sort the issues by category.
    $sorted_issues = [];
    foreach ($issues as $issue) {
      $sorted_issues[$issue['category']][] = '* #' . $issue['issue_number'] . ' ' . $issue['title'];
    }

    foreach ($mapping as $key => $category) {
      if (isset($sorted_issues[$key])) {
        $text .= "$category\n";
        $text .= implode("\n", $sorted_issues[$key]) . "\n";
        $text .= "\n";
      }
    }

    $text .= "Contributors:\n";
    $text .= implode(', ', array_keys($contributors)) . "\n";
  }
}

echo $text;

function format_issue_html(array $issue): string {
  $title = $issue['title'] ?? ('#' . ($issue['issue_number'] ?? ''));
  $url = $issue['url'] ?? '';

  if ($url) {
    return '<a href="' . html_escape($url) . '">' . html_escape($title) . '</a>';
  }

  return html_escape($title);
}

function html_escape(string $text): string {
  return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
