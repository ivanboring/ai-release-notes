<?php

include('vendor/autoload.php');
include('functions.php');

/**
 * Script to fetch and cache Drupal release notes from a GitLab compare range.
 */

$project = $argv[1] ?? '';
$from = $argv[2] ?? '';
$to = $argv[3] ?? '';

if (!$project || !$from || !$to) {
  echo "Usage: php get_release_notes.php [project] [from] [to]\n";
  echo "Example: php get_release_notes.php ai 1.3.3 1.4.x\n";
  exit(1);
}

$issues = [];
$contributors = [];
$organizations = [];
$seen_issues = [];
$project_path = normalize_project_path($project);
$encoded_project = rawurlencode($project_path);
$compare_url = 'https://git.drupalcode.org/api/v4/projects/' . $encoded_project . '/repository/compare?from=' . rawurlencode($from) . '&to=' . rawurlencode($to);

echo "Fetching compare data: $compare_url\n";
$compare = fetch_json($compare_url);

if (!isset($compare['commits']) || !is_array($compare['commits'])) {
  echo "Compare response did not include commits.\n";
  exit(1);
}

foreach ($compare['commits'] as $commit) {
  $title = $commit['title'] ?? '';
  $issue_number = extract_issue_number($title);

  if (!$issue_number) {
    $issue_number = find_issue_number_from_commit_details($encoded_project, $commit);
  }

  if (!$issue_number) {
    echo "WARNING: No issue number found for commit: \"$title\". If this commit belongs to a valid issue, add it manually: php add_issue.php $project <issue_number>\n";
    continue;
  }

  if (isset($seen_issues[$issue_number])) {
    echo "Issue #$issue_number already found, skipping duplicate commit.\n";
    continue;
  }

  echo "Found issue #$issue_number: $title\n";
  $work_item_url = build_work_item_url($project_path, $issue_number);
  $work_item = fetch_work_item($encoded_project, $work_item_url, $issue_number);
  $title = $work_item['title'] ?? '';
  if ($title === '') {
    echo "WARNING: Could not resolve a valid title for issue #$issue_number (the issue may not exist in this project or the page returned an error). If this is a valid issue, add it manually: php add_issue.php $project $issue_number\n";
    continue;
  }

  $labels = $work_item['labels'] ?? [];
  $category = category_from_labels($labels, $category_mapping);

  $issues[] = [
    'issue_number' => $issue_number,
    'title' => $title,
    'url' => $work_item_url,
    'category' => $category,
  ];
  $seen_issues[$issue_number] = TRUE;

  sleep(5);
  add_credits_for_issue($issue_number, $work_item_url, $contributors, $organizations);

  write_cache($issues, $contributors, $organizations);
}

write_cache($issues, $contributors, $organizations);
echo "Wrote cache.json with " . count($issues) . " issues.\n";
