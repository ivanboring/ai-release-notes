<?php

$category_mapping = [
  'category:bug' => '1',
  'category:task' => '2',
  'category:feature' => '3',
  'category:support' => '4',
  'category:plan' => '5',
];

function normalize_project_path(string $project): string {
  if (strpos($project, '/') !== FALSE) {
    return $project;
  }
  return 'project/' . $project;
}

function build_work_item_url(string $project_path, string $issue_number): string {
  return 'https://git.drupalcode.org/' . $project_path . '/-/work_items/' . $issue_number;
}

function fetch_json(string $url, bool $use_browser_headers = TRUE): ?array {
  $response = fetch_url($url, $use_browser_headers);
  if ($response === '') {
    return NULL;
  }

  $json = json_decode($response, TRUE);
  if (!is_array($json)) {
    echo "Failed to decode JSON URL: $url\n";
    return NULL;
  }

  return $json;
}

function fetch_text(string $url): string {
  return fetch_url($url);
}

function fetch_url(string $url, bool $use_browser_headers = TRUE): string {
  $ch = curl_init($url);
  $options = [
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_FOLLOWLOCATION => TRUE,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_TIMEOUT => 120,
  ];

  if ($use_browser_headers) {
    $options += [
      CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
      CURLOPT_HTTPHEADER => [
        'Accept: application/json, text/html;q=0.9, */*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
      ],
    ];
  }

  curl_setopt_array($ch, $options);

  $response = curl_exec($ch);
  $error = curl_error($ch);
  $status_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

  if ($response === FALSE) {
    echo "Failed to fetch URL: $url\n";
    if ($error) {
      echo "cURL error: $error\n";
    }
    return '';
  }

  if ($status_code >= 400) {
    echo "Failed to fetch URL: $url (HTTP $status_code)\n";
    return '';
  }

  return $response;
}

function extract_issue_number(string $text): ?string {
  if (preg_match('/#(\d+)/', $text, $matches)) {
    return $matches[1];
  }
  return NULL;
}

function find_issue_number_from_commit_details(string $encoded_project, array $commit): ?string {
  $message = $commit['message'] ?? '';
  $issue_number = extract_issue_number($message);
  if ($issue_number) {
    return $issue_number;
  }

  if (!empty($commit['id'])) {
    $commit_url = 'https://git.drupalcode.org/api/v4/projects/' . $encoded_project . '/repository/commits/' . rawurlencode($commit['id']);
    $commit_details = fetch_json($commit_url);
    if ($commit_details) {
      foreach (['title', 'message', 'description'] as $field) {
        if (!empty($commit_details[$field])) {
          $issue_number = extract_issue_number($commit_details[$field]);
          if ($issue_number) {
            return $issue_number;
          }
        }
      }

      $issue_number = extract_issue_number(json_encode($commit_details));
      if ($issue_number) {
        return $issue_number;
      }
    }
  }

  if (!empty($commit['web_url'])) {
    $issue_number = extract_issue_number(fetch_text($commit['web_url']));
    if ($issue_number) {
      return $issue_number;
    }
  }

  if (!empty($commit['id'])) {
    $issue_number = find_issue_number_from_mr($encoded_project, $commit['id']);
    if ($issue_number) {
      return $issue_number;
    }
  }

  return NULL;
}

function find_issue_number_from_mr(string $encoded_project, string $commit_sha): ?string {
  $mrs_url = 'https://git.drupalcode.org/api/v4/projects/' . $encoded_project . '/repository/commits/' . rawurlencode($commit_sha) . '/merge_requests';
  $mrs = fetch_json($mrs_url);
  if (!is_array($mrs)) {
    return NULL;
  }

  foreach ($mrs as $mr) {
    $mr_iid = $mr['iid'] ?? NULL;
    if (!$mr_iid) {
      continue;
    }

    $closes_url = 'https://git.drupalcode.org/api/v4/projects/' . $encoded_project . '/merge_requests/' . rawurlencode($mr_iid) . '/closes_issues';
    $closed_issues = fetch_json($closes_url);
    if (!is_array($closed_issues)) {
      continue;
    }

    foreach ($closed_issues as $issue) {
      $iid = $issue['iid'] ?? NULL;
      if ($iid) {
        echo "Found issue #$iid via MR !$mr_iid for commit $commit_sha\n";
        return (string) $iid;
      }
    }
  }

  return NULL;
}

function fetch_work_item(string $encoded_project, string $work_item_url, string $issue_number): array {
  $work_item = fetch_json('https://git.drupalcode.org/api/v4/projects/' . $encoded_project . '/issues/' . rawurlencode($issue_number));
  if (is_array($work_item)) {
    return [
      'title' => $work_item['title'] ?? '',
      'labels' => extract_work_item_labels($work_item),
    ];
  }

  $work_item_page = fetch_text($work_item_url);
  $title = extract_html_title($work_item_page);

  // Reject titles that are Cloudflare or auth error pages, not real issues.
  $error_titles = ['Client Challenge', 'Just a moment...', 'Access denied', 'Attention Required', '403 Forbidden', '404 Not Found'];
  if (in_array($title, $error_titles, TRUE)) {
    return ['title' => '', 'labels' => []];
  }

  return [
    'title' => $title,
    'labels' => extract_category_labels_from_text($work_item_page),
  ];
}

