<?php

include('vendor/autoload.php');
include('functions.php');

/**
 * Manually add a missed issue to cache.json, including credits.
 *
 * Usage: php add_issue.php [project] [issue_number]
 * Example: php add_issue.php ai 3578472
 */

$project = $argv[1] ?? '';
$issue_number = $argv[2] ?? '';

if (!$project || !$issue_number) {
  echo "Usage: php add_issue.php [project] [issue_number]\n";
  echo "Example: php add_issue.php ai 3578472\n";
  exit(1);
}

if (!file_exists('cache.json')) {
  echo "cache.json not found. Run get_release_notes.php first.\n";
  exit(1);
}

$cache = json_decode(file_get_contents('cache.json'), TRUE);
if (!is_array($cache) || !isset($cache['issues'], $cache['contributors'], $cache['organizations'])) {
  echo "cache.json is invalid or incomplete.\n";
  exit(1);
}

$issues = $cache['issues'];
$contributors = $cache['contributors'];
$organizations = $cache['organizations'];

$existing = array_column($issues, 'issue_number');
if (in_array($issue_number, $existing)) {
  echo "Issue #$issue_number is already in cache.json, nothing to do.\n";
  exit(0);
}

$project_path = normalize_project_path($project);
$encoded_project = rawurlencode($project_path);
$work_item_url = build_work_item_url($project_path, $issue_number);

echo "Fetching issue #$issue_number...\n";
$work_item = fetch_work_item($encoded_project, $work_item_url, $issue_number);
$title = $work_item['title'] ?? '';

if ($title === '') {
  echo "Could not find a title for issue #$issue_number. Check the issue number and project.\n";
  exit(1);
}

$labels = $work_item['labels'] ?? [];
$category = category_from_labels($labels, $category_mapping);

$issues[] = [
  'issue_number' => $issue_number,
  'title' => $title,
  'url' => $work_item_url,
  'category' => $category,
];

echo "Added issue #$issue_number: $title\n";
echo "Fetching credits...\n";

add_credits_for_issue($issue_number, $work_item_url, $contributors, $organizations);
write_cache($issues, $contributors, $organizations);

echo "cache.json updated. Total issues: " . count($issues) . "\n";
