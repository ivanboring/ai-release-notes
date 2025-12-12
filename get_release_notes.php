<?php

include('vendor/autoload.php');
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;

/**
 * Script to fetch and cache Drupal release notes.
 */

try {
  $data = file_get_contents('copied_issues.txt');
}
catch (Exception $e) {
  echo "Error reading file: " . $e->getMessage();
  exit(1);
}

$mapping = [
  1 => 'Bugs',
  2 => 'Tasks',
  3 => 'New Features',
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
    echo "Cache loaded successfully.\n";

    foreach ($issues as $key => $issue) {
      // If there is no title, we have to rerun it.
      if (empty($issue['title'])) {
        $issue_number = $issue['issue_number'];
        echo "Issue #{$issue['issue_number']} has no title, re-fetching...\n";
        // Contacting Drupal API to get the release notes.
        $url = "https://www.drupal.org/api-d7/node/$issue_number.json?drupalorg_extra_credit=1";
        $response = json_decode(file_get_contents($url), TRUE);
        $issues[$key]['issue_number'] = $issue_number;
        $issues[$key]['title'] = $response['title'];
        $issues[$key]['url'] = $response['url'];
        $issues[$key]['category'] = $response['field_issue_category'];
        $round = [];
        $org_issue = [];

        $credit_url = "https://www.drupal.org/jsonapi/node/contribution_record?filter[field_source_link.uri]=https://www.drupal.org/node/" . $issue_number . "&include=field_contributors,field_contributors.field_contributor_user,field_contributors.field_contributor_organisation,field_contributors.field_contributor_customer";
        $credit_response = make_browser_call($credit_url);
        // Extract the json part from the HTML with { and }.
        $start = strpos($credit_response, '{');
        $end = strrpos($credit_response, '}');
        $credit_response = substr($credit_response, $start, $end - $start + 1);
        $credit_response = json_decode($credit_response, TRUE);
        if (!isset($credit_response['included'])) {
          unset($issues[$key]);
          continue;
        }
        foreach ($credit_response['included'] as $included) {
          if ($included['type'] === 'user--user') {
            // If the user already exists in the round, skip it.
            if (isset($round[$included['attributes']['name']])) {
              continue;
            }
            // Also skip if the name is "System Message".
            if ($included['attributes']['name'] === 'System Message') {
              continue;
            }
            if (!isset($contributors[$included['attributes']['name']])) {
              $contributors[$included['attributes']['name']] = 1;
            } else {
              $contributors[$included['attributes']['name']]++;
            }
            $round[$included['attributes']['name']] = TRUE;
          }
          if ($included['type'] === 'node--organization') {
            if (!isset($organizations[$included['attributes']['title']])) {
              if (!isset($org_issue[$included['attributes']['title']])) {
                $organizations[$included['attributes']['title']] = 1;
                $org_issue[$included['attributes']['title']] = TRUE;
              }
            } else {
              if (!isset($org_issue[$included['attributes']['title']])) {
                $organizations[$included['attributes']['title']]++;
                $org_issue[$included['attributes']['title']] = TRUE;
              }
            }
          }
          if ($included['type'] === 'node--customer') {
            if (!isset($organizations[$included['attributes']['title']])) {
              if (!isset($org_issue[$included['attributes']['title']])) {
                $organizations[$included['attributes']['title']] = 1;
                $org_issue[$included['attributes']['title']] = TRUE;
              }
            } else {
              if (!isset($org_issue[$included['attributes']['title']])) {
                $organizations[$included['attributes']['title']]++;
                $org_issue[$included['attributes']['title']] = TRUE;
              }
            }
          }
        }
      }

      // Write out a cache.
      file_put_contents('cache.json', json_encode([
        'issues' => $issues,
        'contributors' => $contributors,
        'organizations' => $organizations,
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
  }
}

foreach (explode("\n", $data) as $key => $line) {
  if (trim($line) === '') {
    continue;
  }
  // Find the issue number in the line it should be #34243.
  if (preg_match('/#(\d+)/', $line, $matches)) {
    // Sleep for Drupal API rate limiting.
    sleep(5);
    $issue_number = $matches[1];
    // Check if we already have this issue in the cache.
    if (isset($issues[$key]) && isset($issues[$key]['issue_number']) && $issues[$key]['issue_number'] == $issue_number && !empty($issues[$key]['title'])) {
      echo "Issue #{$issue_number} already in cache, skipping...\n";
      continue;
    }
    echo "Found issue number: $issue_number\n";

    // Contacting Drupal API to get the release notes.
    $url = "https://www.drupal.org/api-d7/node/$issue_number.json?drupalorg_extra_credit=1";
    $response = json_decode(file_get_contents($url), TRUE);
    $issues[$key]['issue_number'] = $issue_number;
    $issues[$key]['title'] = $response['title'];
    $issues[$key]['url'] = $response['url'];
    $issues[$key]['category'] = $response['field_issue_category'];
    $round = [];
    $org_issue = [];
    $credit_url = "https://www.drupal.org/jsonapi/node/contribution_record?filter[field_source_link.uri]=https://www.drupal.org/node/" . $issue_number . "&include=field_contributors,field_contributors.field_contributor_user,field_contributors.field_contributor_organisation,field_contributors.field_contributor_customer";
    $credit_response = make_browser_call($credit_url);
    // Extract the json part from the HTML with { and }.
    $start = strpos($credit_response, '{');
    $end = strrpos($credit_response, '}');
    $credit_response = substr($credit_response, $start, $end - $start + 1);
    $credit_response = json_decode($credit_response, TRUE);
    if (!isset($credit_response['included'])) {
      unset($issues[$key]);
      continue;
    }
    foreach ($credit_response['included'] as $included) {
      if ($included['type'] === 'user--user') {
        // If the user already exists in the round, skip it.
        if (isset($round[$included['attributes']['name']])) {
          continue;
        }
        // Also skip if the name is "System Message".
        if ($included['attributes']['name'] === 'System Message') {
          continue;
        }
        if (!isset($contributors[$included['attributes']['name']])) {
          $contributors[$included['attributes']['name']] = 1;
        }
        else {
          $contributors[$included['attributes']['name']]++;
        }
        $round[$included['attributes']['name']] = TRUE;
      }
      if ($included['type'] === 'node--organization') {
        if (!isset($organizations[$included['attributes']['title']])) {
          if (!isset($org_issue[$included['attributes']['title']])) {
            $organizations[$included['attributes']['title']] = 1;
            $org_issue[$included['attributes']['title']] = TRUE;
          }
        }
        else {
          if (!isset($org_issue[$included['attributes']['title']])) {
            $organizations[$included['attributes']['title']]++;
            $org_issue[$included['attributes']['title']] = TRUE;
          }
        }
      }
      if ($included['type'] === 'node--customer') {
        if (!isset($organizations[$included['attributes']['title']])) {
          if (!isset($org_issue[$included['attributes']['title']])) {
            $organizations[$included['attributes']['title']] = 1;
            $org_issue[$included['attributes']['title']] = TRUE;
          }
        }
        else {
          if (!isset($org_issue[$included['attributes']['title']])) {
            $organizations[$included['attributes']['title']]++;
            $org_issue[$included['attributes']['title']] = TRUE;
          }
        }
      }
    }
    // Write out a cache.
    file_put_contents('cache.json', json_encode([
      'issues' => $issues,
      'contributors' => $contributors,
      'organizations' => $organizations,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }
  else {
    echo "No issue number found in line: $line\n";
    exit;
  }


}

function make_browser_call($url) {
  echo "Fetching URL via browser: $url\n";
  $browserFactory = new BrowserFactory();

  // starts headless Chrome
  $browser = $browserFactory->createBrowser([
    'startupTimeout' => 60,
  ]);

  try {
    // creates a new page and navigate to an URL
    $page = $browser->createPage();
    $page->navigate($url)->waitForNavigation(Page::DOM_CONTENT_LOADED, 60000);
    $html = $page->getHtml();
  } finally {
    // bye
    $browser->close();
  }
  return $html;
}