function extract_work_item_labels(array $work_item): array {
  if (!empty($work_item['labels']) && is_array($work_item['labels'])) {
    return normalize_labels($work_item['labels']);
  }

  if (!empty($work_item['widgets']) && is_array($work_item['widgets'])) {
    foreach ($work_item['widgets'] as $widget) {
      $type = strtolower($widget['type'] ?? '');
      if ($type === 'labels' && !empty($widget['labels']) && is_array($widget['labels'])) {
        return normalize_labels($widget['labels']);
      }
    }
  }

  return [];
}

function normalize_labels(array $labels): array {
  $normalized_labels = [];
  foreach ($labels as $label) {
    if (is_string($label)) {
      $normalized_labels[] = $label;
      continue;
    }

    if (is_array($label)) {
      if (!empty($label['title'])) {
        $normalized_labels[] = $label['title'];
      }
      elseif (!empty($label['name'])) {
        $normalized_labels[] = $label['name'];
      }
    }
  }

  return $normalized_labels;
}

function extract_html_title(string $html): string {
  if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
    $title = html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $title = preg_replace('/\s+\(#[0-9]+\)\s+.*$/', '', $title);
    return trim($title);
  }

  return '';
}

function extract_category_labels_from_text(string $text): array {
  if (preg_match_all('/category:{1,2}(bug|task|feature|plan|support)/i', $text, $matches)) {
    $labels = [];
    foreach ($matches[1] as $category) {
      $labels[] = 'category:' . strtolower($category);
    }
    return array_values(array_unique($labels));
  }

  return [];
}

function category_from_labels(array $labels, array $category_mapping): string {
  foreach ($labels as $label) {
    $normalized_label = strtolower(trim($label));
    $normalized_label = str_replace('category::', 'category:', $normalized_label);
    if (isset($category_mapping[$normalized_label])) {
      return $category_mapping[$normalized_label];
    }
  }

  return $category_mapping['category:bug'];
}

function add_credits_for_issue(string $issue_number, string $work_item_url, array &$contributors, array &$organizations): void {
  $round = [];
  $org_issue = [];
  $users = [];
  $actual_orgs = [];

  $query = http_build_query([
    'filter[field_source_link.uri]' => $work_item_url,
    'include' => 'field_contributors,field_contributors.field_contributor_user,field_contributors.field_contributor_organisation,field_contributors.field_contributor_customer',
  ], '', '&', PHP_QUERY_RFC3986);
  $credit_url = "https://www.drupal.org/jsonapi/node/contribution_record?" . $query;
  $credit_response = fetch_json($credit_url, FALSE);
  if (!isset($credit_response['included'])) {
    echo "No credit records found for issue #$issue_number.\n";
    return;
  }

  foreach ($credit_response['included'] as $included) {
    if ($included['type'] === 'paragraph--contributor') {
      if (credit_field_enabled($included['attributes']['field_credit_this_contributor'] ?? NULL)) {
        foreach (relationship_ids($included, 'field_contributor_user') as $user_id) {
          $users[] = $user_id;
        }
        if (credit_field_enabled($included['attributes']['field_contributor_attribute_orgs'] ?? NULL)) {
          foreach (relationship_ids($included, 'field_contributor_customer') as $customer_id) {
            $actual_orgs[] = $customer_id;
          }
          foreach (relationship_ids($included, 'field_contributor_organisation') as $org_id) {
            $actual_orgs[] = $org_id;
          }
        }
      }
    }
  }

  foreach ($credit_response['included'] as $included) {
    if ($included['type'] === 'user--user' && in_array($included['id'], $users)) {
      if (isset($round[$included['attributes']['name']])) {
        continue;
      }
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
    if ($included['type'] === 'node--organization' && in_array($included['id'], $actual_orgs)) {
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
    if ($included['type'] === 'node--customer' && in_array($included['id'], $actual_orgs)) {
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
}

function credit_field_enabled($value): bool {
  return $value !== FALSE && $value !== 0 && $value !== '0';
}

function relationship_ids(array $included, string $relationship): array {
  $data = $included['relationships'][$relationship]['data'] ?? [];
  if (isset($data['id'])) {
    return [$data['id']];
  }

  $ids = [];
  foreach ($data as $item) {
    if (isset($item['id'])) {
      $ids[] = $item['id'];
    }
  }

  return $ids;
}

function write_cache(array $issues, array $contributors, array $organizations): void {
  file_put_contents('cache.json', json_encode([
    'issues' => $issues,
    'contributors' => $contributors,
    'organizations' => $organizations,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
